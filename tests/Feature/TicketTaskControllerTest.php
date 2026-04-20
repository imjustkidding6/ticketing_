<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketTask;
use App\Models\User;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTaskControllerTest extends TestCase
{
    use RefreshDatabase;

    private function setupContext(): array
    {
        $plan = Plan::factory()->create(['slug' => 'business', 'features' => PlanFeature::forPlan('business')]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);
        $user = User::factory()->create();
        $tenant->addUser($user, 'admin');

        $roleService = app(TenantRoleService::class);
        $roleService->setTenantContext($tenant);
        $roleService->setupDefaultRoles($tenant);
        $roleService->syncRole($user, 'admin', $tenant);

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);

        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

        return [$tenant, $user, $ticket];
    }

    public function test_add_task(): void
    {
        [$tenant, $user, $ticket] = $this->setupContext();

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/tasks"), [
            'description' => 'Test task description',
        ])->assertRedirect();

        $this->assertDatabaseHas('ticket_tasks', [
            'ticket_id' => $ticket->id,
            'description' => 'Test task description',
        ]);
    }

    public function test_update_task(): void
    {
        [$tenant, $user, $ticket] = $this->setupContext();
        $task = TicketTask::factory()->create(['ticket_id' => $ticket->id]);

        $this->put($this->tenantUrl("/tickets/{$ticket->id}/tasks/{$task->id}"), [
            'description' => 'Updated task',
        ])->assertRedirect();

        $task->refresh();
        $this->assertEquals('Updated task', $task->description);
    }

    public function test_change_task_status(): void
    {
        [$tenant, $user, $ticket] = $this->setupContext();
        $task = TicketTask::factory()->create(['ticket_id' => $ticket->id, 'status' => 'pending']);

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/tasks/{$task->id}/status"), [
            'status' => 'completed',
        ])->assertRedirect();

        $task->refresh();
        $this->assertEquals('completed', $task->status);
    }

    public function test_delete_task(): void
    {
        [$tenant, $user, $ticket] = $this->setupContext();
        $task = TicketTask::factory()->create(['ticket_id' => $ticket->id]);

        $this->delete($this->tenantUrl("/tickets/{$ticket->id}/tasks/{$task->id}"))->assertRedirect();

        $this->assertDatabaseMissing('ticket_tasks', ['id' => $task->id]);
    }

    public function test_task_creates_activity_log(): void
    {
        [$tenant, $user, $ticket] = $this->setupContext();

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/tasks"), [
            'description' => 'Logged task',
        ]);

        $this->assertDatabaseHas('ticket_histories', [
            'ticket_id' => $ticket->id,
            'action' => 'task_added',
        ]);
    }

    public function test_task_status_creates_activity_log(): void
    {
        [$tenant, $user, $ticket] = $this->setupContext();
        $task = TicketTask::factory()->create(['ticket_id' => $ticket->id, 'status' => 'pending']);

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/tasks/{$task->id}/status"), [
            'status' => 'in_progress',
        ]);

        $this->assertDatabaseHas('ticket_histories', [
            'ticket_id' => $ticket->id,
            'action' => 'task_status_changed',
        ]);
    }

    public function test_task_history_endpoint(): void
    {
        [$tenant, $user, $ticket] = $this->setupContext();
        $task = TicketTask::factory()->create(['ticket_id' => $ticket->id]);

        $this->get($this->tenantUrl("/tickets/{$ticket->id}/tasks/{$task->id}/history"))
            ->assertOk()
            ->assertJsonIsArray();
    }
}
