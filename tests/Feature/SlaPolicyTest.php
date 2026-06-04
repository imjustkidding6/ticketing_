<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\User;
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
        $user = User::factory()->create();
        $tenant->addUser($user, 'admin');

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);

        return [$tenant, $user];
    }

    public function test_starter_cannot_access(): void
    {
        $this->setupContext('start');
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

        // Spot-check a couple of the standard defaults landed correctly.
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

        // updateTier upserts all 4 priority rows for the tier.
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

        // is_active honors the submitted value (critical was unchecked).
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

        // All premium-tier policies are gone.
        $this->assertDatabaseMissing('sla_policies', [
            'tenant_id' => $tenant->id,
            'client_tier' => 'premium',
        ]);
        foreach ($premiumPolicies as $policy) {
            $this->assertDatabaseMissing('sla_policies', ['id' => $policy->id]);
        }

        // Other tiers are untouched.
        $this->assertDatabaseHas('sla_policies', ['id' => $basicPolicy->id]);
    }
}
