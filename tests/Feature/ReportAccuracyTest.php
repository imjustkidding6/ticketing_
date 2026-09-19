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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The defects behind "Please fix the reports, it wasnt accurate" and
 * "can you fix the sla compliance report" (QA scenarios S4, S6, S9).
 *
 * Three separate ways the same page could contradict itself: a CSV printing UTC
 * beside a screen printing tenant time, a response verdict judged against a
 * different target from the hours printed next to it, and a historical figure
 * that moved between two viewings.
 */
class ReportAccuracyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->create(['slug' => 'business', 'features' => PlanFeature::forPlan('business')]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $this->tenant = Tenant::factory()->create(['license_id' => $license->id]);

        $user = User::factory()->create();
        $this->tenant->addUser($user, 'owner');
        $this->actingAs($user)->withTenant($this->tenant)
            ->withSession(['current_tenant_id' => $this->tenant->id]);

        // Eight hours ahead of the server, so a UTC value and a tenant-local one
        // can never be mistaken for each other.
        AppSetting::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'group' => 'general', 'key' => 'timezone', 'value' => 'Asia/Manila',
        ]);

        $this->client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function policy(int $responseHours = 4, int $resolutionHours = 24): SlaPolicy
    {
        return SlaPolicy::factory()->create([
            'tenant_id' => $this->tenant->id,
            'priority' => null,
            'client_tier' => null,
            'is_active' => true,
            'response_time_hours' => $responseHours,
            'resolution_time_hours' => $resolutionHours,
        ]);
    }

    /**
     * D2 / S9 — the CSV must print the same instant the screen does.
     */
    public function test_sla_compliance_csv_prints_tenant_local_timestamps(): void
    {
        $policy = $this->policy();

        // 2026-08-26 23:00 UTC is 2026-08-27 07:00 in Manila — the two differ in
        // the date as well as the hour, so an off-by-offset export is unmistakable.
        Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'sla_policy_id' => $policy->id,
            'status' => 'closed',
            'priority' => 'medium',
            'created_at' => Carbon::parse('2026-08-26 20:00:00', 'UTC'),
            'first_response_at' => Carbon::parse('2026-08-26 21:00:00', 'UTC'),
            'closed_at' => Carbon::parse('2026-08-26 23:00:00', 'UTC'),
            'response_due_at' => Carbon::parse('2026-08-27 00:00:00', 'UTC'),
            'resolution_due_at' => Carbon::parse('2026-08-27 20:00:00', 'UTC'),
        ]);

        $csv = $this->csv('/reports/export/sla-compliance?from=2026-08-20&to=2026-08-31');

        $this->assertStringContainsString('2026-08-27 07:00', $csv, 'Closed time should be tenant-local.');
        $this->assertStringContainsString('2026-08-27 04:00', $csv, 'Created time should be tenant-local.');
        $this->assertStringNotContainsString('2026-08-26 23:00', $csv, 'Raw UTC must not reach the CSV.');
    }

    public function test_reopen_csv_prints_tenant_local_timestamps(): void
    {
        // The reopen report is gated on ticket_reopening (Enterprise).
        $this->tenant->license->plan->update(['features' => PlanFeature::forPlan('enterprise')]);

        Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'status' => 'open',
            'priority' => 'medium',
            'reopened_count' => 1,
            'created_at' => Carbon::parse('2026-08-26 20:00:00', 'UTC'),
            'first_closed_at' => Carbon::parse('2026-08-26 23:00:00', 'UTC'),
            'last_reopened_at' => Carbon::parse('2026-08-26 23:30:00', 'UTC'),
        ]);

        $csv = $this->csv('/reports/export/reopens?from=2026-08-20&to=2026-08-31');

        $this->assertStringContainsString('2026-08-27 07:00', $csv);
        $this->assertStringContainsString('2026-08-27 07:30', $csv);
        $this->assertStringNotContainsString('2026-08-26 23:00', $csv);
    }

    /**
     * D3 / S4 — the printed hours and the badge must be the same comparison.
     *
     * Created T0, held 8h, first replied at T0+10h against a 4h target. Hold has
     * pushed response_due_at out to T0+12h, so the old code called this MET while
     * printing "10 / 4" in the columns beside it.
     */
    public function test_response_verdict_matches_the_hours_printed_next_to_it(): void
    {
        $policy = $this->policy(responseHours: 4);
        $t0 = Carbon::parse('2026-08-01 00:00:00', 'UTC');

        Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'sla_policy_id' => $policy->id,
            'status' => 'closed',
            'priority' => 'medium',
            'created_at' => $t0,
            'first_response_at' => $t0->copy()->addHours(10),
            'closed_at' => $t0->copy()->addHours(11),
            'total_hold_time_minutes' => 480,
            'response_due_at' => $t0->copy()->addHours(12),   // 4h target + 8h hold
            'resolution_due_at' => $t0->copy()->addHours(32),
        ]);

        $report = app(SlaService::class)->getComplianceReport(
            Carbon::parse('2026-07-01', 'UTC'),
            Carbon::parse('2026-09-01', 'UTC'),
        );

        $row = $report['rows']->first();

        $this->assertSame(10.0, (float) $row['response_hours']);
        $this->assertSame(4, (int) $row['response_target']);
        $this->assertSame('missed', $row['response_status'], '10h against a 4h target cannot be a MET.');
        $this->assertSame(0, $report['response_met']);
        $this->assertSame(1, $report['response_missed']);
    }

    public function test_a_reply_inside_the_raw_target_is_still_met(): void
    {
        $policy = $this->policy(responseHours: 4);
        $t0 = Carbon::parse('2026-08-01 00:00:00', 'UTC');

        Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'sla_policy_id' => $policy->id,
            'status' => 'closed',
            'priority' => 'medium',
            'created_at' => $t0,
            'first_response_at' => $t0->copy()->addHours(2),
            'closed_at' => $t0->copy()->addHours(5),
            'response_due_at' => $t0->copy()->addHours(4),
            'resolution_due_at' => $t0->copy()->addHours(24),
        ]);

        $report = app(SlaService::class)->getComplianceReport(
            Carbon::parse('2026-07-01', 'UTC'),
            Carbon::parse('2026-09-01', 'UTC'),
        );

        $this->assertSame('met', $report['rows']->first()['response_status']);
        $this->assertSame(100.0, (float) $report['response_compliance']);
    }

    /**
     * D4 — the denominator excludes policy-less closed tickets. The maths is
     * unchanged; what must not happen again is the exclusion going unreported.
     */
    public function test_closed_tickets_without_a_policy_are_counted_and_disclosed(): void
    {
        $policy = $this->policy();
        $t0 = Carbon::parse('2026-08-01 00:00:00', 'UTC');

        Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'sla_policy_id' => $policy->id,
            'status' => 'closed',
            'priority' => 'medium',
            'created_at' => $t0,
            'first_response_at' => $t0->copy()->addHour(),
            'closed_at' => $t0->copy()->addHours(3),
            'response_due_at' => $t0->copy()->addHours(4),
            'resolution_due_at' => $t0->copy()->addHours(24),
        ]);

        // Two closed tickets that never carried a policy — invisible until now.
        Ticket::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'sla_policy_id' => null,
            'status' => 'closed',
            'priority' => 'medium',
            'created_at' => $t0,
            'closed_at' => $t0->copy()->addHours(3),
        ]);

        $report = app(SlaService::class)->getComplianceReport(
            Carbon::parse('2026-07-01', 'UTC'),
            Carbon::parse('2026-09-01', 'UTC'),
        );

        $this->assertSame(1, $report['total_with_sla']);
        $this->assertSame(2, $report['closed_without_sla']);

        $this->get($this->tenantUrl('/reports/sla-compliance?from=2026-07-01&to=2026-09-01'))
            ->assertOk()
            ->assertSee('2 more closed tickets had no SLA policy', false);
    }

    /**
     * D5 / S6 — a closed ticket's resolution time is a historical fact.
     */
    public function test_a_closed_ticket_with_a_stuck_hold_does_not_shrink_over_time(): void
    {
        $t0 = Carbon::parse('2026-08-01 00:00:00', 'UTC');

        // Reached a terminal state by a path that never cleared hold_started_at —
        // Assistant\UpdateStatus writes status directly.
        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'status' => 'closed',
            'priority' => 'medium',
            'created_at' => $t0,
            'in_progress_at' => $t0,
            'closed_at' => $t0->copy()->addHours(10),
            'total_hold_time_minutes' => 60,
            'hold_started_at' => $t0->copy()->addHours(2),
        ]);

        Carbon::setTestNow($t0->copy()->addDays(1));
        $today = $ticket->fresh()->getEffectiveResolutionTimeHours();
        $todayHold = $ticket->fresh()->getTotalHoldTimeMinutes();

        Carbon::setTestNow($t0->copy()->addDays(8));
        $aWeekLater = $ticket->fresh()->getEffectiveResolutionTimeHours();

        Carbon::setTestNow();

        $this->assertSame(60, $todayHold, 'Only the banked hold counts once a ticket is terminal.');
        $this->assertSame($today, $aWeekLater, 'A closed ticket\'s resolution time must not move.');
        $this->assertSame(9.0, (float) $today);
    }

    /**
     * The guard must not freeze a live hold: a ticket still on_hold is still
     * accruing, which is what the breach sweep and the deadline extension rely on.
     */
    public function test_a_live_hold_still_accrues(): void
    {
        $t0 = Carbon::parse('2026-08-01 00:00:00', 'UTC');

        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'status' => 'on_hold',
            'priority' => 'medium',
            'created_at' => $t0,
            'total_hold_time_minutes' => 30,
            'hold_started_at' => $t0->copy()->addHour(),
        ]);

        Carbon::setTestNow($t0->copy()->addHours(3));
        $this->assertSame(150, $ticket->fresh()->getTotalHoldTimeMinutes());
        Carbon::setTestNow();
    }

    private function csv(string $path): string
    {
        $response = $this->get($this->tenantUrl($path));
        $response->assertOk();

        return $response->streamedContent();
    }
}
