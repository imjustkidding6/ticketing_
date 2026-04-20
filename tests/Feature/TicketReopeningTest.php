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

class TicketReopeningTest extends TestCase
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

    public function test_reopen_returns_403_for_starter_plan(): void
    {
        $tenant = $this->createStarterTenant();
        $user = $this->setupTenantContext($tenant);

        $ticket = Ticket::factory()->closed()->create([
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
        ]);

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/reopen"))
            ->assertForbidden();
    }

    public function test_can_reopen_closed_ticket(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $user = $this->setupTenantContext($tenant);

        $ticket = Ticket::factory()->closed()->create([
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
        ]);

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/reopen"))
            ->assertRedirect();

        $ticket->refresh();
        $this->assertEquals('open', $ticket->status);
        $this->assertNull($ticket->closed_at);
        $this->assertEquals(1, $ticket->reopened_count);
    }

    public function test_reopen_increments_reopened_count(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $user = $this->setupTenantContext($tenant);

        $ticket = Ticket::factory()->closed()->create([
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
            'reopened_count' => 2,
        ]);

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/reopen"));

        $ticket->refresh();
        $this->assertEquals(3, $ticket->reopened_count);
    }

    public function test_reopen_creates_history_entry(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $user = $this->setupTenantContext($tenant);

        $ticket = Ticket::factory()->closed()->create([
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
        ]);

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/reopen"));

        $this->assertDatabaseHas('ticket_histories', [
            'ticket_id' => $ticket->id,
            'action' => 'status_changed',
            'new_value' => 'open',
        ]);
    }
}
