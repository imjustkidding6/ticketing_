<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\User;
use App\Services\HelpService;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The promise of the help centre is that it never mentions something the
 * reader cannot do. These prove it, at both levels: whole topics, and blocks
 * inside a topic — gating only whole topics is the obvious half and the
 * useless half.
 */
class HelpCentreTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): Tenant
    {
        $plan = Plan::where('slug', 'enterprise')->first()
            ?? Plan::factory()->create(['slug' => 'enterprise', 'features' => PlanFeature::forPlan('enterprise')]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);
        SlaPolicy::factory()->create([
            'tenant_id' => $tenant->id, 'priority' => null,
            'client_tier' => null, 'is_active' => true,
        ]);

        return $tenant;
    }

    private function reader(Tenant $tenant, string $role): User
    {
        $user = User::factory()->create();
        $tenant->addUser($user, 'member');
        $rs = app(TenantRoleService::class);
        $rs->setTenantContext($tenant);
        $rs->setupDefaultRoles($tenant);
        $rs->syncRole($user, $role, $tenant);
        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);

        return $user;
    }

    private function help(): HelpService
    {
        return app(HelpService::class);
    }

    // ── the core promise ───────────────────────────────────────────────────

    public function test_two_roles_see_provably_different_topic_sets(): void
    {
        $tenant = $this->tenant();

        $admin = $this->reader($tenant, 'admin');
        $adminTopics = array_keys($this->help()->topicsFor($admin));

        $viewer = $this->reader($tenant, 'viewer');
        $viewerTopics = array_keys($this->help()->topicsFor($viewer));

        $this->assertNotEquals($adminTopics, $viewerTopics,
            'If both roles see the same guide, the scoping is not doing anything');
        $this->assertGreaterThan(count($viewerTopics), count($adminTopics));
        $this->assertContains('getting-started', $viewerTopics, 'An unscoped topic is visible to everyone');
    }

    public function test_blocks_are_filtered_inside_a_topic_not_just_whole_topics(): void
    {
        $tenant = $this->tenant();

        // An agent can view and update tickets but cannot merge them.
        $agent = $this->reader($tenant, 'agent');
        $topic = $this->help()->topicFor($agent, 'managing-tickets');

        $this->assertNotNull($topic, 'An agent reads about tickets');

        $text = $this->help()->searchTextFor($topic);
        $this->assertStringContainsString('Statuses', $text);
        $this->assertStringNotContainsString('Merging duplicates', $text,
            'An agent who cannot merge must not be taught how to');
    }

    public function test_a_heading_left_with_no_content_under_it_is_dropped(): void
    {
        $tenant = $this->tenant();
        $agent = $this->reader($tenant, 'agent');

        $blocks = $this->help()->topicFor($agent, 'managing-tickets')['blocks'];

        foreach ($blocks as $i => $block) {
            if (isset($block['h'])) {
                $next = $blocks[$i + 1] ?? null;
                $this->assertNotNull($next, "Heading '{$block['h']}' is the last block, with nothing under it");
                $this->assertArrayNotHasKey('h', $next, "Heading '{$block['h']}' is followed by another heading");
            }
        }
    }

    public function test_a_topic_emptied_by_filtering_disappears_entirely(): void
    {
        $tenant = $this->tenant();
        $viewer = $this->reader($tenant, 'viewer');

        // A viewer holds no settings permissions, so that topic has nothing
        // left to say to them.
        $this->assertNull($this->help()->topicFor($viewer, 'settings-configuration'));
    }

    public function test_minutes_total_only_what_this_reader_can_see(): void
    {
        $tenant = $this->tenant();

        $admin = $this->reader($tenant, 'admin');
        $adminMinutes = $this->help()->totalMinutesFor($admin);

        $viewer = $this->reader($tenant, 'viewer');
        $viewerMinutes = $this->help()->totalMinutesFor($viewer);

        $this->assertGreaterThan($viewerMinutes, $adminMinutes);
    }

    // ── the honest empty state ─────────────────────────────────────────────

    public function test_hidden_topics_name_the_permission_to_ask_for(): void
    {
        $tenant = $this->tenant();
        $viewer = $this->reader($tenant, 'viewer');

        $hidden = collect($this->help()->hiddenTopicsFor($viewer));
        $settings = $hidden->firstWhere('title', 'Settings & Configuration');

        $this->assertNotNull($settings,
            'A search for a feature the reader cannot reach must be able to explain itself');
        $this->assertContains('view settings', $settings['permissions']);
    }

    // ── the page and the PDF ───────────────────────────────────────────────

    public function test_the_page_renders_only_what_this_reader_may_see(): void
    {
        $tenant = $this->tenant();
        $this->reader($tenant, 'viewer');

        $this->get($this->tenantUrl('/tutorials'))
            ->assertOk()
            ->assertSee('Getting Started')
            ->assertDontSee('Settings &amp; Configuration', false)
            ->assertDontSee('API tokens');
    }

    public function test_two_roles_download_visibly_different_guides(): void
    {
        $tenant = $this->tenant();

        $this->reader($tenant, 'admin');
        $adminPdf = $this->get($this->tenantUrl('/tutorials/download'))
            ->assertOk()->getContent();

        $this->reader($tenant, 'viewer');
        $viewerPdf = $this->get($this->tenantUrl('/tutorials/download'))
            ->assertOk()->getContent();

        $this->assertNotSame(strlen($adminPdf), strlen($viewerPdf),
            'A downloadable manual covering everything undoes the scoping the '
            .'moment someone opens it — it must come from the same filtered content');
    }

    public function test_an_old_per_topic_link_lands_on_that_topic(): void
    {
        $tenant = $this->tenant();
        $this->reader($tenant, 'admin');

        $this->get($this->tenantUrl('/tutorials/sla-management'))
            ->assertRedirect(route('tutorials.index').'#topic-sla-management');
    }

    // ── the anchoring and routing contracts ────────────────────────────────

    public function test_every_tour_step_targets_an_anchor_that_exists_in_a_template(): void
    {
        $anchors = $this->anchorsInTemplates();
        $missing = [];

        foreach (config('help.topics', []) as $key => $topic) {
            foreach ($topic['tour']['steps'] ?? [] as $i => $step) {
                if (! preg_match('/^\[data-tour="([^"]+)"\]$/', $step['selector'], $m)) {
                    $missing[] = "{$key} step {$i}: selector must be a [data-tour=\"…\"] anchor";

                    continue;
                }

                if (! in_array($m[1], $anchors, true)) {
                    $missing[] = "{$key} step {$i}: no template contains data-tour=\"{$m[1]}\"";
                }
            }
        }

        $this->assertSame([], $missing, implode("\n", $missing));
    }

    public function test_every_tour_step_names_a_registered_route(): void
    {
        $bad = [];

        foreach (config('help.topics', []) as $key => $topic) {
            foreach ($topic['tour']['steps'] ?? [] as $i => $step) {
                if (! Route::has($step['route'])) {
                    $bad[] = "{$key} step {$i}: no route named {$step['route']}";
                }
            }
        }

        $this->assertSame([], $bad, implode("\n", $bad));
    }

    public function test_realistic_searches_reach_the_topic_that_answers_them(): void
    {
        $tenant = $this->tenant();
        $admin = $this->reader($tenant, 'admin');

        $topics = $this->help()->topicsFor($admin);
        $index = [];
        foreach ($topics as $key => $topic) {
            $index[$key] = strtolower($this->help()->searchTextFor($topic));
        }

        $expected = [
            'on hold' => 'managing-tickets',
            'merge duplicate' => 'managing-tickets',
            'reopen' => 'managing-tickets',
            'response time' => 'sla-management',
            'breach' => 'sla-management',
            'client tier' => 'sla-management',
            'timezone' => 'settings-configuration',
            'api token' => 'settings-configuration',
            'csv' => 'reports-analytics',
            'public portal' => 'client-management',
        ];

        foreach ($expected as $query => $topicKey) {
            $this->assertArrayHasKey($topicKey, $index, "no such topic {$topicKey}");
            $this->assertStringContainsString($query, $index[$topicKey],
                "a search for \"{$query}\" should reach the {$topicKey} topic");
        }
    }

    /**
     * @return list<string>
     */
    private function anchorsInTemplates(): array
    {
        $found = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            if (preg_match_all('/data-tour="([^"]+)"/', (string) file_get_contents($file->getPathname()), $m)) {
                $found = array_merge($found, $m[1]);
            }
        }

        return array_values(array_unique($found));
    }
}
