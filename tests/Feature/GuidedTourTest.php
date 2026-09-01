<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuidedTourTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOnPlan(string $slug = 'enterprise'): Tenant
    {
        $plan = Plan::where('slug', $slug)->first()
            ?? Plan::factory()->create(['slug' => $slug, 'features' => PlanFeature::forPlan($slug)]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);
        SlaPolicy::factory()->create([
            'tenant_id' => $tenant->id, 'priority' => null,
            'client_tier' => null, 'is_active' => true,
        ]);

        return $tenant;
    }

    private function actor(Tenant $tenant, string $role): User
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

    // ── the endpoint ───────────────────────────────────────────────────────

    public function test_a_tour_is_served_to_a_user_who_can_complete_it(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant, 'admin');

        $this->getJson($this->tenantUrl('/tours/create-ticket'))
            ->assertOk()
            ->assertJsonPath('tour.key', 'create-ticket')
            ->assertJsonStructure(['tour' => ['key', 'title', 'minutes', 'steps']]);
    }

    public function test_the_endpoint_returns_403_not_an_empty_tour(): void
    {
        $tenant = $this->tenantOnPlan();
        // A viewer holds view-only permissions; they cannot create a ticket, so
        // being taught how to is a dead end.
        $this->actor($tenant, 'viewer');

        $this->getJson($this->tenantUrl('/tours/create-ticket'))
            ->assertForbidden()
            ->assertJsonMissingPath('tour');
    }

    public function test_an_unknown_tour_key_is_a_404(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant, 'admin');

        $this->getJson($this->tenantUrl('/tours/no-such-tour'))->assertNotFound();
    }

    public function test_the_launcher_only_lists_tours_the_user_can_take(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant, 'admin');
        $adminKeys = collect($this->getJson($this->tenantUrl('/tours'))->json('tours'))->pluck('key');

        $this->assertContains('create-ticket', $adminKeys);

        $this->actor($tenant, 'viewer');
        $viewerKeys = collect($this->getJson($this->tenantUrl('/tours'))->json('tours'))->pluck('key');

        $this->assertNotContains('create-ticket', $viewerKeys,
            'A viewer cannot create tickets, so must not be offered the tour that teaches it');
    }

    public function test_a_plan_gated_tour_is_hidden_without_the_feature(): void
    {
        // ticket_merging is Enterprise-only.
        $business = $this->tenantOnPlan('business');
        $this->actor($business, 'admin');

        $keys = collect($this->getJson($this->tenantUrl('/tours'))->json('tours'))->pluck('key');
        $this->assertNotContains('merge-duplicates', $keys);

        $this->getJson($this->tenantUrl('/tours/merge-duplicates'))->assertForbidden();
    }

    public function test_a_tour_can_be_started_twice_in_a_row(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant, 'admin');

        // B1: no first-run flag, nothing to reset.
        $this->getJson($this->tenantUrl('/tours/create-ticket?start=1'))->assertOk();
        $this->getJson($this->tenantUrl('/tours/create-ticket?start=1'))->assertOk();
    }

    // ── the anchoring contract ─────────────────────────────────────────────

    /**
     * The contract that makes tours survive a redesign: every step targets a
     * data-tour attribute that some template actually contains. Renaming an
     * anchor now fails the build instead of breaking a tour silently for a year.
     */
    public function test_every_step_targets_an_anchor_that_exists_in_a_template(): void
    {
        $anchors = $this->anchorsInTemplates();
        $missing = [];

        foreach (config('tours', []) as $key => $tour) {
            foreach ($tour['steps'] as $i => $step) {
                if (! preg_match('/^\[data-tour="([^"]+)"\]$/', $step['selector'], $m)) {
                    $missing[] = "{$key} step {$i}: selector must be a [data-tour=\"…\"] anchor, got {$step['selector']}";

                    continue;
                }

                if (! in_array($m[1], $anchors, true)) {
                    $missing[] = "{$key} step {$i}: no template contains data-tour=\"{$m[1]}\"";
                }
            }
        }

        $this->assertSame([], $missing, implode("\n", $missing));
    }

    public function test_every_step_declares_a_route_that_exists(): void
    {
        $bad = [];

        foreach (config('tours', []) as $key => $tour) {
            foreach ($tour['steps'] as $i => $step) {
                $name = rtrim($step['page'], '*');

                $exists = collect(app('router')->getRoutes()->getRoutesByName())
                    ->keys()
                    ->contains(fn (string $r) => str_ends_with($step['page'], '*') ? str_starts_with($r, $name) : $r === $name);

                if (! $exists) {
                    $bad[] = "{$key} step {$i}: no route named {$step['page']}";
                }
            }
        }

        $this->assertSame([], $bad, implode("\n", $bad));
    }

    public function test_tour_definitions_are_well_formed(): void
    {
        foreach (config('tours', []) as $key => $tour) {
            $this->assertSame($key, $tour['key'], 'The array key and the tour key must match — the key is what resumes.');
            $this->assertNotEmpty($tour['title']);
            $this->assertIsInt($tour['minutes'], 'An honest estimate has to be shown before the user commits.');

            $count = count($tour['steps']);
            $this->assertGreaterThanOrEqual(2, $count, "{$key}: a one-step tour is a tooltip.");
            $this->assertLessThanOrEqual(7, $count, "{$key}: longer than seven steps and it is really two tours.");

            foreach ($tour['steps'] as $i => $step) {
                $this->assertContains($step['advance'], ['next', 'click'], "{$key} step {$i}");
                $this->assertNotEmpty($step['title'], "{$key} step {$i}");
                $this->assertNotEmpty($step['body'], "{$key} step {$i}");
            }
        }
    }

    /**
     * @return list<string>
     */
    private function anchorsInTemplates(): array
    {
        $found = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

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
