<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\License;
use App\Models\Plan;
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

        $this->post($this->tenantUrl('/sla/tier/premium'), [
            'rows' => [
                'low' => ['response' => 20, 'resolution' => 40, 'is_active' => '1'],
                'medium' => ['response' => 10, 'resolution' => 20, 'is_active' => '1'],
                'high' => ['response' => 5, 'resolution' => 10, 'is_active' => '1'],
                'critical' => ['response' => 2, 'resolution' => 5, 'is_active' => '0'],
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
}
