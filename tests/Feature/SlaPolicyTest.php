<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaCalendar;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlaPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function setupContext(string $planSlug = 'business'): array
    {
        $plan = Plan::factory()->create(['slug' => $planSlug, 'features' => PlanFeature::forPlan($planSlug)]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);
        app(TenantRoleService::class)->setupDefaultRoles($tenant);
        $user = User::factory()->create();
        $tenant->addUser($user, 'admin');

        $roleService = app(TenantRoleService::class);
        $roleService->setupDefaultRoles($tenant);
        $roleService->syncRole($user, 'admin', $tenant);

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);

        return [$tenant, $user];
    }

    public function test_starter_cannot_access(): void
    {
        $this->setupContext('starter');
        $this->get($this->tenantUrl('/sla'))->assertForbidden();
    }

    public function test_list_sla_policies(): void
    {
        $this->setupContext('business');
        $this->get($this->tenantUrl('/sla'))->assertOk();
    }

    public function test_seed_default_sla_policies(): void
    {
        [$tenant] = $this->setupContext('business');

        $this->post($this->tenantUrl('/sla/seed-defaults'))->assertRedirect();

        // Seeding creates one policy per (tier, priority) pair: 3 tiers x 4 priorities.
        $this->assertDatabaseCount('sla_policies', 12);

        // Spot-check standard defaults.
        $this->assertDatabaseHas('sla_policies', [
            'tenant_id' => $tenant->id,
            'client_tier' => 'enterprise',
            'priority' => 'critical',
            'response_time_hours' => 1,
            'resolution_time_hours' => 2,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('sla_policies', [
            'tenant_id' => $tenant->id,
            'client_tier' => 'basic',
            'priority' => 'low',
            'response_time_hours' => 48,
            'resolution_time_hours' => 72,
        ]);
    }

    public function test_update_tier_sla_policies(): void
    {
        [$tenant] = $this->setupContext('business');

        // Both clocks are required on every row — the form always posts them, and a
        // silently-defaulted mode is how a target ends up on a clock nobody chose.
        $this->post($this->tenantUrl('/sla/tier/premium'), [
            'rows' => [
                'low' => ['response' => 20, 'resolution' => 40, 'is_active' => '1', 'response_mode' => 'calendar', 'resolution_mode' => 'calendar'],
                'medium' => ['response' => 10, 'resolution' => 20, 'is_active' => '1', 'response_mode' => 'calendar', 'resolution_mode' => 'calendar'],
                'high' => ['response' => 5, 'resolution' => 10, 'is_active' => '1', 'response_mode' => 'calendar', 'resolution_mode' => 'calendar'],
                'critical' => ['response' => 2, 'resolution' => 5, 'is_active' => '0', 'response_mode' => 'calendar', 'resolution_mode' => 'calendar'],
            ],
        ])->assertRedirect($this->tenantUrl('/sla'));

        $this->assertDatabaseCount('sla_policies', 4);

        $this->assertDatabaseHas('sla_policies', [
            'tenant_id' => $tenant->id,
            'client_tier' => 'premium',
            'priority' => 'high',
            'response_time_hours' => 5,
            'resolution_time_hours' => 10,
            'name' => 'Premium - High',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('sla_policies', [
            'tenant_id' => $tenant->id,
            'client_tier' => 'premium',
            'priority' => 'critical',
            'is_active' => false,
        ]);
    }

    public function test_destroy_tier_sla_policies(): void
    {
        [$tenant] = $this->setupContext('business');

        $premiumPolicies = SlaPolicy::factory()->count(4)->sequence(
            ['priority' => 'low'],
            ['priority' => 'medium'],
            ['priority' => 'high'],
            ['priority' => 'critical'],
        )->create(['tenant_id' => $tenant->id, 'client_tier' => 'premium']);

        $basicPolicy = SlaPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'client_tier' => 'basic',
            'priority' => 'low',
        ]);

        $this->delete($this->tenantUrl('/sla/tier/premium'))->assertRedirect($this->tenantUrl('/sla'));

        $this->assertDatabaseMissing('sla_policies', [
            'tenant_id' => $tenant->id,
            'client_tier' => 'premium',
        ]);
        foreach ($premiumPolicies as $policy) {
            $this->assertDatabaseMissing('sla_policies', ['id' => $policy->id]);
        }

        $this->assertDatabaseHas('sla_policies', ['id' => $basicPolicy->id]);
    }

    public function test_can_create_single_sla_policy(): void
    {
        [$tenant] = $this->setupContext('business');

        $response = $this->post($this->tenantUrl('/sla'), [
            'name' => 'Enterprise SLA Ultra',
            'description' => 'Ultra high priority policy',
            'client_tier' => 'enterprise',
            'priority' => 'critical',
            'response_time_hours' => 1,
            'resolution_time_hours' => 4,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sla_policies', [
            'tenant_id' => $tenant->id,
            'name' => 'Enterprise SLA Ultra',
            'client_tier' => 'enterprise',
            'priority' => 'critical',
            'response_time_hours' => 1,
            'resolution_time_hours' => 4,
            'is_active' => true,
        ]);
    }

    public function test_validates_response_time_less_than_resolution_time(): void
    {
        $this->setupContext('business');

        $response = $this->post($this->tenantUrl('/sla'), [
            'name' => 'Invalid Target SLA',
            'client_tier' => 'premium',
            'priority' => 'high',
            'response_time_hours' => 10,
            'resolution_time_hours' => 5, // Invalid: response > resolution
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors(['response_time_hours']);
    }

    public function test_prevents_duplicate_tier_and_priority_combination_without_overwrite(): void
    {
        [$tenant] = $this->setupContext('business');

        SlaPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Existing Basic Low',
            'client_tier' => 'basic',
            'priority' => 'low',
        ]);

        $response = $this->post($this->tenantUrl('/sla'), [
            'name' => 'Duplicate Policy',
            'client_tier' => 'basic',
            'priority' => 'low',
            'response_time_hours' => 12,
            'resolution_time_hours' => 24,
            'overwrite' => false,
        ]);

        $response->assertSessionHas('duplicate_warning');
    }

    public function test_can_update_sla_policy(): void
    {
        [$tenant] = $this->setupContext('business');

        $policy = SlaPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Old Policy Name',
            'client_tier' => 'basic',
            'priority' => 'medium',
            'response_time_hours' => 12,
            'resolution_time_hours' => 24,
        ]);

        $response = $this->put($this->tenantUrl('/sla/'.$policy->id), [
            'name' => 'New Updated Policy Name',
            'client_tier' => 'basic',
            'priority' => 'medium',
            'response_time_hours' => 8,
            'resolution_time_hours' => 16,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sla_policies', [
            'id' => $policy->id,
            'name' => 'New Updated Policy Name',
            'response_time_hours' => 8,
            'resolution_time_hours' => 16,
        ]);
    }

    public function test_can_toggle_sla_policy_active_status(): void
    {
        [$tenant] = $this->setupContext('business');

        $policy = SlaPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $this->post($this->tenantUrl('/sla/'.$policy->id.'/toggle'))->assertRedirect();

        $this->assertDatabaseHas('sla_policies', [
            'id' => $policy->id,
            'is_active' => false,
        ]);
    }

    public function test_prevents_deleting_sla_policy_assigned_to_tickets(): void
    {
        [$tenant] = $this->setupContext('business');

        $policy = SlaPolicy::factory()->create(['tenant_id' => $tenant->id]);
        Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'sla_policy_id' => $policy->id,
        ]);

        $response = $this->delete($this->tenantUrl('/sla/'.$policy->id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('sla_policies', ['id' => $policy->id]);
    }

    public function test_can_delete_unused_sla_policy(): void
    {
        [$tenant] = $this->setupContext('business');

        $policy = SlaPolicy::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->delete($this->tenantUrl('/sla/'.$policy->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('sla_policies', ['id' => $policy->id]);
    }

    public function test_can_bulk_action_sla_policies(): void
    {
        [$tenant] = $this->setupContext('business');

        $p1 = SlaPolicy::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $p2 = SlaPolicy::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

        $response = $this->post($this->tenantUrl('/sla/bulk-action'), [
            'action' => 'deactivate',
            'ids' => [$p1->id, $p2->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sla_policies', ['id' => $p1->id, 'is_active' => false]);
        $this->assertDatabaseHas('sla_policies', ['id' => $p2->id, 'is_active' => false]);
    }

    public function test_can_export_sla_policies_csv(): void
    {
        $this->setupContext('business');

        $response = $this->get($this->tenantUrl('/sla/export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_sidebar_navigation_renders_for_authorized_users(): void
    {
        $this->setupContext('business');

        $response = $this->get($this->tenantUrl('/sla'));

        $response->assertOk();
        $response->assertSee('SLA Policies');
        $response->assertSee(route('sla.index'));
    }

    public function test_sidebar_navigation_hidden_for_unauthorized_users(): void
    {
        $this->setupContext('starter');

        $response = $this->get($this->tenantUrl('/dashboard'));

        $response->assertOk();
        $response->assertDontSee('SLA Policies');
    }

    public function test_admin_sla_policies_route_accessible_by_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get(route('admin.sla.index'));

        $response->assertOk();
        $response->assertSee('SLA Policies');
    }

    public function test_admin_sla_policies_route_protected_by_auth_and_admin(): void
    {
        $this->get(route('admin.sla.index'))->assertRedirect(route('login'));

        $nonAdmin = User::factory()->create(['is_admin' => false]);
        $this->actingAs($nonAdmin)->get(route('admin.sla.index'))->assertForbidden();
    }

    public function test_tier_editor_states_targets_are_calendar_hours(): void
    {
        $this->setupContext('business');

        $response = $this->get($this->tenantUrl('/sla/tier/basic/edit'));

        $response->assertOk();
        // The number must never be typed without its unit. The unit moved from the column
        // header onto a per-target control when the clock became choosable, but an admin
        // entering "48" must still be able to read what 48 means without leaving the page.
        $response->assertSee('calendar hours');
        $response->assertSee('Calendar hours are elapsed clock time', false);
    }

    private function nineToFiveCalendar(Tenant $tenant): SlaCalendar
    {
        return SlaCalendar::factory()->nineToFive()->create([
            'tenant_id' => $tenant->id,
            'timezone' => 'UTC',
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function tierPayload(string $responseMode = 'calendar', string $resolutionMode = 'calendar'): array
    {
        $rows = [];
        foreach (SlaPolicy::PRIORITIES as $priority) {
            $rows[$priority] = [
                'response' => 4,
                'resolution' => 8,
                'response_mode' => $responseMode,
                'resolution_mode' => $resolutionMode,
                'is_active' => 1,
            ];
        }

        return $rows;
    }

    public function test_tier_editor_offers_a_mode_per_clock(): void
    {
        [$tenant] = $this->setupContext('business');
        $this->nineToFiveCalendar($tenant);

        $response = $this->get($this->tenantUrl('/sla/tier/basic/edit'));

        $response->assertOk();

        // Two selects per priority row, four rows: per clock, not per policy. A per-row
        // control cannot express "respond on the wall clock, resolve on the working one",
        // which is the sentence this whole feature exists for.
        foreach (SlaPolicy::PRIORITIES as $priority) {
            $response->assertSee('rows['.$priority.'][response_mode]', false);
            $response->assertSee('rows['.$priority.'][resolution_mode]', false);
        }

        $this->assertSame(8, substr_count($response->getContent(), '_mode]"'));

        // Every one defaults to calendar: nothing drifts onto business hours by accident.
        $this->assertSame(8, substr_count($response->getContent(), '<option value="calendar" selected>'));
    }

    public function test_business_hours_option_is_disabled_when_no_calendar_exists(): void
    {
        $this->setupContext('business');

        $response = $this->get($this->tenantUrl('/sla/tier/basic/edit'));

        $response->assertOk();
        // The state every workspace is in today, so it must explain itself rather than
        // just greying a control out.
        $response->assertSee('<option value="business" disabled', false);
        $response->assertSee('Business hours are unavailable until you set up a business-hours calendar.');
        $response->assertSee($this->tenantUrl('/sla/calendar'), false);
    }

    public function test_update_tier_rejects_business_hours_without_a_usable_calendar(): void
    {
        $this->setupContext('business');

        // A disabled option is a hint to a browser, not a guard. The POST is the guard.
        $response = $this->post($this->tenantUrl('/sla/tier/basic'), [
            'rows' => $this->tierPayload('calendar', 'business'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('rows');
        $this->assertDatabaseCount('sla_policies', 0);
    }

    public function test_update_tier_rejects_an_unknown_mode(): void
    {
        [$tenant] = $this->setupContext('business');
        $this->nineToFiveCalendar($tenant);

        $response = $this->post($this->tenantUrl('/sla/tier/basic'), [
            'rows' => $this->tierPayload('elapsed', 'calendar'),
        ]);

        $response->assertSessionHasErrors('rows.low.response_mode');
        $this->assertDatabaseCount('sla_policies', 0);
    }

    public function test_update_tier_persists_both_modes(): void
    {
        [$tenant] = $this->setupContext('business');
        $this->nineToFiveCalendar($tenant);

        $this->post($this->tenantUrl('/sla/tier/basic'), [
            'rows' => $this->tierPayload('calendar', 'business'),
        ])->assertRedirect();

        $this->assertDatabaseHas('sla_policies', [
            'tenant_id' => $tenant->id,
            'client_tier' => 'basic',
            'priority' => 'critical',
            'response_hours_mode' => 'calendar',
            'resolution_hours_mode' => 'business',
        ]);
    }

    public function test_enterprise_tier_warns_but_still_saves_business_hours(): void
    {
        [$tenant] = $this->setupContext('business');
        $this->nineToFiveCalendar($tenant);

        $response = $this->post($this->tenantUrl('/sla/tier/enterprise'), [
            'rows' => $this->tierPayload('business', 'business'),
        ]);

        // Warn, do not block: encoding pricing policy in a validator ages badly.
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('warning');
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('sla_policies', [
            'tenant_id' => $tenant->id,
            'client_tier' => 'enterprise',
            'priority' => 'critical',
            'response_hours_mode' => 'business',
        ]);
    }

    public function test_enterprise_tier_does_not_warn_when_every_clock_stays_calendar(): void
    {
        [$tenant] = $this->setupContext('business');
        $this->nineToFiveCalendar($tenant);

        $this->post($this->tenantUrl('/sla/tier/enterprise'), [
            'rows' => $this->tierPayload('calendar', 'calendar'),
        ])->assertSessionMissing('warning');
    }

    public function test_sla_index_explains_the_clock(): void
    {
        $this->setupContext('business');

        $response = $this->get($this->tenantUrl('/sla'));

        $response->assertOk();
        $response->assertSee('calendar hours');
        $response->assertSee('nights, weekends and holidays', false);
    }
}
