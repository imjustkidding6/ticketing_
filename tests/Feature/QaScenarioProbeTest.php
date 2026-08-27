<?php

namespace Tests\Feature;

/**
 * QA EVIDENCE PROBES — these tests DOCUMENT CURRENT BEHAVIOUR, they are not
 * assertions of desired behaviour. Several of them assert that a bug exists.
 * Do not "fix" a probe; fix the code and then invert the probe.
 */

use App\Enums\PlanFeature;
use App\Models\Client;
use App\Models\Department;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\TenantRoleService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QaScenarioProbeTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOnPlan(string $slug = 'enterprise'): Tenant
    {
        $plan = Plan::where('slug', $slug)->first()
            ?? Plan::factory()->create(['slug' => $slug, 'features' => PlanFeature::forPlan($slug)]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);
        SlaPolicy::factory()->create([
            'tenant_id' => $tenant->id, 'priority' => null,
            'client_tier' => null, 'is_active' => true,
        ]);

        return $tenant;
    }

    private function actor(Tenant $tenant, string $role = 'admin', string $pivot = 'admin'): User
    {
        $user = User::factory()->create();
        $tenant->addUser($user, $pivot);
        $rs = app(TenantRoleService::class);
        $rs->setTenantContext($tenant);
        $rs->setupDefaultRoles($tenant);
        $rs->syncRole($user, $role, $tenant);
        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);

        return $user;
    }

    private function ticket(Tenant $tenant, array $attrs = []): Ticket
    {
        return Ticket::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'status' => 'open',
            'priority' => 'medium',
        ], $attrs));
    }

    // ── SCENARIO 14 — invalid status transitions ───────────────────────────

    public function test_closed_ticket_cannot_jump_straight_back_to_in_progress(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $t = $this->ticket($tenant, ['status' => 'closed', 'closed_at' => now()]);

        $this->post($this->tenantUrl("/tickets/{$t->id}/status"), ['status' => 'in_progress'])
            ->assertSessionHas('error');

        $this->assertSame('closed', $t->fresh()->status,
            'closed -> in_progress is not in the transition map and must be refused');
    }

    public function test_open_ticket_may_be_closed_directly_leaving_no_response_time(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $t = $this->ticket($tenant);

        $this->post($this->tenantUrl("/tickets/{$t->id}/status"), [
            'status' => 'closed', 'closing_remarks' => 'done',
        ]);

        // open -> closed IS in the transition map: a ticket can legitimately be
        // closed without being worked (duplicate, spam, resolved on the call).
        // Documented here so the consequence is visible rather than surprising.
        $f = $t->fresh();
        $this->assertSame('closed', $f->status);
        $this->assertNull($f->first_response_at,
            'A ticket closed without ever moving to in_progress has no measurable response time');
    }

    public function test_transition_map_is_enforced_at_the_service_layer_too(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $t = $this->ticket($tenant, ['status' => 'closed']);

        $wf = app(\App\Services\TicketWorkflowService::class);
        $this->assertFalse($wf->isValidTransition($t, 'in_progress'));

        // The same rule must hold at the service layer, which is what the API and
        // the assistant tools go through.
        $this->expectException(\App\Exceptions\InvalidStatusTransitionException::class);
        app(TicketService::class)->changeStatus($t, 'in_progress');
    }

    // ── SCENARIO 7 — reopening ─────────────────────────────────────────────

    public function test_already_open_ticket_cannot_be_reopened(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $t = $this->ticket($tenant, ['status' => 'open', 'reopened_count' => 0]);

        $this->post($this->tenantUrl("/tickets/{$t->id}/reopen"), ['reason' => 'bogus'])
            ->assertSessionHas('error');

        $this->assertSame(0, $t->fresh()->reopened_count,
            'Reopening a ticket that was never closed must not inflate the reopen counter');
    }

    public function test_status_change_reopen_clears_closed_at_and_counts_the_reopen(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $t = $this->ticket($tenant, ['status' => 'closed', 'closed_at' => now()->subHour()]);

        // Reopen via the ordinary status control rather than the Reopen button.
        $this->post($this->tenantUrl("/tickets/{$t->id}/status"), ['status' => 'open']);

        $f = $t->fresh();
        $this->assertSame('open', $f->status);
        $this->assertNull($f->closed_at,
            'An open ticket must not carry a closure timestamp — reports key on closed_at');
        $this->assertSame(1, $f->reopened_count,
            'Reopening through the status control counts as a reopen, same as the button');
    }

    // ── SCENARIO 8 / 11 — merging ──────────────────────────────────────────

    public function test_ticket_cannot_be_merged_into_itself(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $t = $this->ticket($tenant);

        $this->post($this->tenantUrl("/tickets/{$t->id}/merge"), ['target_ticket_id' => $t->id])
            ->assertSessionHas('error');

        $f = $t->fresh();
        $this->assertFalse((bool) $f->is_merged,
            'A ticket merged into itself becomes its own parent and vanishes from every list');
        $this->assertNull($f->merged_into_ticket_id);
    }

    public function test_child_cannot_be_merged_into_another_child(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $mother = $this->ticket($tenant);
        $childA = $this->ticket($tenant);
        $childB = $this->ticket($tenant);

        $this->post($this->tenantUrl("/tickets/{$childA->id}/merge"), ['target_ticket_id' => $mother->id]);
        $this->post($this->tenantUrl("/tickets/{$childB->id}/merge"), ['target_ticket_id' => $childA->id])
            ->assertSessionHas('error');

        $this->assertFalse((bool) $childB->fresh()->is_merged,
            'Merging into an already-merged ticket builds a chain the mother cannot see');

        // Merged straight into the mother, both children are visible where staff look.
        $this->post($this->tenantUrl("/tickets/{$childB->id}/merge"), ['target_ticket_id' => $mother->id]);
        $this->assertCount(2, $mother->fresh()->mergedTickets);
    }

    public function test_unmerge_returns_comments_to_the_child(): void
    {
        $tenant = $this->tenantOnPlan();
        $user = $this->actor($tenant);
        $mother = $this->ticket($tenant);
        $child = $this->ticket($tenant);

        TicketComment::factory()->create([
            'tenant_id' => $tenant->id, 'ticket_id' => $child->id, 'user_id' => $user->id,
        ]);

        $this->post($this->tenantUrl("/tickets/{$child->id}/merge"), ['target_ticket_id' => $mother->id]);
        $this->post($this->tenantUrl("/tickets/{$child->id}/unmerge"));

        $this->assertSame(1, $child->fresh()->comments()->count(),
            'Unmerge must return the comments the merge moved, or the undo loses history');
        $this->assertSame(0, $mother->fresh()->comments()->count());
    }

    public function test_unmerge_restores_the_status_the_child_had_before_the_merge(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $mother = $this->ticket($tenant);
        $child = $this->ticket($tenant, ['status' => 'closed', 'closed_at' => now()->subDay()]);

        $this->post($this->tenantUrl("/tickets/{$child->id}/merge"), ['target_ticket_id' => $mother->id]);
        $this->post($this->tenantUrl("/tickets/{$child->id}/unmerge"));

        $this->assertSame('closed', $child->fresh()->status,
            'Unmerge must restore the pre-merge status, not resurrect a legitimately closed ticket');
        $this->assertNotNull($child->fresh()->closed_at);
    }

    // ── SCENARIO 9 — concurrent assignment ─────────────────────────────────

    public function test_second_agent_cannot_steal_an_already_claimed_ticket(): void
    {
        $tenant = $this->tenantOnPlan();
        $john = $this->actor($tenant, 'agent', 'member');
        $t = $this->ticket($tenant);

        $this->post($this->tenantUrl("/tickets/{$t->id}/self-assign"));
        $this->assertSame($john->id, $t->fresh()->assigned_to);

        $maria = $this->actor($tenant, 'agent', 'member');
        $this->post($this->tenantUrl("/tickets/{$t->id}/self-assign"))
            ->assertSessionHas('error');

        $this->assertSame($john->id, $t->fresh()->assigned_to,
            'A claimed ticket must stay with the agent who claimed it first');
        $this->assertSame(1, $t->fresh()->assignments()->count(),
            'A losing claim must not leave an assignment row behind');
    }

    public function test_manager_reassignment_still_overwrites_an_existing_assignee(): void
    {
        $tenant = $this->tenantOnPlan();
        $john = $this->actor($tenant, 'agent', 'member');
        $t = $this->ticket($tenant);
        $this->post($this->tenantUrl("/tickets/{$t->id}/self-assign"));

        $maria = User::factory()->create();
        $tenant->addUser($maria, 'member');
        $this->actor($tenant, 'manager', 'member');

        // Reassignment is deliberate, so it must not be blocked by the claim guard.
        $this->post($this->tenantUrl("/tickets/{$t->id}/assign"), ['assigned_to' => $maria->id]);

        $this->assertSame($maria->id, $t->fresh()->assigned_to);
        $this->assertSame(2, $t->fresh()->assignments()->count(),
            'Both the original claim and the reassignment stay in the assignment history');
    }

    public function test_claim_is_rejected_when_the_row_is_taken_between_read_and_write(): void
    {
        $tenant = $this->tenantOnPlan();
        $john = $this->actor($tenant, 'agent', 'member');
        $t = $this->ticket($tenant);
        $other = User::factory()->create();
        $tenant->addUser($other, 'member');

        // Stand in for the interleaving: the in-memory model still reads as
        // unassigned while the row underneath has already been claimed.
        Ticket::withoutGlobalScopes()->where('id', $t->id)->update(['assigned_to' => $other->id]);
        $this->assertNull($t->assigned_to);

        $this->expectException(\App\Exceptions\TicketAlreadyAssignedException::class);
        app(TicketService::class)->assignTicket($t, $john, onlyIfUnassigned: true);
    }

    // ── SCENARIO 10 / 19 — cross-tenant foreign keys ───────────────────────

    public function test_ticket_cannot_be_created_pointing_at_another_tenants_client(): void
    {
        $victim = $this->tenantOnPlan();
        $victimClient = Client::factory()->create(['tenant_id' => $victim->id, 'name' => 'VICTIM CORP']);

        $attacker = $this->tenantOnPlan();
        $this->actor($attacker);
        $ownClient = Client::factory()->create(['tenant_id' => $attacker->id]);

        $this->post($this->tenantUrl('/tickets'), [
            'subject' => 'probe', 'description' => 'probe',
            'priority' => 'medium', 'client_id' => $victimClient->id,
        ])->assertSessionHasErrors('client_id');

        $this->assertSame(0, Ticket::withoutGlobalScopes()->where('tenant_id', $attacker->id)->count(),
            'A foreign tenant client_id must be rejected by validation, not silently accepted');

        // The tenant's own client still works — the rule scopes, it does not block.
        $this->post($this->tenantUrl('/tickets'), [
            'subject' => 'probe', 'description' => 'probe',
            'priority' => 'medium', 'client_id' => $ownClient->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Ticket::withoutGlobalScopes()->where('tenant_id', $attacker->id)->count());
    }

    public function test_ticket_cannot_be_assigned_to_a_user_of_another_tenant(): void
    {
        $victim = $this->tenantOnPlan();
        $outsider = User::factory()->create(['name' => 'OUTSIDER']);
        $victim->addUser($outsider, 'member');

        $attacker = $this->tenantOnPlan();
        $this->actor($attacker);
        $client = Client::factory()->create(['tenant_id' => $attacker->id]);

        $this->post($this->tenantUrl('/tickets'), [
            'subject' => 'probe', 'description' => 'probe', 'priority' => 'medium',
            'client_id' => $client->id, 'assigned_to' => $outsider->id,
        ])->assertSessionHasErrors('assigned_to');

        $this->assertSame(0, Ticket::withoutGlobalScopes()->where('tenant_id', $attacker->id)->count(),
            'assigned_to must require membership of the current tenant, not merely a users row');
    }

    // ── SLA guard reachability ─────────────────────────────────────────────

    public function test_sla_guard_fires_on_the_web_create_path(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        // Remove every policy so the guard SHOULD reject a prioritised ticket.
        SlaPolicy::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->post($this->tenantUrl('/tickets'), [
            'subject' => 'probe', 'description' => 'probe',
            'priority' => 'critical', 'client_id' => $client->id,
        ])->assertSessionHasErrors();

        $this->assertSame(0, Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(),
            'The SLA guard must reject a prioritised ticket with no matching policy on every entry point');
    }

    public function test_sla_guard_fires_at_the_service_layer(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        SlaPolicy::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();

        $this->expectException(\InvalidArgumentException::class);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        app(TicketService::class)->createTicket([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'subject' => 'probe', 'description' => 'probe', 'priority' => 'critical',
        ]);
    }

    // ── SCENARIO 5 — SLA clock start ───────────────────────────────────────

    public function test_sla_clock_starts_at_creation_not_assignment(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $policy = SlaPolicy::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $t = app(TicketService::class)->createTicket([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'subject' => 'p', 'description' => 'p', 'priority' => 'high',
        ]);

        $this->assertNotNull($t->response_due_at);
        $this->assertSame(
            $t->created_at->addHours($policy->response_time_hours)->timestamp,
            $t->response_due_at->timestamp,
            'EVIDENCE: SLA due dates are derived from created_at — an unassigned ticket burns SLA while nobody owns it'
        );
    }

    // ── SCENARIO 10 — department scoping fail-open ─────────────────────────

    public function test_agent_with_no_departments_does_not_see_every_ticket(): void
    {
        $tenant = $this->tenantOnPlan();
        $itDept = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'IT']);
        $hrDept = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'HR']);

        $hrTicket = $this->ticket($tenant, ['department_id' => $hrDept->id, 'subject' => 'HR SECRET']);

        // An agent deliberately given NO department membership.
        $agent = $this->actor($tenant, 'agent', 'member');
        $this->assertSame(0, $agent->departments()->count());

        // No departments means no departmental grant — not a grant to everything.
        $this->get($this->tenantUrl("/tickets/{$hrTicket->id}"))->assertForbidden();

        // Their own work stays reachable.
        $ownTicket = $this->ticket($tenant, ['department_id' => $itDept->id, 'assigned_to' => $agent->id]);
        $this->get($this->tenantUrl("/tickets/{$ownTicket->id}"))->assertOk();
    }

    public function test_department_restriction_applies_to_every_verb_not_just_show(): void
    {
        $tenant = $this->tenantOnPlan();
        $it = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'IT']);
        $hr = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'HR']);
        $hrTicket = $this->ticket($tenant, ['department_id' => $hr->id]);

        $agent = $this->actor($tenant, 'agent', 'member');
        $agent->departments()->attach($it->id);

        $this->get($this->tenantUrl("/tickets/{$hrTicket->id}"))->assertForbidden();

        // The restriction must hold whichever verb is used to reach the ticket.
        $this->get($this->tenantUrl("/tickets/{$hrTicket->id}/edit"))->assertForbidden();
        $this->post($this->tenantUrl("/tickets/{$hrTicket->id}/status"), ['status' => 'in_progress'])
            ->assertForbidden();
        $this->post($this->tenantUrl("/tickets/{$hrTicket->id}/self-assign"))->assertForbidden();
    }

    public function test_department_restricted_agent_cannot_download_foreign_attachment(): void
    {
        $tenant = $this->tenantOnPlan();
        $it = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'IT']);
        $hr = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'HR']);
        $hrTicket = $this->ticket($tenant, [
            'department_id' => $hr->id,
            'attachments' => [['name' => 'payroll.pdf', 'path' => 'x/payroll.pdf', 'size' => 1, 'mime' => 'application/pdf']],
        ]);

        $agent = $this->actor($tenant, 'agent', 'member');
        $agent->departments()->attach($it->id);

        $this->get($this->tenantUrl("/tickets/{$hrTicket->id}/attachments/0"))
            ->assertForbidden();
    }

    // ── SCENARIO 12 — duplicate submission ─────────────────────────────────

    public function test_probe_identical_submissions_create_two_tickets(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $payload = [
            'subject' => 'Cannot access company email', 'description' => 'same',
            'priority' => 'medium', 'client_id' => $client->id,
        ];

        $this->post($this->tenantUrl('/tickets'), $payload);
        $this->post($this->tenantUrl('/tickets'), $payload);

        $this->assertSame(2, Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(),
            'EVIDENCE: no idempotency key, no duplicate detection — a double-click yields two tickets');
    }
}
