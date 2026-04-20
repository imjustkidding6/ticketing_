<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentEscalationTest extends TestCase
{
    use RefreshDatabase;

    private function createEnterpriseTenant(): Tenant
    {
        $plan = Plan::factory()->create([
            'slug' => 'enterprise',
            'features' => PlanFeature::forPlan('enterprise'),
        ]);
        $license = License::factory()->active()->forPlan($plan)->create();

        return Tenant::factory()->create(['license_id' => $license->id]);
    }

    private function createStarterTenant(): Tenant
    {
        $plan = Plan::factory()->start()->create([
            'features' => PlanFeature::forPlan('start'),
        ]);
        $license = License::factory()->active()->forPlan($plan)->create();

        return Tenant::factory()->create(['license_id' => $license->id]);
    }

    private function setupTenantContext(Tenant $tenant): User
    {
        $user = User::factory()->create();
        $tenant->addUser($user, 'member');

        $this->actingAs($user)
            ->withTenant($tenant)
            ->withSession(['current_tenant_id' => $tenant->id]);

        return $user;
    }

    public function test_escalation_returns_403_for_starter_plan(): void
    {
        $tenant = $this->createStarterTenant();
        $user = $this->setupTenantContext($tenant);

        $ticket = Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
        ]);

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/escalate"), [
            'to_tier' => 'tier_2',
        ])->assertForbidden();
    }

    public function test_can_escalate_ticket(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $user = $this->setupTenantContext($tenant);

        $ticket = Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
            'current_tier' => 'tier_1',
        ]);

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/escalate"), [
            'to_tier' => 'tier_2',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertEquals('tier_2', $ticket->current_tier);

        $this->assertDatabaseHas('ticket_escalations', [
            'ticket_id' => $ticket->id,
            'to_tier' => 'tier_2',
        ]);
    }

    public function test_escalation_with_agent_reassignment(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $user = $this->setupTenantContext($tenant);

        $agent = User::factory()->create();
        $tenant->addUser($agent, 'member');

        $ticket = Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/escalate"), [
            'to_tier' => 'tier_3',
            'assigned_to' => $agent->id,
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertEquals('tier_3', $ticket->current_tier);
        $this->assertEquals($agent->id, $ticket->assigned_to);
    }

    public function test_escalation_without_agent_keeps_current(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $user = $this->setupTenantContext($tenant);

        $ticket = Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/escalate"), [
            'to_tier' => 'tier_2',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertEquals($user->id, $ticket->assigned_to);
    }

    public function test_escalation_requires_valid_tier(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $user = $this->setupTenantContext($tenant);

        $ticket = Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
        ]);

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/escalate"), [
            'to_tier' => 'tier_99',
        ])->assertSessionHasErrors('to_tier');
    }
}
