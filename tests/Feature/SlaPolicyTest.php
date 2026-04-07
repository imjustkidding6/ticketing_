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

    public function test_create_sla_policy(): void
    {
        [$tenant] = $this->setupContext('business');

        $this->post($this->tenantUrl('/sla'), [
            'name' => 'Premium SLA',
            'client_tier' => 'premium',
            'priority' => 'high',
            'response_time_hours' => 4,
            'resolution_time_hours' => 24,
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('sla_policies', ['name' => 'Premium SLA', 'tenant_id' => $tenant->id]);
    }

    public function test_update_sla_policy(): void
    {
        [$tenant] = $this->setupContext('business');
        $sla = SlaPolicy::factory()->create(['tenant_id' => $tenant->id]);

        $this->put($this->tenantUrl("/sla/{$sla->id}"), [
            'name' => 'Updated SLA',
            'client_tier' => 'basic',
            'priority' => 'low',
            'response_time_hours' => 8,
            'resolution_time_hours' => 48,
            'is_active' => true,
        ])->assertRedirect();

        $sla->refresh();
        $this->assertEquals('Updated SLA', $sla->name);
    }

    public function test_delete_sla_policy(): void
    {
        [$tenant] = $this->setupContext('business');
        $sla = SlaPolicy::factory()->create(['tenant_id' => $tenant->id]);

        $this->delete($this->tenantUrl("/sla/{$sla->id}"))->assertRedirect();

        $this->assertDatabaseMissing('sla_policies', ['id' => $sla->id]);
    }
}
