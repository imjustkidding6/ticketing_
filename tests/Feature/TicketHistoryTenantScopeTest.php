<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A user can belong to several workspaces and switch between them. Anything that
 * queries ticket_histories directly must not show them one workspace's activity
 * while they are working in another.
 */
class TicketHistoryTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    private function ticketIn(Tenant $tenant, User $creator): Ticket
    {
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        return Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'created_by' => $creator->id,
            'status' => 'open',
        ]);
    }

    public function test_history_rows_are_scoped_to_the_active_workspace(): void
    {
        $user = User::factory()->create();
        $workspaceA = Tenant::factory()->withLicense()->create();
        $workspaceB = Tenant::factory()->withLicense()->create();
        $workspaceA->addUser($user, 'admin');
        $workspaceB->addUser($user, 'admin');

        $this->actingAs($user);

        // Work in B.
        $this->withSession(['current_tenant_id' => $workspaceB->id]);
        $ticketB = $this->ticketIn($workspaceB, $user);
        app(TicketService::class)->addHistory($ticketB, 'status_changed', 'status', 'open', 'closed', 'SECRET FROM WORKSPACE B');

        // Switch to A.
        $this->withSession(['current_tenant_id' => $workspaceA->id]);
        $ticketA = $this->ticketIn($workspaceA, $user);
        app(TicketService::class)->addHistory($ticketA, 'status_changed', 'status', 'open', 'closed', 'workspace A activity');

        $visible = TicketHistory::query()->where('user_id', $user->id)->get();

        $this->assertCount(1, $visible, 'Only the active workspace\'s history may be visible');
        $this->assertSame('workspace A activity', $visible->first()->description);
        $this->assertFalse(
            $visible->pluck('description')->contains('SECRET FROM WORKSPACE B'),
            'Activity from another workspace must never appear in this one'
        );
    }

    public function test_history_is_stamped_with_the_tickets_tenant_not_the_session(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->withLicense()->create();
        $tenant->addUser($user, 'admin');
        $this->actingAs($user)->withSession(['current_tenant_id' => $tenant->id]);

        $ticket = $this->ticketIn($tenant, $user);

        // Background jobs run with no session at all; the ticket is the only
        // reliable source of the tenant.
        session()->forget('current_tenant_id');
        $entry = app(TicketService::class)->addHistory($ticket, 'created', description: 'from a job');

        $this->assertSame($tenant->id, $entry->tenant_id);
    }

    public function test_dashboard_activity_feed_does_not_leak_across_workspaces(): void
    {
        $user = User::factory()->create();
        $workspaceA = Tenant::factory()->withLicense()->create();
        $workspaceB = Tenant::factory()->withLicense()->create();
        $workspaceA->addUser($user, 'owner');
        $workspaceB->addUser($user, 'owner');

        $this->actingAs($user);

        $this->withSession(['current_tenant_id' => $workspaceB->id]);
        $ticketB = $this->ticketIn($workspaceB, $user);
        app(TicketService::class)->addHistory($ticketB, 'status_changed', description: 'LEAKED-FROM-B');

        $this->withSession(['current_tenant_id' => $workspaceA->id])->withTenant($workspaceA);
        $ticketA = $this->ticketIn($workspaceA, $user);
        app(TicketService::class)->addHistory($ticketA, 'status_changed', description: 'belongs-to-A');

        $this->get($this->tenantUrl('/my-activity'))
            ->assertOk()
            ->assertDontSee('LEAKED-FROM-B');
    }
}
