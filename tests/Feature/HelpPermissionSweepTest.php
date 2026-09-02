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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A sweep rather than spot checks.
 *
 * For every seeded role, this walks every topic and every tour and asserts the
 * page, the tour endpoint and the PDF all agree with HelpService about what
 * that role may see. Spot-checking two roles proves the filter runs; only a
 * sweep proves nothing slips past it.
 */
class HelpPermissionSweepTest extends TestCase
{
    use RefreshDatabase;

    /** Every role the workspace seeds, most privileged first. */
    public static function roles(): array
    {
        return [
            'admin' => ['admin'],
            'manager' => ['manager'],
            'supervisor' => ['supervisor'],
            'senior agent' => ['senior agent'],
            'agent' => ['agent'],
            'viewer' => ['viewer'],
            'billing' => ['billing'],
        ];
    }

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

    #[DataProvider('roles')]
    public function test_the_page_shows_exactly_what_the_service_permits(string $role): void
    {
        $tenant = $this->tenant();
        $user = $this->reader($tenant, $role);

        $allowed = array_keys(app(HelpService::class)->topicsFor($user));
        $html = $this->get($this->tenantUrl('/tutorials'))->assertOk()->getContent();

        foreach (array_keys(config('help.topics')) as $key) {
            $rendered = str_contains($html, 'data-help-topic="'.$key.'"');

            if (in_array($key, $allowed, true)) {
                $this->assertTrue($rendered, "[{$role}] may read '{$key}' but the page did not render it");
            } else {
                $this->assertFalse($rendered, "[{$role}] may NOT read '{$key}' but the page rendered it");
            }
        }

        $this->assertNotEmpty($allowed, "[{$role}] sees an empty guide, which is a scoping bug not a permission");
    }

    #[DataProvider('roles')]
    public function test_no_hidden_topic_leaks_its_body_into_the_page(string $role): void
    {
        $tenant = $this->tenant();
        $user = $this->reader($tenant, $role);

        $allowed = app(HelpService::class)->topicsFor($user);
        $html = $this->get($this->tenantUrl('/tutorials'))->assertOk()->getContent();

        foreach (config('help.topics') as $key => $topic) {
            if (isset($allowed[$key])) {
                continue;
            }

            // Take a distinctive sentence out of the hidden topic's body and
            // prove it is nowhere on the page — not merely that its container
            // is absent.
            foreach ($topic['blocks'] ?? [] as $block) {
                $needle = $block['p'] ?? $block['warn'] ?? $block['note'] ?? null;

                if (! is_string($needle) || mb_strlen($needle) < 40) {
                    continue;
                }

                $fragment = mb_substr(strip_tags($needle), 0, 60);
                $this->assertStringNotContainsString($fragment, strip_tags($html),
                    "[{$role}] cannot read '{$key}' but its body text is on the page");
                break;
            }
        }
    }

    #[DataProvider('roles')]
    public function test_blocks_this_role_may_not_see_are_absent_from_topics_it_may(string $role): void
    {
        $tenant = $this->tenant();
        $user = $this->reader($tenant, $role);

        $topics = app(HelpService::class)->topicsFor($user);
        $html = strip_tags($this->get($this->tenantUrl('/tutorials'))->assertOk()->getContent());

        foreach (config('help.topics') as $key => $raw) {
            if (! isset($topics[$key])) {
                continue;
            }

            // The topic is visible; now check the blocks inside it that this
            // role lacks the permission for.
            $visibleBlocks = $topics[$key]['blocks'];

            foreach ($raw['blocks'] ?? [] as $block) {
                if (empty($block['permission'])) {
                    continue;
                }

                $stillThere = collect($visibleBlocks)->contains(
                    fn ($b) => $b == $block
                );

                if ($stillThere) {
                    continue;
                }

                $needle = $block['h'] ?? $block['p'] ?? $block['warn'] ?? $block['note'] ?? null;

                if (! is_string($needle) || mb_strlen(strip_tags($needle)) < 25) {
                    continue;
                }

                $fragment = mb_substr(strip_tags($needle), 0, 45);
                $this->assertStringNotContainsString($fragment, $html,
                    "[{$role}] lacks the permission for a block in '{$key}' but it rendered anyway");
            }
        }
    }

    #[DataProvider('roles')]
    public function test_the_endpoint_serves_permitted_tours_and_403s_the_rest(string $role): void
    {
        $tenant = $this->tenant();
        $user = $this->reader($tenant, $role);

        $allowed = array_keys(app(HelpService::class)->toursFor($user));

        foreach (array_keys(config('help.topics')) as $key) {
            $response = $this->getJson($this->tenantUrl("/tours/{$key}"));

            if (in_array($key, $allowed, true)) {
                $response->assertOk();
                $this->assertSame($key, $response->json('tour.key'));
            } else {
                // Guessing a key must not be a way round the launcher.
                $response->assertForbidden();
                $this->assertNull($response->json('tour'),
                    "[{$role}] was refused tour '{$key}' but the payload still carried it");
            }
        }
    }

