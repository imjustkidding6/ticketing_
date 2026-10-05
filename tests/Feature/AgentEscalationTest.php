<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\EscalationService;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentEscalationTest extends TestCase
{
    use RefreshDatabase;

    private function createEnterpriseTenant(): Tenant
    {
        // Reused across tenants in one test: `plans.slug` is unique, so a second
        // factory()->create() with the same slug is a constraint violation.
        $plan = Plan::query()->where('slug', 'enterprise')->first() ?? Plan::factory()->create([
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

        $roleService = app(TenantRoleService::class);
        $roleService->setupDefaultRoles($tenant);
        $roleService->syncRole($user, 'admin', $tenant);

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

        // The assigned agent must have a support_tier >= the escalation target tier.
        $agent = User::factory()->create(['support_tier' => 'tier_3']);
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

    /**
     * The ladder moved out of EscalationController's private helper into
     * EscalationService so AnalyzeTicketService (suggestion 7) could filter engineer
     * candidates with the same comparison instead of a second copy. These assert the
     * moved helper directly, and the test below asserts the controller still refuses
     * exactly what it refused before.
     */
    public function test_the_tier_ladder_ranks_and_compares_in_the_service(): void
    {
        $service = app(EscalationService::class);

        $this->assertSame(0, $service->tierRank(null));
        $this->assertSame(1, $service->tierRank('tier_1'));
        $this->assertSame(3, $service->tierRank('tier_3'));

        $tier2 = User::factory()->make(['support_tier' => 'tier_2']);

        $this->assertTrue($service->canHandleTier($tier2, 'tier_1', null));
        $this->assertTrue($service->canHandleTier($tier2, 'tier_2', null));
        $this->assertFalse($service->canHandleTier($tier2, 'tier_3', null));
        $this->assertFalse($service->canHandleTier(User::factory()->make(['support_tier' => null]), 'tier_1', null));
        $this->assertFalse($service->canHandleTier(null, 'tier_1', null));
    }

    public function test_an_owner_bypasses_the_tier_ladder_but_only_in_their_own_workspace(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $owner = User::factory()->create(['support_tier' => null]);
        $tenant->addUser($owner, 'owner');

        $other = $this->createEnterpriseTenant();

        $service = app(EscalationService::class);

        $this->assertTrue($service->canHandleTier($owner, 'tier_3', $tenant));
        // Owner of one workspace, nobody in another: the tenant is a parameter precisely
        // so this cannot be answered from the session.
        $this->assertFalse($service->canHandleTier($owner, 'tier_3', $other));
    }

    public function test_escalation_still_refuses_an_agent_below_the_target_tier(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $user = $this->setupTenantContext($tenant);

        $agent = User::factory()->create(['support_tier' => 'tier_1']);
        $tenant->addUser($agent, 'member');

        $ticket = Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
            'current_tier' => 'tier_1',
        ]);

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/escalate"), [
            'to_tier' => 'tier_3',
            'assigned_to' => $agent->id,
        ])->assertRedirect()->assertSessionHas('error');

        $ticket->refresh();
        $this->assertSame('tier_1', $ticket->current_tier);
    }
}
