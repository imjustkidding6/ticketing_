<?php

namespace App\Services;

use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Support\TenantTime;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

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

        $ticket->update([
            'sla_policy_id' => $policy->id,
            'response_due_at' => $ticket->created_at->copy()
                ->addHours($policy->response_time_hours)
                ->addMinutes($holdMinutes),
            'resolution_due_at' => $ticket->created_at->copy()
                ->addHours($policy->resolution_time_hours)
                ->addMinutes($holdMinutes),
        ]);
    }

    /**
     * Push the deadlines out by a period the ticket spent on hold.
     */
    public function extendForHold(Ticket $ticket, int $holdMinutes): void
    {
        if ($holdMinutes <= 0) {
            return;
        }

        $updates = [];

        if ($ticket->response_due_at && ! $ticket->first_response_at) {
            $updates['response_due_at'] = $ticket->response_due_at->copy()->addMinutes($holdMinutes);
        }

        if ($ticket->resolution_due_at) {
            $updates['resolution_due_at'] = $ticket->resolution_due_at->copy()->addMinutes($holdMinutes);
        }

        if ($updates) {
            $ticket->update($updates);
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
            ->with(['client', 'assignee'])
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
    public function getComplianceReport(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? TenantTime::startOfDaysAgo(30);
        $to = $to ?? TenantTime::now();

        $closedTickets = Ticket::query()
            ->notMerged()
            ->notSpam()
            ->with(['client', 'department', 'assignee', 'slaPolicy'])
            ->where('status', 'closed')
            ->whereNotNull('sla_policy_id')
            ->whereBetween('closed_at', [$from, $to])
            ->get();

        // Closed tickets with no policy stamped on them are not part of the
        // percentage — no target was ever promised for them. Counting them would be
        // inventing a commitment; hiding them makes compliance read better than the
        // service actually was, which is the complaint this report attracted. So the
        // denominator is unchanged and the exclusion is reported alongside it.
        $withoutSla = Ticket::query()
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
            $responseHours = $t->first_response_at
                ? round($t->created_at->diffInHours($t->first_response_at, false), 2)
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
        ];
    }
}