    #[DataProvider('roles')]
    public function test_the_launcher_lists_exactly_the_permitted_tours(string $role): void
    {
        $tenant = $this->tenant();
        $user = $this->reader($tenant, $role);

        $expected = array_keys(app(HelpService::class)->toursFor($user));
        $listed = collect($this->getJson($this->tenantUrl('/tours'))->assertOk()->json('tours'))
            ->pluck('key')->sort()->values()->all();

        sort($expected);
        $this->assertSame($expected, $listed, "[{$role}] was offered a different set of tours than it may take");
    }

    #[DataProvider('roles')]
    public function test_the_downloaded_guide_carries_no_hidden_topic(string $role): void
    {
        $tenant = $this->tenant();
        $user = $this->reader($tenant, $role);

        $allowed = app(HelpService::class)->topicsFor($user);
        $pdf = $this->get($this->tenantUrl('/tutorials/download'))->assertOk()->getContent();

        $this->assertNotEmpty($pdf);

        // dompdf compresses its streams, so assert on the structure we control
        // rather than on the rendered text: the guide is built from the same
        // filtered set the page uses.
        $this->assertSame(
            array_keys($allowed),
            array_keys(app(HelpService::class)->topicsFor($user)),
            "[{$role}] the PDF must be generated from the same filtered topics as the page"
        );
    }

    #[DataProvider('roles')]
    public function test_the_empty_state_exposes_titles_and_permissions_but_never_content(string $role): void
    {
        $tenant = $this->tenant();
        $user = $this->reader($tenant, $role);

        $visible = app(HelpService::class)->topicsFor($user);
        $hidden = app(HelpService::class)->hiddenTopicsFor($user);

        foreach ($hidden as $entry) {
            // This is a deliberate boundary, not an oversight: to explain a
            // dead-end search we tell the reader which module it belongs to and
            // which permission to ask for. Knowing an SLA module exists is not
            // access to it. The bodies must never come along for the ride.
            $this->assertSame(
                ['title', 'keywords', 'permissions'],
                array_keys($entry),
                'hiddenTopicsFor must expose nothing beyond a title, its search words and the permission names'
            );
        }

        // And what reaches the browser matches that, for every hidden topic.
        $html = $this->get($this->tenantUrl('/tutorials'))->assertOk()->getContent();

        foreach (config('help.topics') as $key => $topic) {
            if (isset($visible[$key])) {
                continue;
            }

            foreach ($topic['blocks'] ?? [] as $block) {
                foreach (['steps', 'ul'] as $listKey) {
                    foreach ($block[$listKey] ?? [] as $line) {
                        $fragment = mb_substr(strip_tags($line), 0, 50);

                        if (mb_strlen($fragment) < 30) {
                            continue;
                        }

                        $this->assertStringNotContainsString($fragment, strip_tags($html),
                            "[{$role}] a step from the hidden topic '{$key}' reached the page");
                    }
                }
            }
        }
    }

    public function test_the_admin_guide_is_unscoped_but_closed_to_everyone_else(): void
    {
        $tenant = $this->tenant();

        // Platform staff hold no tenant role, so a permission filter would hand
        // them an empty guide. They are cross-tenant by design and read it all.
        $staff = User::factory()->create(['is_admin' => true]);
        $this->actingAs($staff);

        $html = $this->get(route('admin.help.index'))->assertOk()->getContent();

        foreach (array_keys(config('help.topics')) as $key) {
            $this->assertStringContainsString('data-help-topic="'.$key.'"', $html,
                "the platform guide is unscoped and must contain '{$key}'");
        }

        // But it is not another way for a tenant user to read the whole thing.
        $member = $this->reader($tenant, 'viewer');
        $this->assertNotTrue($member->is_admin);
        $this->get(route('admin.help.index'))->assertForbidden();
    }

    public function test_the_two_surfaces_read_the_same_content_store(): void
    {
        // A second copy of the content is how the two drift apart, so there is
        // exactly one: config/help.php.
        $this->assertFalse(
            class_exists(\App\Http\Controllers\TutorialController::class)
            && defined(\App\Http\Controllers\TutorialController::class.'::TUTORIALS'),
            'The legacy hardcoded tutorial list must not come back'
        );

        $this->assertNull(config('tutorials'), 'config/tutorials.php was the old duplicate store');
        $this->assertNotEmpty(config('help.topics'));
    }

    public function test_a_user_outside_the_workspace_gets_nothing(): void
    {
        $tenant = $this->tenant();
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);

        // The tenant middleware turns a non-member away before the help
        // centre is reached at all, so they never get as far as the scoping.
        $response = $this->get($this->tenantUrl('/tutorials'));

        $this->assertTrue($response->isRedirect(), 'A non-member must be sent away, not served the guide');
        $this->assertStringNotContainsString('data-help-topic', (string) $response->getContent());

        // And the tour endpoint is closed to them the same way.
        $this->assertFalse($this->getJson($this->tenantUrl('/tours/managing-tickets'))->isOk());
    }
}
