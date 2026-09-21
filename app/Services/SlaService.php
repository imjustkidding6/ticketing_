<?php

namespace App\Services;

use App\Models\SlaCalendar;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Support\SlaClock;
use App\Support\TenantTime;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class SlaService
{
    /**
     * Assign an SLA policy to a ticket and set due dates.
     */
    public function assignSla(Ticket $ticket): void
    {
        $policy = SlaPolicy::findForTicket($ticket);

        if (! $policy) {
            return;
        }

        // Time already spent on hold is not the team's to answer for, so it is
        // added to the deadline. This keeps the stored due date and the report's
        // effective-hours calculation in agreement: effective <= target holds
        // exactly when closed_at <= resolution_due_at.
        $holdMinutes = $ticket->getTotalHoldTimeMinutes();

        // One clock per target, chosen by that target's own mode. Calendar mode goes
        // through SlaClock::alwaysOpen() rather than branching back to addHours(): one
        // code path with two clocks, which is what makes the equality with the old
        // expression a property worth testing instead of a tautology.
        $responseClock = $this->clockFor($policy->response_hours_mode);
        $resolutionClock = $this->clockFor($policy->resolution_hours_mode);

        $ticket->update([
            'sla_policy_id' => $policy->id,
            'response_due_at' => $responseClock->addBusinessMinutes(
                $ticket->created_at, $policy->response_time_hours * 60 + $holdMinutes
            ),
            'resolution_due_at' => $resolutionClock->addBusinessMinutes(
                $ticket->created_at, $policy->resolution_time_hours * 60 + $holdMinutes
            ),
        ]);
    }

    /**
     * Push the deadlines out by a period the ticket spent on hold.
     *
     * The extension is measured in the target's OWN clock: a 6-hour hold on a
     * 09:00-17:00 calendar starting at 15:00 moves the deadline to 13:00 the next day,
     * not to 21:00 tonight. $holdMinutes itself stays wall-clock — it is what
     * changeStatus() banks and what getTotalHoldTimeMinutes() reports, and converting it
     * here would change hold accounting for calendar-mode policies too.
     */
    public function extendForHold(Ticket $ticket, int $holdMinutes): void
    {
        // Load-bearing, and more so than it looks: addBusinessMinutes($due, 0) returns
        // the NEXT OPEN instant rather than $due, so without this early return a
        // zero-minute hold would shunt a deadline that fell at 02:00 forward to 09:00.
        if ($holdMinutes <= 0) {
            return;
        }

        $policy = $ticket->slaPolicy;
        $updates = [];

        if ($ticket->response_due_at && ! $ticket->first_response_at) {
            $updates['response_due_at'] = $this->clockFor($policy?->response_hours_mode)
                ->addBusinessMinutes($ticket->response_due_at, $holdMinutes);
        }

        if ($ticket->resolution_due_at) {
            $updates['resolution_due_at'] = $this->clockFor($policy?->resolution_hours_mode)
                ->addBusinessMinutes($ticket->resolution_due_at, $holdMinutes);
        }

        if ($updates) {
            $ticket->update($updates);
        }
    }

    /**
     * The clock a target in this mode is measured on. The single fallback point, and
     * public because Ticket::getEffective*TimeHours() must measure in exactly the same
     * clock the deadline was set in — a second resolution path is how the printed figure
     * and the met/missed verdict drift apart.
     *
     * This NEVER throws. It runs inside ticket creation, so a workspace that deleted its
     * calendar must still be able to file a ticket; business hours is blocked where it is
     * configured (the tier editor and the calendar editor), not where a ticket is written.
     */
    public function clockFor(?string $mode): SlaClock
    {
        $tz = TenantTime::timezone();

        if ($mode !== 'business') {
            return SlaClock::alwaysOpen($tz);
        }

        $calendar = SlaCalendar::query()->default()->first();

        if (! $calendar || ! $calendar->hasOpenWindows()) {
            Log::warning('SLA business-hours mode with no usable calendar; falling back to calendar hours.', [
                'tenant_id' => session('current_tenant_id'),
            ]);

            return SlaClock::alwaysOpen($tz);
        }

        try {
            return SlaClock::fromCalendar($calendar);
        } catch (InvalidArgumentException $e) {
            // hasOpenWindows() already ruled out the empty calendar; this catches the
            // pathological one (every window zero-length) that EmptySlaCalendarException
            // also covers. Ticket creation must not be the thing that discovers it.
            Log::warning('SLA calendar could not be used; falling back to calendar hours.', [
                'tenant_id' => session('current_tenant_id'),
                'error' => $e->getMessage(),
            ]);

            return SlaClock::alwaysOpen($tz);
        }
    }

    /**
     * Get overdue tickets.
     *
     * @return Collection<int, Ticket>
     */
    public function getOverdueTickets(): Collection
    {
        return Ticket::withoutGlobalScopes()
            ->open()
            ->whereNotIn('status', Ticket::PAUSED_STATUSES)
            ->notMerged()
            ->notSpam()
            ->where(function ($q) {
                $q->where('resolution_due_at', '<', now())
                    ->orWhere(function ($q2) {
                        $q2->whereNull('first_response_at')
                            ->where('response_due_at', '<', now());
                    });
            })
            // Cross-tenant query, so the eager loads must be too — a scoped
            // relation on an unscoped row resolves to null for every other tenant.
            ->with([
                'client' => fn ($q) => $q->withoutGlobalScopes(),
                'assignee',
            ])
            ->get();
    }

    /**
     * Get overdue tickets that haven't been notified yet.
     *
     * @return Collection<int, Ticket>
     */
    public function getTicketsNeedingBreachWarning(): Collection
    {
        return $this->breachWarningQuery()->get();
    }

    /**
     * The breach-warning set as a query, so callers can chunk rather than load
     * every overdue ticket across every tenant into memory at once.
     *
     * @return Builder<Ticket>
     */
    public function breachWarningQuery(): Builder
    {
        return Ticket::withoutGlobalScopes()
            ->open()
            ->whereNotIn('status', Ticket::PAUSED_STATUSES)
            ->notMerged()
            ->notSpam()
            ->whereNull('sla_breach_notified_at')
            ->where(function ($q) {
                $q->where('resolution_due_at', '<', now())
                    ->orWhere(function ($q2) {
                        $q2->whereNull('first_response_at')
                            ->where('response_due_at', '<', now());
                    });
            })
            ->with(['client', 'assignee']);
    }

    /**
     * Get SLA compliance report.
     *
     * @return array<string, mixed>
     */
    public function getComplianceReport(?Carbon $from, ?Carbon $to, ?int $tenantId): array
    {
        $from = $from ?? TenantTime::startOfDaysAgo(30);
        $to = $to ?? TenantTime::now();

        // $tenantId is required, not defaulted: the caller must state the scope it
        // wants. Reading it from the ambient TenantScope is the defect this fixes —
        // an unset session silently widened the report to every tenant.
        $closedTickets = Ticket::withoutGlobalScopes()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->notMerged()
            ->notSpam()
            // The eager loads carry each related model's own global scope, so on the
            // platform path a foreign tenant's policy would resolve to null and its
            // ticket would silently score "na" instead of met/missed — rows right,
            // percentage wrong.
            ->with($tenantId === null
                ? [
                    'client' => fn ($q) => $q->withoutGlobalScopes(),
                    'department' => fn ($q) => $q->withoutGlobalScopes(),
                    'assignee',
                    'slaPolicy' => fn ($q) => $q->withoutGlobalScopes(),
                ]
                : ['client', 'department', 'assignee', 'slaPolicy'])
            ->where('status', 'closed')
            ->whereNotNull('sla_policy_id')
            ->whereBetween('closed_at', [$from, $to])
            ->get();

        // Closed tickets with no policy stamped on them are not part of the
        // percentage — no target was ever promised for them. Counting them would be
        // inventing a commitment; hiding them makes compliance read better than the
        // service actually was, which is the complaint this report attracted. So the
        // denominator is unchanged and the exclusion is reported alongside it.
        $withoutSla = Ticket::withoutGlobalScopes()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->notMerged()
            ->notSpam()
            ->where('status', 'closed')
            ->whereNull('sla_policy_id')
            ->whereBetween('closed_at', [$from, $to])
            ->count();

        $rows = $closedTickets->map(function (Ticket $t) {
            // Resolution time excludes hold time. Since assignSla()/extendForHold()
            // also push resolution_due_at out by the hold period, this comparison is
            // now equivalent to closed_at <= resolution_due_at — the same rule the
            // breach scheduler applies. Keep them in step if either changes.
            $resolutionHours = $t->getEffectiveResolutionTimeHours();
            $resolutionTarget = $t->slaPolicy?->resolution_time_hours;
            $resolutionMet = $resolutionHours !== null && $resolutionTarget !== null && $resolutionHours <= $resolutionTarget;
            $resolutionMissed = $resolutionHours !== null && $resolutionTarget !== null && $resolutionHours > $resolutionTarget;

            // Response time is reported raw (created_at -> first_response_at) with hold
            // NOT subtracted, per CLAUDE.md. The verdict must therefore be judged on
            // the same raw figure against the same printed target: response_due_at has
            // been pushed out by extendForHold(), so comparing against it produced rows
            // reading "10h / 4h target" beside a MET badge. response_due_at is left
            // untouched — the breach scheduler depends on the hold extension.
            // ...but "raw" means "in whichever clock the target is stated in". Under a
            // business-hours response target the printed hours must be business hours
            // too, or a 4h target met at 10:00 the next morning prints "17h / 4h" beside
            // a MET badge — the same defect in the other clock. Still no hold subtracted.
            $responseHours = $t->first_response_at
                ? ($t->slaPolicy?->responseClockIsBusiness()
                    ? app(self::class)->clockFor('business')->businessHoursBetween($t->created_at, $t->first_response_at)
                    : round($t->created_at->diffInHours($t->first_response_at, false), 2))
                : null;
            $responseTarget = $t->slaPolicy?->response_time_hours;
            $responseMet = $responseHours !== null && $responseTarget !== null && $responseHours <= $responseTarget;
            $responseMissed = $responseHours !== null && $responseTarget !== null && $responseHours > $responseTarget;

            $responseStatus = $responseMet ? 'met' : ($responseMissed ? 'missed' : 'na');
            $resolutionStatus = $resolutionMet ? 'met' : ($resolutionMissed ? 'missed' : 'na');

            return [
                'ticket' => $t,
                'priority' => $t->priority,
                'client_tier' => $t->client?->tier,
                'policy_name' => $t->slaPolicy?->name,
                'response_hours' => $responseHours,
                'response_target' => $responseTarget,
                'response_status' => $responseStatus,
                'resolution_hours' => $resolutionHours,
                'resolution_target' => $t->slaPolicy?->resolution_time_hours,
                'resolution_status' => $resolutionStatus,
                'response_clock' => $t->slaPolicy?->response_hours_mode ?? 'calendar',
                'resolution_clock' => $t->slaPolicy?->resolution_hours_mode ?? 'calendar',
            ];
        });

        $responseMet = $rows->where('response_status', 'met')->count();
        $responseMissed = $rows->where('response_status', 'missed')->count();
        $resolutionMet = $rows->where('resolution_status', 'met')->count();
        $resolutionMissed = $rows->where('resolution_status', 'missed')->count();
        $total = $closedTickets->count();

        $avgResponseHours = $rows->pluck('response_hours')->filter(fn ($v) => $v !== null)->avg();
        $avgResolutionHours = $rows->pluck('resolution_hours')->filter(fn ($v) => $v !== null)->avg();

        // Breakdown by priority
        $byPriority = $rows->groupBy('priority')->map(function ($group) {
            $respMet = $group->where('response_status', 'met')->count();
            $respMissed = $group->where('response_status', 'missed')->count();
            $resMet = $group->where('resolution_status', 'met')->count();
            $resMissed = $group->where('resolution_status', 'missed')->count();

            return [
                'count' => $group->count(),
                'response_met' => $respMet,
                'response_missed' => $respMissed,
                'response_rate' => ($respMet + $respMissed) > 0 ? round(($respMet / ($respMet + $respMissed)) * 100, 1) : null,
                'resolution_met' => $resMet,
                'resolution_missed' => $resMissed,
                'resolution_rate' => ($resMet + $resMissed) > 0 ? round(($resMet / ($resMet + $resMissed)) * 100, 1) : null,
            ];
        });

        // Breakdown by client tier
        $byTier = $rows->groupBy('client_tier')->map(function ($group) {
            $respMet = $group->where('response_status', 'met')->count();
            $respMissed = $group->where('response_status', 'missed')->count();
            $resMet = $group->where('resolution_status', 'met')->count();
            $resMissed = $group->where('resolution_status', 'missed')->count();

            return [
                'count' => $group->count(),
                'response_met' => $respMet,
                'response_missed' => $respMissed,
                'response_rate' => ($respMet + $respMissed) > 0 ? round(($respMet / ($respMet + $respMissed)) * 100, 1) : null,
                'resolution_met' => $resMet,
                'resolution_missed' => $resMissed,
                'resolution_rate' => ($resMet + $resMissed) > 0 ? round(($resMet / ($resMet + $resMissed)) * 100, 1) : null,
            ];
        });

        return [
            'total_with_sla' => $total,
            'closed_without_sla' => $withoutSla,
            'response_met' => $responseMet,
            'response_missed' => $responseMissed,
            'response_compliance' => ($responseMet + $responseMissed) > 0 ? round(($responseMet / ($responseMet + $responseMissed)) * 100, 1) : 0,
            'resolution_met' => $resolutionMet,
            'resolution_missed' => $resolutionMissed,
            'resolution_compliance' => ($resolutionMet + $resolutionMissed) > 0 ? round(($resolutionMet / ($resolutionMet + $resolutionMissed)) * 100, 1) : 0,
            'avg_response_hours' => $avgResponseHours !== null ? round($avgResponseHours, 1) : null,
            'avg_resolution_hours' => $avgResolutionHours !== null ? round($avgResolutionHours, 1) : null,
            'by_priority' => $byPriority,
            'by_tier' => $byTier,
            'rows' => $rows,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'scope' => $tenantId === null ? 'platform' : 'tenant',
            'tenant_id' => $tenantId,
        ];
    }
}
