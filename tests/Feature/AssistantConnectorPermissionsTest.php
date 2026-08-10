<?php

namespace Tests\Feature;

use App\Assistant\AddComment;
use App\Assistant\CreateTicket;
use App\Assistant\FindTickets;
use App\Assistant\ListClients;
use App\Assistant\SetTenantContext;
use App\Assistant\UpdateStatus;
use App\Enums\PlanFeature;
use App\Models\Client;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\TenantRoleService;
use Cliqueha\AssistantConnector\Models\DesktopToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The Jude connector tools must never let a token do more than its owner can do
 * in the web UI.
 */
class AssistantConnectorPermissionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function setupContext(string $role = 'agent'): array
    {
        $plan = Plan::factory()->create(['slug' => 'business', 'features' => PlanFeature::forPlan('business')]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);

        // Business+ tenants enforce the SLA-policy guard on ticket priority.
        SlaPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'priority' => null,
            'client_tier' => null,
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $tenant->addUser($user, $role);

        $roleService = app(TenantRoleService::class);
        $roleService->setTenantContext($tenant);
        $roleService->setupDefaultRoles($tenant);

        // 'owner' is a pivot role, not a Spatie role — it bypasses checks instead.
        if ($role !== 'owner') {
            $roleService->syncRole($user, $role, $tenant);
        }

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        return [$tenant, $user->fresh()];
    }

    private function makeTicket(Tenant $tenant, array $attributes = []): Ticket
    {
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        return Ticket::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
        ], $attributes));
    }

    public function test_agent_can_create_a_ticket_but_cannot_browse_clients(): void
    {
        [$tenant, $user] = $this->setupContext('agent');
        Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Acme Corp']);

        $created = (new CreateTicket)->handle([
            'client' => 'Acme',
            'subject' => 'Printer down',
            'description' => 'It is on fire.',
        ], $user);

        $this->assertSame('created', $created['status'] ?? null);

        // Agents hold no 'view clients' — the directory stays closed to them.
        $listed = (new ListClients)->handle([], $user);

        $this->assertArrayHasKey('error', $listed);
        $this->assertArrayNotHasKey('clients', $listed);
    }

    public function test_viewer_cannot_create_a_ticket(): void
    {
        [$tenant, $user] = $this->setupContext('viewer');
        Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Acme Corp']);

        $result = (new CreateTicket)->handle([
            'client' => 'Acme',
            'subject' => 'Printer down',
            'description' => 'It is on fire.',
        ], $user);

        $this->assertArrayHasKey('error', $result);
        // Read-only roles still see tickets and clients.
        $this->assertArrayHasKey('tickets', (new FindTickets)->handle([], $user));
        $this->assertArrayHasKey('clients', (new ListClients)->handle([], $user));
    }

    public function test_create_ticket_suggests_client_names_to_users_who_may_view_clients(): void
    {
        [$tenant, $user] = $this->setupContext('admin');
        Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Secret Industries']);

        $result = (new CreateTicket)->handle([
            'client' => 'no-such-client',
            'subject' => 'x',
            'description' => 'y',
        ], $user);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Secret Industries', $result['error']);
    }

    public function test_create_ticket_does_not_leak_client_names_without_view_clients(): void
    {
        [$tenant, $user] = $this->setupContext('agent');
        Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Secret Industries']);

        $result = (new CreateTicket)->handle([
            'client' => 'no-such-client',
            'subject' => 'x',
            'description' => 'y',
        ], $user);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringNotContainsString('Secret Industries', $result['error']);
    }

    public function test_agent_can_progress_and_close_a_ticket(): void
    {
        [$tenant, $user] = $this->setupContext('agent');
        $ticket = $this->makeTicket($tenant, ['status' => 'open']);

        $progressed = (new UpdateStatus)->handle([
            'ticket_number' => $ticket->ticket_number,
            'status' => 'in_progress',
        ], $user);
        $this->assertSame('updated', $progressed['status'] ?? null);

        // The default agent role holds 'close tickets'.
        $closed = (new UpdateStatus)->handle([
            'ticket_number' => $ticket->ticket_number,
            'status' => 'closed',
        ], $user);
        $this->assertSame('updated', $closed['status'] ?? null);
        $this->assertSame('closed', $ticket->fresh()->status);
    }

    public function test_closing_is_blocked_when_the_role_lacks_close_tickets(): void
    {
        [$tenant, $user] = $this->setupContext('agent');
        $ticket = $this->makeTicket($tenant, ['status' => 'open']);

        Role::where('name', 'agent')->where('tenant_id', $tenant->id)->first()
            ->revokePermissionTo('close tickets');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $result = (new UpdateStatus)->handle([
            'ticket_number' => $ticket->ticket_number,
            'status' => 'closed',
        ], $user->fresh());

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('open', $ticket->fresh()->status);
    }

    public function test_reopening_is_blocked_without_the_reopen_permission(): void
    {
        [$tenant, $user] = $this->setupContext('agent');
        $ticket = $this->makeTicket($tenant, ['status' => 'closed']);

        // 'reopen tickets' is an Enterprise-gated permission the agent lacks here.
        $result = (new UpdateStatus)->handle([
            'ticket_number' => $ticket->ticket_number,
            'status' => 'open',
        ], $user);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('closed', $ticket->fresh()->status);
    }

    public function test_user_with_no_role_is_denied_everywhere_and_writes_nothing(): void
    {
        [$tenant, $user] = $this->setupContext('agent');
        $ticket = $this->makeTicket($tenant, ['status' => 'open']);
        Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Acme Corp']);

        // Strip the Spatie role, keeping the tenant membership.
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->syncRoles([]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        $ticketCount = Ticket::withoutGlobalScopes()->count();
        $commentCount = TicketComment::withoutGlobalScopes()->count();

        $results = [
            (new FindTickets)->handle([], $user),
            (new ListClients)->handle([], $user),
            (new CreateTicket)->handle(['client' => 'Acme', 'subject' => 's', 'description' => 'd'], $user),
            (new UpdateStatus)->handle(['ticket_number' => $ticket->ticket_number, 'status' => 'closed'], $user),
            (new AddComment)->handle(['ticket_number' => $ticket->ticket_number, 'content' => 'hello'], $user),
        ];

        foreach ($results as $i => $result) {
            $this->assertArrayHasKey('error', $result, "Tool #{$i} should have been denied.");
        }

        $this->assertSame($ticketCount, Ticket::withoutGlobalScopes()->count());
        $this->assertSame($commentCount, TicketComment::withoutGlobalScopes()->count());
        $this->assertSame('open', $ticket->fresh()->status);
    }

    public function test_owner_bypasses_every_permission_check(): void
    {
        [$tenant, $user] = $this->setupContext('owner');
        $ticket = $this->makeTicket($tenant, ['status' => 'closed']);
        Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Acme Corp']);

        $this->assertArrayHasKey('clients', (new ListClients)->handle([], $user));
        $this->assertArrayHasKey('tickets', (new FindTickets)->handle([], $user));

        // Reopening a closed ticket — blocked for a plain agent, allowed for the owner.
        $reopened = (new UpdateStatus)->handle([
            'ticket_number' => $ticket->ticket_number,
            'status' => 'open',
        ], $user);
        $this->assertSame('updated', $reopened['status'] ?? null);

        $commented = (new AddComment)->handle([
            'ticket_number' => $ticket->ticket_number,
            'content' => 'Looking into it.',
        ], $user);
        $this->assertSame('comment_added', $commented['status'] ?? null);
    }

    public function test_set_tenant_context_syncs_the_spatie_team_id_from_the_token(): void
    {
        [$tenant, $user] = $this->setupContext('agent');

        $issued = DesktopToken::issue($user, 'Jude');
        $issued['token']->update(['tenant_id' => $tenant->id]);

        // Simulate a fresh request with no team id resolved yet.
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        request()->headers->set('Authorization', 'Bearer '.$issued['plain']);

        (new SetTenantContext)($user);

        $this->assertSame($tenant->id, app(PermissionRegistrar::class)->getPermissionsTeamId());
    }

    public function test_connect_jude_page_requires_manage_settings(): void
    {
        [, $user] = $this->setupContext('agent');

        $this->get('/connect-jude')->assertForbidden();
    }

    public function test_admin_can_reach_the_connect_jude_page(): void
    {
        [, $user] = $this->setupContext('admin');

        $this->get('/connect-jude')->assertOk();
    }
}
