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

    public function test_create_page_loads(): void
    {
        $this->setupContext('business');
        $this->get($this->tenantUrl('/sla/create'))->assertOk()->assertSee('Priority');
    }

    public function test_batch_create_sla_policies(): void
    {
        [$tenant] = $this->setupContext('business');

        $this->post($this->tenantUrl('/sla'), [
            'name' => 'Standard SLA',
            'client_tier' => 'premium',
            'priorities' => [
                'critical' => ['enabled' => 1, 'response_time_hours' => 1, 'resolution_time_hours' => 4],
                'high' => ['enabled' => 1, 'response_time_hours' => 4, 'resolution_time_hours' => 8],
                'medium' => ['enabled' => 1, 'response_time_hours' => 8, 'resolution_time_hours' => 24],
                'low' => ['enabled' => 1, 'response_time_hours' => 24, 'resolution_time_hours' => 72],
            ],
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseCount('sla_policies', 4);
        $this->assertDatabaseHas('sla_policies', [
            'name' => 'Standard SLA - Critical',
            'tenant_id' => $tenant->id,
            'client_tier' => 'premium',
            'priority' => 'critical',
            'response_time_hours' => 1,
            'resolution_time_hours' => 4,
        ]);
        $this->assertDatabaseHas('sla_policies', [
            'name' => 'Standard SLA - Low',
            'tenant_id' => $tenant->id,
            'priority' => 'low',
            'response_time_hours' => 24,
            'resolution_time_hours' => 72,
        ]);
    }

    public function test_batch_create_with_partial_priorities(): void
    {
        [$tenant] = $this->setupContext('business');

        $this->post($this->tenantUrl('/sla'), [
            'name' => 'Partial SLA',
            'priorities' => [
                'critical' => ['enabled' => 1, 'response_time_hours' => 1, 'resolution_time_hours' => 4],
                'high' => ['enabled' => 0, 'response_time_hours' => 4, 'resolution_time_hours' => 8],
                'medium' => ['enabled' => 0, 'response_time_hours' => 8, 'resolution_time_hours' => 24],
                'low' => ['enabled' => 1, 'response_time_hours' => 24, 'resolution_time_hours' => 72],
            ],
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseCount('sla_policies', 2);
        $this->assertDatabaseHas('sla_policies', ['priority' => 'critical', 'tenant_id' => $tenant->id]);
        $this->assertDatabaseHas('sla_policies', ['priority' => 'low', 'tenant_id' => $tenant->id]);
        $this->assertDatabaseMissing('sla_policies', ['priority' => 'high', 'tenant_id' => $tenant->id]);
    }

    public function test_batch_create_fails_when_no_priorities_enabled(): void
    {
        $this->setupContext('business');

        $this->post($this->tenantUrl('/sla'), [
            'name' => 'Empty SLA',
            'priorities' => [
                'critical' => ['enabled' => 0, 'response_time_hours' => 1, 'resolution_time_hours' => 4],
                'high' => ['enabled' => 0, 'response_time_hours' => 4, 'resolution_time_hours' => 8],
                'medium' => ['enabled' => 0, 'response_time_hours' => 8, 'resolution_time_hours' => 24],
                'low' => ['enabled' => 0, 'response_time_hours' => 24, 'resolution_time_hours' => 72],
            ],
        ])->assertSessionHasErrors('priorities');

        $this->assertDatabaseCount('sla_policies', 0);
    }

    public function test_batch_create_with_any_tier(): void
    {
        [$tenant] = $this->setupContext('business');

        $this->post($this->tenantUrl('/sla'), [
            'name' => 'Default SLA',
            'client_tier' => '',
            'priorities' => [
                'critical' => ['enabled' => 1, 'response_time_hours' => 2, 'resolution_time_hours' => 8],
                'high' => ['enabled' => 0, 'response_time_hours' => 4, 'resolution_time_hours' => 8],
                'medium' => ['enabled' => 0, 'response_time_hours' => 8, 'resolution_time_hours' => 24],
                'low' => ['enabled' => 0, 'response_time_hours' => 24, 'resolution_time_hours' => 72],
            ],
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('sla_policies', [
            'tenant_id' => $tenant->id,
            'client_tier' => null,
            'priority' => 'critical',
            'response_time_hours' => 2,
        ]);
    }

    public function test_batch_create_updates_existing_duplicate(): void
    {
        [$tenant] = $this->setupContext('business');

        SlaPolicy::create([
            'tenant_id' => $tenant->id,
            'name' => 'Old SLA - Critical',
            'client_tier' => 'premium',
            'priority' => 'critical',
            'response_time_hours' => 10,
            'resolution_time_hours' => 48,
            'is_active' => true,
        ]);

        $this->post($this->tenantUrl('/sla'), [
            'name' => 'Updated SLA',
            'client_tier' => 'premium',
            'priorities' => [
                'critical' => ['enabled' => 1, 'response_time_hours' => 1, 'resolution_time_hours' => 4],
                'high' => ['enabled' => 0, 'response_time_hours' => 4, 'resolution_time_hours' => 8],
                'medium' => ['enabled' => 0, 'response_time_hours' => 8, 'resolution_time_hours' => 24],
                'low' => ['enabled' => 0, 'response_time_hours' => 24, 'resolution_time_hours' => 72],
            ],
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseCount('sla_policies', 1);
        $this->assertDatabaseHas('sla_policies', [
            'tenant_id' => $tenant->id,
            'name' => 'Updated SLA - Critical',
            'response_time_hours' => 1,
            'resolution_time_hours' => 4,
        ]);
    }

    public function test_update_sla_policy(): void
    {
        [$tenant] = $this->setupContext('business');
        $sla = SlaPolicy::create([
            'tenant_id' => $tenant->id,
            'name' => 'Test SLA',
            'priority' => 'low',
            'response_time_hours' => 4,
            'resolution_time_hours' => 24,
            'is_active' => true,
        ]);

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
        $sla = SlaPolicy::create([
            'tenant_id' => $tenant->id,
            'name' => 'Delete Me',
            'priority' => 'high',
            'response_time_hours' => 4,
            'resolution_time_hours' => 24,
            'is_active' => true,
        ]);

        $this->delete($this->tenantUrl("/sla/{$sla->id}"))->assertRedirect();

        $this->assertDatabaseMissing('sla_policies', ['id' => $sla->id]);
    }
}
