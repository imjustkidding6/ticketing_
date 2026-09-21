<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\AppSetting;
use App\Models\Client;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SlaService;
use App\Services\TenantRoleService;
use App\Services\TicketService;
use App\Support\TenantTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The SLA panel on the ticket page. Its deadlines were computed, stored, judged for
 * compliance and used to fire breach warnings long before an agent could see any of
 * them — the OVERDUE badge was the first signal, and by then it was too late.
 *
 * The panel is display-only, so the tests here are mostly about it never printing a
 * number the rest of the system contradicts: a paused deadline, a verdict judged on a
 * different figure from the one printed, or a deadline that is stale because the panel
 * was not swapped in when the status card was.
 */
class TicketSlaPanelTest extends TestCase
{
    use RefreshDatabase;

    private const HEADING = 'SLA';

    private const NULL_POLICY_COPY = 'No SLA policy applied.';

    private const PAUSED_COPY = 'excluded from breach alerts';

    private Tenant $tenant;

    private User $user;

    private function bootTenant(string $planSlug = 'business', bool $withPolicy = true): void
    {
        $plan = Plan::factory()->create(['slug' => $planSlug, 'features' => PlanFeature::forPlan($planSlug)]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $this->tenant = Tenant::factory()->create(['license_id' => $license->id]);

        if ($withPolicy) {
            SlaPolicy::factory()->create([
                'tenant_id' => $this->tenant->id,
                'priority' => null,
                'client_tier' => null,
                'is_active' => true,
                'response_time_hours' => 4,
                'resolution_time_hours' => 24,
            ]);
        }

        app(TenantRoleService::class)->setupDefaultRoles($this->tenant);

        $this->user = User::factory()->create();
        $this->tenant->addUser($this->user, 'owner');
        $this->actingAs($this->user)->withTenant($this->tenant)
            ->withSession(['current_tenant_id' => $this->tenant->id]);
    }

    /** A ticket created the way the product creates one, so the SLA columns are real. */
    private function ticket(array $attributes = []): Ticket
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $ticket = app(TicketService::class)->createTicket([
            'client_id' => $client->id,
            'subject' => 'Printer is on fire',
            'description' => 'It is genuinely on fire.',
            'priority' => 'medium',
        ]);

        if ($attributes) {
            $ticket->forceFill($attributes)->save();
        }

        return $ticket->fresh();
    }

    private function showTicket(Ticket $ticket): TestResponse
    {
        return $this->get($this->tenantUrl('/tickets/'.$ticket->id));
    }

