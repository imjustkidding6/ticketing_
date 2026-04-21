<?php

namespace App\Services;

use App\Models\SlaPolicy;
use App\Models\Ticket;

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

        $ticket->update([
            'sla_policy_id' => $policy->id,
            'response_due_at' => $ticket->created_at->addHours($policy->response_time_hours),
            'resolution_due_at' => $ticket->created_at->addHours($policy->resolution_time_hours),
        ]);
    }

    /**
     * Get overdue tickets.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Ticket>
     */
    public function getOverdueTickets(): \Illuminate\Database\Eloquent\Collection
    {
        return Ticket::withoutGlobalScopes()
            ->open()
            ->notMerged()
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
     * @return \Illuminate\Database\Eloquent\Collection<int, Ticket>
     */
    public function getTicketsNeedingBreachWarning(): \Illuminate\Database\Eloquent\Collection
    {
        return Ticket::withoutGlobalScopes()
            ->open()
            ->notMerged()
            ->whereNull('sla_breach_notified_at')
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
     * Get SLA compliance report.
     *
     * @return array<string, mixed>
     */
    public function getComplianceReport(?\Carbon\Carbon $from = null, ?\Carbon\Carbon $to = null): array
    {
        $from = $from ?? now()->subDays(30);
        $to = $to ?? now();

        $closedTickets = Ticket::query()
            ->notMerged()
            ->notSpam()
            ->with(['client', 'department', 'assignee', 'slaPolicy'])
            ->where('status', 'closed')
            ->whereNotNull('sla_policy_id')
            ->whereBetween('closed_at', [$from, $to])
            ->get();

        $rows = $closedTickets->map(function (Ticket $t) {
            $responseMet = $t->first_response_at && $t->response_due_at && $t->first_response_at->lte($t->response_due_at);
            $responseMissed = $t->first_response_at && $t->response_due_at && $t->first_response_at->gt($t->response_due_at);
            $resolutionMet = $t->closed_at && $t->resolution_due_at && $t->closed_at->lte($t->resolution_due_at);
            $resolutionMissed = $t->closed_at && $t->resolution_due_at && $t->closed_at->gt($t->resolution_due_at);

            $responseHours = $t->first_response_at
                ? round($t->created_at->diffInHours($t->first_response_at, false), 2)
                : null;
            $resolutionHours = $t->closed_at
                ? round($t->created_at->diffInHours($t->closed_at, false), 2)
                : null;

            $responseStatus = $responseMet ? 'met' : ($responseMissed ? 'missed' : 'na');
            $resolutionStatus = $resolutionMet ? 'met' : ($resolutionMissed ? 'missed' : 'na');

            return [
                'ticket' => $t,
                'priority' => $t->priority,
                'client_tier' => $t->client?->tier,
                'policy_name' => $t->slaPolicy?->name,
                'response_hours' => $responseHours,
                'response_target' => $t->slaPolicy?->response_time_hours,
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
