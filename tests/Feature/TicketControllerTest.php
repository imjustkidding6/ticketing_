<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\Client;
use App\Models\Department;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createBusinessTenant(): Tenant
    {
        $plan = Plan::factory()->create(['slug' => 'business', 'features' => PlanFeature::forPlan('business')]);
        $license = License::factory()->active()->forPlan($plan)->create();

        return Tenant::factory()->create(['license_id' => $license->id]);
    }

    private function setupTenantContext(Tenant $tenant, string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $tenant->addUser($user, $role === 'admin' ? 'admin' : 'member');

        $roleService = app(TenantRoleService::class);
        $roleService->setTenantContext($tenant);
        $roleService->setupDefaultRoles($tenant);
        $roleService->syncRole($user, $role, $tenant);

        $this->actingAs($user)
            ->withTenant($tenant)
            ->withSession(['current_tenant_id' => $tenant->id]);

        return $user;
    }

    public function test_index_requires_auth(): void
    {
        $tenant = $this->createBusinessTenant();

        $this->get($this->withTenant($tenant)->tenantUrl('/tickets'))
            ->assertRedirect('/login');
    }

    public function test_index_lists_tickets(): void
    {
        $tenant = $this->createBusinessTenant();
        $user = $this->setupTenantContext($tenant);

        Ticket::factory()->count(3)->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

        $this->get($this->tenantUrl('/tickets'))->assertOk();
    }

    public function test_create_form_loads(): void
    {
        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant);

        $this->get($this->tenantUrl('/tickets/create'))->assertOk();
    }

    public function test_agent_cannot_access_ticket_creation_form(): void
    {
        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant, 'agent');

        $this->get($this->tenantUrl('/tickets/create'))->assertForbidden();
    }

    public function test_agent_cannot_submit_new_ticket(): void
    {
        $tenant = $this->createBusinessTenant();
        $agent = $this->setupTenantContext($tenant, 'agent');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->post($this->tenantUrl('/tickets'), [
            'client_id' => $client->id,
            'subject' => 'Agent ticket attempt',
            'description' => 'Should be blocked',
            'priority' => 'medium',
        ])->assertForbidden();

        $this->assertDatabaseMissing('tickets', [
            'tenant_id' => $tenant->id,
            'subject' => 'Agent ticket attempt',
            'created_by' => $agent->id,
        ]);
    }

    public function test_agent_does_not_see_create_ticket_action_on_index(): void
    {
        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant, 'agent');

        $this->get($this->tenantUrl('/tickets'))
            ->assertOk()
            ->assertDontSee('New Ticket')
            ->assertDontSee('Create your first ticket');
    }

    public function test_store_creates_ticket(): void
    {
        $tenant = $this->createBusinessTenant();
        $user = $this->setupTenantContext($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
        $category = TicketCategory::factory()->create(['tenant_id' => $tenant->id, 'department_id' => $dept->id]);

        $this->post($this->tenantUrl('/tickets'), [
            'client_id' => $client->id,
            'subject' => 'Test ticket subject',
            'description' => 'Test ticket description',
            'priority' => 'medium',
            'department_id' => $dept->id,
            'category_id' => $category->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'tenant_id' => $tenant->id,
            'subject' => 'Test ticket subject',
            'client_id' => $client->id,
        ]);
    }

    public function test_show_ticket(): void
    {
        $tenant = $this->createBusinessTenant();
        $user = $this->setupTenantContext($tenant);
        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

        $this->get($this->tenantUrl("/tickets/{$ticket->id}"))->assertOk();
    }

    public function test_agent_does_not_see_edit_button_on_ticket_view(): void
    {
        $tenant = $this->createBusinessTenant();
        $manager = $this->setupTenantContext($tenant, 'manager');
        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $manager->id]);

        $this->setupTenantContext($tenant, 'agent');

        $this->get($this->tenantUrl("/tickets/{$ticket->id}"))
            ->assertOk()
            ->assertDontSee($this->tenantUrl("/tickets/{$ticket->id}/edit"));
    }

    public function test_agent_does_not_see_edit_button_on_ticket_index(): void
    {
        $tenant = $this->createBusinessTenant();
        $manager = $this->setupTenantContext($tenant, 'manager');
        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $manager->id]);

        $this->setupTenantContext($tenant, 'agent');

        $this->get($this->tenantUrl('/tickets'))
            ->assertOk()
            ->assertDontSee($this->tenantUrl("/tickets/{$ticket->id}/edit"));
    }

    public function test_manager_sees_edit_button_on_ticket_index(): void
    {
        $tenant = $this->createBusinessTenant();
        $manager = $this->setupTenantContext($tenant, 'manager');
        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $manager->id]);

        $this->get($this->tenantUrl('/tickets'))
            ->assertOk()
            ->assertSee($this->tenantUrl("/tickets/{$ticket->id}/edit"));
    }

    public function test_edit_ticket(): void
    {
        $tenant = $this->createBusinessTenant();
        $user = $this->setupTenantContext($tenant);
        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

        $this->get($this->tenantUrl("/tickets/{$ticket->id}/edit"))->assertOk();
    }

    public function test_assign_ticket(): void
    {
        $tenant = $this->createBusinessTenant();
        $user = $this->setupTenantContext($tenant);
        $agent = User::factory()->create();
        $tenant->addUser($agent, 'member');
        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/assign"), [
            'assigned_to' => $agent->id,
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertEquals($agent->id, $ticket->assigned_to);
    }

    public function test_self_assign(): void
    {
        $tenant = $this->createBusinessTenant();
        $user = $this->setupTenantContext($tenant);
        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/self-assign"))->assertRedirect();

        $ticket->refresh();
        $this->assertEquals($user->id, $ticket->assigned_to);
    }

    public function test_change_status(): void
    {
        $tenant = $this->createBusinessTenant();
        $user = $this->setupTenantContext($tenant);
        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id, 'status' => 'open']);

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/status"), [
            'status' => 'in_progress',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertEquals('in_progress', $ticket->status);
    }

    public function test_change_priority(): void
    {
        $tenant = $this->createBusinessTenant();
        $user = $this->setupTenantContext($tenant);
        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id, 'priority' => 'low']);

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/priority"), [
            'priority' => 'critical',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertEquals('critical', $ticket->priority);
    }

    public function test_soft_delete_ticket(): void
    {
        $tenant = $this->createBusinessTenant();
        $user = $this->setupTenantContext($tenant);
        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

        $this->delete($this->tenantUrl("/tickets/{$ticket->id}"), [
            'reason' => 'Duplicate',
        ])->assertRedirect();

        $this->assertSoftDeleted('tickets', ['id' => $ticket->id]);
    }

    public function test_restore_ticket(): void
    {
        $tenant = $this->createBusinessTenant();
        $user = $this->setupTenantContext($tenant);
        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);
        $ticket->delete();

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/restore"))->assertRedirect();

        $this->assertNotSoftDeleted('tickets', ['id' => $ticket->id]);
    }

    public function test_search_tickets(): void
    {
        $tenant = $this->createBusinessTenant();
        $user = $this->setupTenantContext($tenant);
        Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id, 'subject' => 'Unique search term xyz']);

        $this->get($this->tenantUrl('/tickets-search?q=xyz'))->assertOk();
    }

    public function test_mark_false_alarm(): void
    {
        $tenant = $this->createBusinessTenant();
        $user = $this->setupTenantContext($tenant);
        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/false-alarm"))->assertRedirect();

        $ticket->refresh();
        $this->assertTrue($ticket->is_false_alarm);
    }

    public function test_tenant_isolation(): void
    {
        $tenant1 = $this->createBusinessTenant();
        $user = $this->setupTenantContext($tenant1);

        $plan2 = Plan::factory()->create(['slug' => 'business2', 'features' => PlanFeature::forPlan('business')]);
        $license2 = License::factory()->active()->forPlan($plan2)->create();
        $tenant2 = Tenant::factory()->create(['license_id' => $license2->id]);
        $otherUser = User::factory()->create();
        $otherTicket = Ticket::factory()->create(['tenant_id' => $tenant2->id, 'created_by' => $otherUser->id]);

        $this->get($this->tenantUrl("/tickets/{$otherTicket->id}"))
            ->assertForbidden();
    }

    public function test_agent_cannot_delete_tickets(): void
    {
        $tenant = $this->createBusinessTenant();
        $user = $this->setupTenantContext($tenant, 'agent');
        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

        $this->delete($this->tenantUrl("/tickets/{$ticket->id}"))
            ->assertForbidden();
    }
}