    public function test_panel_shows_both_deadlines_in_the_workspace_timezone(): void
    {
        $this->bootTenant();

        AppSetting::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'group' => 'general', 'key' => 'timezone', 'value' => 'Asia/Manila',
        ]);

        $ticket = $this->ticket();

        $response = $this->showTicket($ticket);

        $response->assertOk();
        $response->assertSee(self::HEADING);
        $response->assertSee(TenantTime::format($ticket->resolution_due_at));
        $response->assertSee(TenantTime::format($ticket->response_due_at));

        // The UTC rendering of the same instant must not appear — eight hours adrift is
        // the regression this session already paid for once on the CSV exports.
        $utc = $ticket->resolution_due_at->copy()->setTimezone('UTC')->format('M d, Y g:i A');
        if ($utc !== TenantTime::format($ticket->resolution_due_at)) {
            $response->assertDontSee($utc);
        }
    }

    public function test_panel_is_absent_for_a_starter_tenant(): void
    {
        $this->bootTenant('start', withPolicy: false);

        $ticket = $this->ticket();

        $response = $this->showTicket($ticket);

        $response->assertOk();
        // Asserting on the copy, not on a truthy response: a Starter tenant must render
        // no card, no heading and no stray chrome. The fragment wrapper stays (the swap
        // contract is uniform across tenants) but renders empty, which is why the
        // fragments payload is asserted blank rather than absent.
        $response->assertDontSee(self::NULL_POLICY_COPY);
        $response->assertDontSee('calendar hours');
        $response->assertDontSee('Set targets for this tier');

        $fragments = $this->getJson($this->tenantUrl('/tickets/'.$ticket->id.'/fragments'));
        $fragments->assertOk();
        $this->assertSame('', trim($fragments->json('sla')));
    }

    public function test_panel_visible_to_an_agent_without_sla_policy_permissions(): void
    {
        $this->bootTenant();

        $agent = User::factory()->create();
        $this->tenant->addUser($agent, 'member');
        app(TenantRoleService::class)->syncRole($agent, 'agent', $this->tenant);

        $ticket = $this->ticket(['assigned_to' => $agent->id]);

        $this->actingAs($agent)->withTenant($this->tenant)
            ->withSession(['current_tenant_id' => $this->tenant->id]);

        $response = $this->showTicket($ticket);

        $response->assertOk();
        // Visibility follows the feature, not a permission. A role that can be paged for
        // a breach but not told the deadline would be absurd.
        $response->assertSee(self::HEADING);
        $response->assertSee(TenantTime::format($ticket->resolution_due_at));
        $this->assertFalse($agent->can('update sla policies'));
    }

    public function test_null_policy_renders_the_explanation_not_blanks(): void
    {
        $this->bootTenant(withPolicy: false);

        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'sla_policy_id' => null,
            'response_due_at' => null,
            'resolution_due_at' => null,
        ]);

        $response = $this->showTicket($ticket);

        $response->assertOk();
        $response->assertSee(self::NULL_POLICY_COPY);
        $response->assertDontSee('overdue by');
    }

    public function test_policy_present_but_due_dates_null_uses_the_same_explanation(): void
    {
        $this->bootTenant();

        $ticket = $this->ticket();
        // An older row: a policy was matched, but no deadline was ever stamped. Left to
        // the countdown branch this prints "in -55 years".
        $ticket->forceFill(['response_due_at' => null, 'resolution_due_at' => null])->save();

        $response = $this->showTicket($ticket->fresh());

        $response->assertOk();
        $response->assertSee(self::NULL_POLICY_COPY);
        $response->assertDontSee('overdue by');
    }

    public function test_paused_ticket_shows_no_countdown(): void
    {
        $this->bootTenant();

        $ticket = $this->ticket();
        app(TicketService::class)->changeStatus($ticket, 'on_hold');

        $this->travel(3)->hours();

        $response = $this->showTicket($ticket->fresh());

        $response->assertOk();
        $response->assertSee('Paused');
        $response->assertSee(self::PAUSED_COPY);
        // A number wrong by a growing amount is worse than no number.
        $response->assertDontSee('overdue by');
        $response->assertSee('Projected:');
    }

    public function test_paused_ticket_projects_the_deadline_forward(): void
    {
        $this->bootTenant();

        // Frozen before the hold starts, so hold_started_at and "now" share one instant and
        // the assertion can be exact rather than within a tolerance that would hide drift.
        Carbon::setTestNow(Carbon::parse('2026-09-21 09:00:00'));

        $ticket = $this->ticket();
        $storedDue = $ticket->resolution_due_at->copy();

        app(TicketService::class)->changeStatus($ticket, 'on_hold');
        $this->travel(3)->hours();

        $projected = $ticket->fresh()->projectedResolutionDueAt();

        $this->assertSame(
            $storedDue->addHours(3)->timestamp,
            $projected->timestamp,
            'Three hours on hold must project the deadline three hours out.'
        );

        $this->showTicket($ticket->fresh())->assertSee(TenantTime::format($projected));
    }

    public function test_projection_is_never_persisted(): void
    {
        $this->bootTenant();

        $ticket = $this->ticket();
        app(TicketService::class)->changeStatus($ticket, 'on_hold');
        $stored = $ticket->fresh()->resolution_due_at->copy();

        $this->travel(3)->hours();
        $this->showTicket($ticket->fresh())->assertOk();

        $this->assertTrue(
            $ticket->fresh()->resolution_due_at->eq($stored),
            'Rendering the panel must not write the projection back to the ticket.'
        );
    }

    /**
     * The important one: it proves the projection applies the same arithmetic as
     * SlaService::extendForHold(), so the panel never showed a number the system then
     * contradicted the moment the hold ended.
     */
    public function test_ending_the_hold_makes_the_projection_the_real_deadline(): void
    {
        $this->bootTenant();

        $ticket = $this->ticket();
        app(TicketService::class)->changeStatus($ticket, 'on_hold');

        $this->travel(3)->hours();

        $projected = $ticket->fresh()->projectedResolutionDueAt()->copy();
        $projectedResponse = $ticket->fresh()->projectedResponseDueAt()->copy();

        // Ending the hold with 'open', not 'in_progress': moving to in_progress also stamps
        // first_response_at, and extendForHold() then deliberately leaves response_due_at
        // alone because that clock has stopped. Using it here would test two events at once.
        app(TicketService::class)->changeStatus($ticket->fresh(), 'open');

        $after = $ticket->fresh();

        // Exact, not within a delta: the test clock is frozen, so the projection and the
        // banked value are computed from the same instant. A tolerance here would hide
        // precisely the drift this test exists to catch.
        $this->assertSame($projected->timestamp, $after->resolution_due_at->timestamp);
        $this->assertSame($projectedResponse->timestamp, $after->response_due_at->timestamp);
    }

    public function test_fragments_endpoint_returns_the_sla_key(): void
    {
        $this->bootTenant();

        $ticket = $this->ticket();

        $before = $this->getJson($this->tenantUrl('/tickets/'.$ticket->id.'/fragments'));
        $before->assertOk()->assertJsonStructure(['badges', 'tasks', 'status', 'history', 'sla']);
        $this->assertStringNotContainsString('Paused', $before->json('sla'));

        // Pausing happens from the status card directly above the panel. If the panel is
        // not swapped too, the agent is left looking at a live countdown on a dead clock.
        app(TicketService::class)->changeStatus($ticket, 'on_hold');

        $after = $this->getJson($this->tenantUrl('/tickets/'.$ticket->id.'/fragments'));
        $after->assertOk();
        $this->assertStringContainsString('Paused', $after->json('sla'));
        $this->assertStringContainsString(self::PAUSED_COPY, $after->json('sla'));
    }

    /**
     * Response is judged RAW and resolution on the effective (hold-excluding) figure —
     * exactly the split in SlaService::getComplianceReport(). Judging one way and
     * printing the other is the "10h / 4h target beside a green MET badge" defect,
     * re-created on a new surface.
     */
    public function test_response_verdict_matches_the_compliance_report(): void
    {
        $this->bootTenant();

        $ticket = $this->ticket();
        $created = $ticket->created_at->copy();

        $ticket->forceFill([
            'first_response_at' => $created->copy()->addHours(6),
            'closed_at' => $created->copy()->addHours(20),
            'status' => 'closed',
            'total_hold_time_minutes' => 600,
        ])->save();

        $report = app(SlaService::class)->getComplianceReport(
            $created->copy()->subDay(),
            $created->copy()->addWeek(),
            $this->tenant->id
        );

        $row = collect($report['rows'])->firstWhere('ticket.id', $ticket->id);
        $this->assertNotNull($row, 'The ticket should appear on the compliance report.');

        // Response: 6h raw against a 4h target = missed, even though 6h minus 10h of
        // hold would be zero and would have read as met.
        $this->assertSame('missed', $row['response_status']);
        // Resolution: 20h elapsed minus 10h hold = 10h against a 24h target = met.
        $this->assertSame('met', $row['resolution_status']);

        $response = $this->showTicket($ticket->fresh());
        $response->assertOk();
        $response->assertSee('Missed');
        $response->assertSee('Met');
        $response->assertSee(Ticket::formatHours($row['resolution_hours']));
    }

    public function test_closed_ticket_shows_effective_resolution_hours(): void
    {
        $this->bootTenant();

        $ticket = $this->ticket();
        $created = $ticket->created_at->copy();

        $ticket->forceFill([
            'closed_at' => $created->copy()->addHours(20),
            'status' => 'closed',
            'total_hold_time_minutes' => 600,
        ])->save();

        $fresh = $ticket->fresh();
        $this->assertSame(10.0, $fresh->getEffectiveResolutionTimeHours());

        $response = $this->showTicket($fresh);
        $response->assertOk();
        // The effective figure, not the raw created_at -> closed_at 20h.
        $response->assertSee(Ticket::formatHours($fresh->getEffectiveResolutionTimeHours()));
    }

    /**
     * isOverdue() has no pause awareness, so a paused, past-due ticket shows OVERDUE
     * while the panel says paused. That is a known disagreement and deliberately not
     * fixed in a display commit — isOverdue() is the semantic twin of the breach
     * sweep's wall-clock test. Pinned here so commit 3 inherits it with evidence.
     */
    public function test_overdue_badge_behaviour_is_unchanged(): void
    {
        $this->bootTenant();

        $ticket = $this->ticket();
        app(TicketService::class)->changeStatus($ticket, 'on_hold');

        $ticket->fresh()->forceFill(['resolution_due_at' => Carbon::now()->subHour()])->save();

        $fresh = $ticket->fresh();

        $this->assertTrue($fresh->isOverdue(), 'isOverdue() must still be pause-unaware.');

        $response = $this->showTicket($fresh);
        $response->assertOk();
        $response->assertSee('Paused');
        // The badge still flags it; the panel still refuses to print a countdown.
        $response->assertDontSee('overdue by');
    }

    public function test_panel_is_not_rendered_on_the_public_portal(): void
    {
        $this->bootTenant();

        $ticket = $this->ticket();

        $response = $this->get($this->tenantUrl('/track-ticket/'.$ticket->tracking_token));

        $response->assertOk();
        // response_due_at is an internal commitment against an internal target; publishing
        // it makes it a contractual promise the tenant never chose to make, and discloses
        // the client's tier by inference.
        $response->assertDontSee(self::NULL_POLICY_COPY);
        $response->assertDontSee(self::PAUSED_COPY);
        $response->assertDontSee('id="ticket-sla"', false);
        $response->assertDontSee(TenantTime::format($ticket->resolution_due_at));
    }
}
