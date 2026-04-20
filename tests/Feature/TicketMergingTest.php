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

class TicketMergingTest extends TestCase
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

    public function test_merge_returns_403_for_starter_plan(): void
    {
        $tenant = $this->createStarterTenant();
        $user = $this->setupTenantContext($tenant);

        $source = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);
        $target = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

        $this->post($this->tenantUrl("/tickets/{$source->id}/merge"), [
            'target_ticket_id' => $target->id,
        ])->assertForbidden();
    }

    public function test_can_merge_ticket_into_another(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $user = $this->setupTenantContext($tenant);

        $source = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);
        $target = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

        $this->post($this->tenantUrl("/tickets/{$source->id}/merge"), [
            'target_ticket_id' => $target->id,
        ])->assertRedirect();

        $source->refresh();
        $this->assertTrue($source->is_merged);
        $this->assertEquals($target->id, $source->merged_into_ticket_id);
        $this->assertEquals('closed', $source->status);
    }

    public function test_merge_requires_valid_target(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $user = $this->setupTenantContext($tenant);

        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/merge"), [
            'target_ticket_id' => '',
        ])->assertSessionHasErrors('target_ticket_id');
    }

    public function test_cannot_merge_into_nonexistent_ticket(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $user = $this->setupTenantContext($tenant);

        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/merge"), [
            'target_ticket_id' => 99999,
        ])->assertSessionHasErrors('target_ticket_id');
    }
}
