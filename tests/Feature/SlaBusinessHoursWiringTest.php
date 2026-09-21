<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\AppSetting;
use App\Models\Client;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaCalendar;
use App\Models\SlaCalendarHoliday;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\ServiceReportService;
use App\Services\SlaService;
use App\Services\TicketService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * The three wiring edits, and the guard that says the default path did not move.
 *
 * The guard comes first on purpose. Every mode defaults to 'calendar', which must remain
 * byte-for-byte the old created_at->addHours(N)->addMinutes(hold) expression — that is
 * what makes shipping this safe for every workspace that never touches the new control.
 */
class SlaBusinessHoursWiringTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

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
    }

    private function setWorkspaceTimezone(string $tz): void
    {
        AppSetting::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $this->tenant->id, 'group' => 'general', 'key' => 'timezone'],
            ['value' => $tz]
        );
    }

    private function policy(
        string $priority,
        int $response,
        int $resolution,
        string $responseMode = 'calendar',
        string $resolutionMode = 'calendar',
        ?string $tier = null,
    ): SlaPolicy {
        return SlaPolicy::factory()->create([
            'tenant_id' => $this->tenant->id,
            'priority' => $priority,
            'client_tier' => $tier,
            'is_active' => true,
            'response_time_hours' => $response,
            'resolution_time_hours' => $resolution,
            'response_hours_mode' => $responseMode,
            'resolution_hours_mode' => $resolutionMode,
        ]);
    }

    private function ticket(string $priority, ?string $tier = null): Ticket
    {
        $client = Client::factory()->create(array_filter([
            'tenant_id' => $this->tenant->id,
            'tier' => $tier,
        ]));

        return app(TicketService::class)->createTicket([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'subject' => 'sla', 'description' => 'sla',
            'priority' => $priority,
        ]);
    }

    /** Mon-Fri 09:00-17:00 in the calendar's own zone. */
    private function nineToFive(string $tz = 'UTC'): SlaCalendar
    {
        return SlaCalendar::factory()->nineToFive()->create([
            'tenant_id' => $this->tenant->id,
            'timezone' => $tz,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* The regression guard */
    /* ------------------------------------------------------------------ */

    /**
     * Every mode at 'calendar' — the state of every row in every workspace the moment
     * this ships — must produce the deadline the old expression produced, to the second.
     *
     * Run in a DST zone as well as UTC. The equality holds because SlaClock::alwaysOpen()
     * measures wall-clock window edges and real elapsed minutes inside them; a clock that
     * merely looked right in UTC would lose an hour every spring here.
     */
    public function test_flag_off_produces_identical_due_dates_to_today(): void
    {
        foreach (['UTC', 'America/New_York'] as $tz) {
            $this->setWorkspaceTimezone($tz);

            foreach (SlaPolicy::TIERS as $tier) {
                foreach (SlaPolicy::PRIORITIES as $priority) {
                    SlaPolicy::withoutGlobalScopes()
                        ->where('tenant_id', $this->tenant->id)->delete();

                    $this->policy($priority, 4, 26, 'calendar', 'calendar', $tier);
                    $ticket = $this->ticket($priority, $tier);

                    $context = "{$tz} / {$tier} / {$priority}";

                    $this->assertSame(
                        $ticket->created_at->copy()->addHours(4)->addMinutes(0)->timestamp,
                        $ticket->response_due_at->timestamp,
                        "Calendar mode must equal the pre-change expression: {$context}"
                    );
                    // 26 hours deliberately spans a night and lands on a different local
                    // day, which is where a half-implemented clock first disagrees.
                    $this->assertSame(
                        $ticket->created_at->copy()->addHours(26)->addMinutes(0)->timestamp,
                        $ticket->resolution_due_at->timestamp,
                        "Calendar mode must equal the pre-change expression: {$context}"
                    );
                }
            }
        }
    }

    /**
     * The same equality on the path that carries hold minutes — assignSla() re-running
     * after a priority change, which is the only way holdMinutes is ever non-zero there.
     */
    public function test_flag_off_matches_today_when_hold_minutes_are_carried(): void
    {
        foreach (['UTC', 'America/New_York'] as $tz) {
            $this->setWorkspaceTimezone($tz);
            SlaPolicy::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->delete();

            $this->policy('low', 48, 72);
            $this->policy('critical', 2, 6);

            $service = app(TicketService::class);
            $ticket = $this->ticket('low');

            $service->changeStatus($ticket, 'on_hold');
            $ticket->refresh()->update(['hold_started_at' => now()->subHours(3)]);
            $service->changeStatus($ticket->fresh(), 'in_progress');

            $service->changePriority($ticket->fresh(), 'critical');
            $ticket->refresh();

            $hold = $ticket->getTotalHoldTimeMinutes();
            $this->assertSame(180, $hold);

            $this->assertSame(
                $ticket->created_at->copy()->addHours(2)->addMinutes($hold)->timestamp,
                $ticket->response_due_at->timestamp,
                "Hold minutes must still be plain added minutes under calendar hours ({$tz})"
            );
            $this->assertSame(
                $ticket->created_at->copy()->addHours(6)->addMinutes($hold)->timestamp,
                $ticket->resolution_due_at->timestamp,
                "Hold minutes must still be plain added minutes under calendar hours ({$tz})"
            );
        }
    }

    /* ------------------------------------------------------------------ */
    /* Edit 1 — assignSla() */
    /* ------------------------------------------------------------------ */

    public function test_business_hours_response_deadline_starts_at_the_next_open_minute(): void
    {
        $this->setWorkspaceTimezone('UTC');
        $this->nineToFive('UTC');
        $this->policy('high', 1, 8, 'business', 'calendar');

        // Friday 18:00 — the Freshdesk worked example. We are shut; the clock has not
        // started, so an hour's target is an hour into Monday morning.
        Carbon::setTestNow(Carbon::parse('2026-09-18 18:00:00', 'UTC'));
        $ticket = $this->ticket('high');

        $this->assertSame(
            '2026-09-21 10:00:00',
            $ticket->response_due_at->utc()->format('Y-m-d H:i:s')
        );

        Carbon::setTestNow();
    }

    public function test_a_holiday_is_skipped(): void
    {
        $this->setWorkspaceTimezone('UTC');
        $calendar = $this->nineToFive('UTC');
        SlaCalendarHoliday::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sla_calendar_id' => $calendar->id,
            'name' => 'Company day',
            'month' => 9,
            'day' => 21,
            // Recurring, so this holds in any year the suite is run against.
            'year' => null,
        ]);
        $this->policy('high', 1, 8, 'business', 'calendar');

        Carbon::setTestNow(Carbon::parse('2026-09-18 18:00:00', 'UTC'));
        $ticket = $this->ticket('high');

        $this->assertSame(
            '2026-09-22 10:00:00',
            $ticket->response_due_at->utc()->format('Y-m-d H:i:s'),
            'Monday is closed, so the clock starts on Tuesday'
        );

        Carbon::setTestNow();
    }

    public function test_split_shifts_are_respected(): void
    {
        $this->setWorkspaceTimezone('UTC');
        SlaCalendar::factory()->splitShift()->create([
            'tenant_id' => $this->tenant->id,
            'timezone' => 'UTC',
        ]);
        $this->policy('high', 1, 8, 'business', 'calendar');

        // Tuesday 11:30, 09:00-12:00 + 13:00-17:00: 30 minutes before lunch, 30 after.
        Carbon::setTestNow(Carbon::parse('2026-09-22 11:30:00', 'UTC'));
        $ticket = $this->ticket('high');

        $this->assertSame(
            '2026-09-22 13:30:00',
            $ticket->response_due_at->utc()->format('Y-m-d H:i:s')
        );

        Carbon::setTestNow();
    }

    public function test_assign_sla_falls_back_to_calendar_hours_when_no_calendar_exists(): void
    {
        $this->setWorkspaceTimezone('UTC');
        $this->policy('high', 1, 8, 'business', 'business');

        Log::spy();

        // No calendar at all. Ticket creation must not be the thing that discovers it:
        // blocking happens where business hours are configured, not where a ticket is
        // written, or a workspace that deleted its calendar could file nothing.
        $ticket = $this->ticket('high');

        $this->assertSame(
            $ticket->created_at->copy()->addHours(1)->timestamp,
            $ticket->response_due_at->timestamp
        );
        $this->assertSame(
            $ticket->created_at->copy()->addHours(8)->timestamp,
            $ticket->resolution_due_at->timestamp
        );

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message) => str_contains($message, 'no usable calendar'))
            ->atLeast()->once();
    }

    public function test_changing_a_mode_does_not_move_an_existing_ticket(): void
    {
        $this->setWorkspaceTimezone('UTC');
        $this->nineToFive('UTC');
        $policy = $this->policy('high', 1, 8, 'calendar', 'calendar');

        Carbon::setTestNow(Carbon::parse('2026-09-18 18:00:00', 'UTC'));
        $ticket = $this->ticket('high');
        $originalResponseDue = $ticket->response_due_at->copy();
        Carbon::setTestNow();

        $policy->update(['response_hours_mode' => 'business']);
        $ticket->refresh();

        // Never retroactive. Stored due dates are the migration boundary — last quarter's
        // compliance number must not move because someone changed a dropdown today.
        $this->assertSame($originalResponseDue->timestamp, $ticket->response_due_at->timestamp);
    }

    /* ------------------------------------------------------------------ */
    /* Edit 2 — extendForHold() */
    /* ------------------------------------------------------------------ */

    public function test_extend_for_hold_extends_by_business_minutes(): void
    {
        $this->setWorkspaceTimezone('UTC');
        $this->nineToFive('UTC');
        $policy = $this->policy('high', 2, 4, 'calendar', 'business');

        // Tuesday, resolution deadline parked at 15:00 — two open hours left in the day.
        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sla_policy_id' => $policy->id,
            'resolution_due_at' => Carbon::parse('2026-09-22 15:00:00', 'UTC'),
            'response_due_at' => Carbon::parse('2026-09-22 15:00:00', 'UTC'),
            'first_response_at' => Carbon::parse('2026-09-22 10:00:00', 'UTC'),
        ]);

        app(SlaService::class)->extendForHold($ticket, 6 * 60);
        $ticket->refresh();

        // Six hours of OPEN time from 15:00: two today, four from Wednesday 09:00.
        $this->assertSame(
            '2026-09-23 13:00:00',
            $ticket->resolution_due_at->utc()->format('Y-m-d H:i:s'),
            'A hold across a closed night must not be repaid in hours nobody was working'
        );
    }

    public function test_extend_for_hold_is_unchanged_for_a_calendar_mode_policy(): void
    {
        $this->setWorkspaceTimezone('UTC');
        $this->nineToFive('UTC');
        $policy = $this->policy('high', 2, 4, 'calendar', 'calendar');

        $due = Carbon::parse('2026-09-22 15:00:00', 'UTC');
        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sla_policy_id' => $policy->id,
            'resolution_due_at' => $due->copy(),
            'response_due_at' => $due->copy(),
            'first_response_at' => Carbon::parse('2026-09-22 10:00:00', 'UTC'),
        ]);

        app(SlaService::class)->extendForHold($ticket, 6 * 60);
        $ticket->refresh();

        $this->assertSame(
            $due->copy()->addMinutes(360)->timestamp,
            $ticket->resolution_due_at->timestamp
        );
    }

    public function test_zero_hold_minutes_does_not_move_the_deadline(): void
    {
        $this->setWorkspaceTimezone('UTC');
        $this->nineToFive('UTC');
        $policy = $this->policy('high', 2, 4, 'business', 'business');

        // 02:00 is closed, so addBusinessMinutes($due, 0) would shunt this to 09:00.
        // The `<= 0` early return is what stops it, and that is why it is load-bearing.
        $due = Carbon::parse('2026-09-22 02:00:00', 'UTC');
        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sla_policy_id' => $policy->id,
            'resolution_due_at' => $due->copy(),
            'response_due_at' => $due->copy(),
        ]);

        app(SlaService::class)->extendForHold($ticket, 0);
        $ticket->refresh();

        $this->assertSame($due->timestamp, $ticket->resolution_due_at->timestamp);
        $this->assertSame($due->timestamp, $ticket->response_due_at->timestamp);
    }

    /* ------------------------------------------------------------------ */
    /* Edit 3 — the effective-hours methods, and the verdicts built on them */
    /* ------------------------------------------------------------------ */

    public function test_effective_resolution_hours_are_business_hours_under_a_business_policy(): void
    {
        $this->setWorkspaceTimezone('UTC');
        $this->nineToFive('UTC');
        $policy = $this->policy('high', 2, 16, 'calendar', 'business');

        // Tuesday 15:00 -> Thursday 10:00. Wall clock: 43 hours. Open hours: 2 on
        // Tuesday, 8 on Wednesday, 1 on Thursday = 11.
        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sla_policy_id' => $policy->id,
            'created_at' => Carbon::parse('2026-09-22 15:00:00', 'UTC'),
            'closed_at' => Carbon::parse('2026-09-24 10:00:00', 'UTC'),
            'status' => 'closed',
        ]);

        $this->assertSame(11.0, $ticket->getEffectiveResolutionTimeHours());
    }

    public function test_effective_response_hours_are_business_hours_under_a_business_policy(): void
    {
        $this->setWorkspaceTimezone('UTC');
        $this->nineToFive('UTC');
        $policy = $this->policy('high', 4, 16, 'business', 'calendar');

        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sla_policy_id' => $policy->id,
            'created_at' => Carbon::parse('2026-09-22 15:00:00', 'UTC'),
            'first_response_at' => Carbon::parse('2026-09-23 11:00:00', 'UTC'),
        ]);

        // 2 open hours Tuesday + 2 Wednesday morning = 4, not the 20 on the wall clock.
        $this->assertSame(4.0, $ticket->getEffectiveResponseTimeHours());
    }

    public function test_business_hours_effective_resolution_is_never_negative(): void
    {
        $this->setWorkspaceTimezone('UTC');
        $this->nineToFive('UTC');
        $policy = $this->policy('high', 2, 16, 'calendar', 'business');

        // The hold ran overnight: 16 wall-clock minutes-hours banked, of which none were
        // open. Subtracting them whole from a small business-hours figure would go
        // negative, and a negative resolution time reads as data corruption. Clamped.
        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sla_policy_id' => $policy->id,
            'created_at' => Carbon::parse('2026-09-22 15:00:00', 'UTC'),
            'closed_at' => Carbon::parse('2026-09-23 10:00:00', 'UTC'),
            'status' => 'closed',
            'total_hold_time_minutes' => 16 * 60,
        ]);

        $this->assertSame(0.0, $ticket->getEffectiveResolutionTimeHours());
    }

    /**
     * The one that fails loudly if edit 3 is skipped: a ticket closed inside its
     * business-hours deadline must print a figure no larger than its target. Judging one
     * way and printing the other is the documented "10h / 4h target beside a green MET
     * badge" defect, in the other clock.
     */
    public function test_business_hours_verdict_and_figure_agree(): void
    {
        $this->setWorkspaceTimezone('UTC');
        $this->nineToFive('UTC');
        $this->policy('high', 4, 16, 'business', 'business');

        // Created Friday 18:00 (shut), closed Tuesday 11:00. Wall clock: 89 hours,
        // which would read "89h / 16h target" and be scored MISSED if edit 3 were
        // omitted. Business clock: 8 Monday + 2 Tuesday = 10 hours, comfortably met.
        Carbon::setTestNow(Carbon::parse('2026-09-18 18:00:00', 'UTC'));
        $ticket = $this->ticket('high');
        Carbon::setTestNow();

        $ticket->update([
            'status' => 'closed',
            'closed_at' => Carbon::parse('2026-09-22 11:00:00', 'UTC'),
            'first_response_at' => Carbon::parse('2026-09-21 10:00:00', 'UTC'),
        ]);

        $report = app(SlaService::class)->getComplianceReport(
            Carbon::parse('2026-09-01', 'UTC'),
            Carbon::parse('2026-09-30', 'UTC'),
            $this->tenant->id
        );

        $row = $report['rows']->first();

        $this->assertNotNull($row);
        $this->assertSame('met', $row['resolution_status']);
        $this->assertLessThanOrEqual($row['resolution_target'], $row['resolution_hours']);
        $this->assertSame('met', $row['response_status']);
        $this->assertLessThanOrEqual($row['response_target'], $row['response_hours']);

        // And the row says which clock it was judged on, so the screen can too.
        $this->assertSame('business', $row['resolution_clock']);
        $this->assertSame('business', $row['response_clock']);
    }

    /**
     * ServiceReportService bypassed both effective-hours methods and subtracted hold from
     * closed_at while comparing against a deadline extendForHold() had ALREADY pushed out
     * by that same hold. Two tickets, one report, two different verdicts.
     */
    public function test_service_report_verdict_matches_the_compliance_report(): void
    {
        $this->setWorkspaceTimezone('UTC');
        $policy = $this->policy('high', 2, 4, 'calendar', 'calendar');

        // Closed 3 hours after a deadline that was already extended for a 6-hour hold.
        // Double-counting the hold would score this MET; it is a miss.
        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'sla_policy_id' => $policy->id,
            'created_at' => Carbon::parse('2026-09-22 09:00:00', 'UTC'),
            'resolution_due_at' => Carbon::parse('2026-09-22 19:00:00', 'UTC'),
            'closed_at' => Carbon::parse('2026-09-22 22:00:00', 'UTC'),
            'status' => 'closed',
            'total_hold_time_minutes' => 6 * 60,
        ]);

        $report = app(ServiceReportService::class)->generate($ticket->fresh());

        $this->assertFalse($report->report_data['sla_compliance']);

        $compliance = app(SlaService::class)->getComplianceReport(
            Carbon::parse('2026-09-01', 'UTC'),
            Carbon::parse('2026-09-30', 'UTC'),
            $this->tenant->id
        );

        $this->assertSame('missed', $compliance['rows']->first()['resolution_status']);
    }
}
