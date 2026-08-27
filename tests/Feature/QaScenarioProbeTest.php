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

    public function test_probe_closed_ticket_can_jump_straight_back_to_in_progress(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $t = $this->ticket($tenant, ['status' => 'closed', 'closed_at' => now()]);

        $this->post($this->tenantUrl("/tickets/{$t->id}/status"), ['status' => 'in_progress']);

        // The state machine in TicketWorkflowService forbids closed -> in_progress.
        $this->assertSame('in_progress', $t->fresh()->status,
            'EVIDENCE: HTTP path bypassed TicketWorkflowService and allowed closed -> in_progress');
    }

    public function test_probe_open_ticket_can_be_closed_without_ever_being_worked(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $t = $this->ticket($tenant);

        $this->post($this->tenantUrl("/tickets/{$t->id}/status"), [
            'status' => 'closed', 'closing_remarks' => 'done',
        ]);

        $f = $t->fresh();
        $this->assertSame('closed', $f->status);
        $this->assertNull($f->first_response_at,
            'EVIDENCE: ticket closed with no first_response_at — SLA response is unmeasurable');
    }

    public function test_probe_workflow_service_would_have_rejected_what_http_allowed(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $t = $this->ticket($tenant, ['status' => 'closed']);

        $wf = app(\App\Services\TicketWorkflowService::class);
        $this->assertFalse($wf->isValidTransition($t, 'in_progress'),
            'EVIDENCE: the rule exists in the service layer but nothing on the HTTP path calls it');
    }

    // ── SCENARIO 7 — reopening ─────────────────────────────────────────────

    public function test_probe_already_open_ticket_can_be_reopened_again(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $t = $this->ticket($tenant, ['status' => 'open', 'reopened_count' => 0]);

        $this->post($this->tenantUrl("/tickets/{$t->id}/reopen"), ['reason' => 'bogus']);

        $this->assertSame(1, $t->fresh()->reopened_count,
            'EVIDENCE: reopen() has no guard that the ticket is closed; counter inflated on an open ticket');
    }

    public function test_probe_status_change_reopen_leaves_stale_closed_at(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $t = $this->ticket($tenant, ['status' => 'closed', 'closed_at' => now()->subHour()]);

        // Reopen via the ordinary status control rather than the Reopen button.
        $this->post($this->tenantUrl("/tickets/{$t->id}/status"), ['status' => 'open']);

        $f = $t->fresh();
        $this->assertSame('open', $f->status);
        $this->assertNotNull($f->closed_at,
            'EVIDENCE: ticket is open but still carries closed_at — reports keyed on closed_at double-count it');
        $this->assertSame(0, $f->reopened_count,
            'EVIDENCE: reopened_count not incremented on this path, so reopen metrics undercount');
    }

    // ── SCENARIO 8 / 11 — merging ──────────────────────────────────────────

    public function test_probe_ticket_can_be_merged_into_itself(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $t = $this->ticket($tenant);

        $this->post($this->tenantUrl("/tickets/{$t->id}/merge"), ['target_ticket_id' => $t->id]);

        $f = $t->fresh();
        $this->assertTrue((bool) $f->is_merged);
        $this->assertSame($t->id, $f->merged_into_ticket_id,
            'EVIDENCE: self-merge accepted — ticket is its own parent, is_merged=true so it vanishes from every list');
    }

    public function test_probe_child_can_be_merged_into_another_child_creating_a_chain(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $mother = $this->ticket($tenant);
        $childA = $this->ticket($tenant);
        $childB = $this->ticket($tenant);

        $this->post($this->tenantUrl("/tickets/{$childA->id}/merge"), ['target_ticket_id' => $mother->id]);
        $this->post($this->tenantUrl("/tickets/{$childB->id}/merge"), ['target_ticket_id' => $childA->id]);

        $this->assertSame($childA->id, $childB->fresh()->merged_into_ticket_id,
            'EVIDENCE: merged into an already-merged ticket; mother->mergedTickets() will not show childB');
        $this->assertCount(1, $mother->fresh()->mergedTickets,
            'EVIDENCE: the mother ticket shows 1 merged child but 2 tickets were merged away');
    }

    public function test_probe_unmerge_does_not_return_comments_to_the_child(): void
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

        $this->assertSame(0, $child->fresh()->comments()->count(),
            'EVIDENCE: merge MOVES comments and unmerge never moves them back — permanent loss of ticket history');
        $this->assertSame(1, $mother->fresh()->comments()->count());
    }

    public function test_probe_unmerge_reopens_a_child_that_was_closed_before_the_merge(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $mother = $this->ticket($tenant);
        $child = $this->ticket($tenant, ['status' => 'closed', 'closed_at' => now()->subDay()]);

        $this->post($this->tenantUrl("/tickets/{$child->id}/merge"), ['target_ticket_id' => $mother->id]);
        $this->post($this->tenantUrl("/tickets/{$child->id}/unmerge"));

        $this->assertSame('open', $child->fresh()->status,
            'EVIDENCE: unmerge unconditionally forces status=open, resurrecting a ticket that was legitimately closed');
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

    public function test_probe_sla_guard_never_fires_on_the_web_create_path(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        // Remove every policy so the guard SHOULD reject a prioritised ticket.
        SlaPolicy::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->post($this->tenantUrl('/tickets'), [
            'subject' => 'probe', 'description' => 'probe',
            'priority' => 'critical', 'client_id' => $client->id,
        ])->assertSessionHasNoErrors();

        $created = Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->latest('id')->first();
        $this->assertNotNull($created,
            'EVIDENCE: guardSlaPolicy is skipped because $data["tenant_id"] is empty until the model boots');
        $this->assertNull($created->sla_policy_id,
            'EVIDENCE: ticket created with a critical priority and no SLA policy at all');
    }

    public function test_probe_service_layer_guard_does_fire_when_tenant_id_is_supplied(): void
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

    public function test_probe_sla_clock_starts_at_creation_not_assignment(): void
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

    public function test_probe_agent_with_no_departments_sees_every_ticket(): void
    {
        $tenant = $this->tenantOnPlan();
        $itDept = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'IT']);
        $hrDept = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'HR']);

        $hrTicket = $this->ticket($tenant, ['department_id' => $hrDept->id, 'subject' => 'HR SECRET']);

        // An agent deliberately given NO department membership.
        $agent = $this->actor($tenant, 'agent', 'member');
        $this->assertSame(0, $agent->departments()->count());

        $this->get($this->tenantUrl("/tickets/{$hrTicket->id}"))->assertOk();
    }

    public function test_probe_department_restricted_agent_blocked_on_show_but_not_on_edit(): void
    {
        $tenant = $this->tenantOnPlan();
        $it = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'IT']);
        $hr = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'HR']);
        $hrTicket = $this->ticket($tenant, ['department_id' => $hr->id]);

        $agent = $this->actor($tenant, 'agent', 'member');
        $agent->departments()->attach($it->id);

        $this->get($this->tenantUrl("/tickets/{$hrTicket->id}"))->assertForbidden();

        // Same ticket, different verb — no department check on this path.
        $this->get($this->tenantUrl("/tickets/{$hrTicket->id}/edit"))->assertOk();
    }

    public function test_probe_department_restricted_agent_can_download_foreign_attachment(): void
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

        // 403 would mean access control ran. Anything else means only storage stopped us.
        $res = $this->get($this->tenantUrl("/tickets/{$hrTicket->id}/attachments/0"));
        $this->assertNotSame(403, $res->getStatusCode(),
            'EVIDENCE: downloadAttachment performs no department access check');
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
