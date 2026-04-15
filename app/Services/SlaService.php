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

    
    public function markBreachNotified(Ticket $ticket): bool
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($ticket) {
            /** @var Ticket|null $fresh */
            $fresh = Ticket::withoutGlobalScopes()
                ->whereKey($ticket->id)
                ->whereNull('sla_breach_notified_at')
                ->lockForUpdate()
                ->first();

            if (! $fresh) {
                // Another process already marked this ticket — skip.
                return false;
            }

            $fresh->update(['sla_breach_notified_at' => now()]);

            return true;
        });
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
            ->where('status', 'closed')
            ->whereNotNull('sla_policy_id')
            ->whereBetween('closed_at', [$from, $to])
            ->get();

        $responseMet = $closedTickets->filter(fn ($t) => $t->first_response_at && $t->response_due_at && $t->first_response_at->lte($t->response_due_at))->count();
        $responseMissed = $closedTickets->filter(fn ($t) => $t->first_response_at && $t->response_due_at && $t->first_response_at->gt($t->response_due_at))->count();
        $resolutionMet = $closedTickets->filter(fn ($t) => $t->closed_at && $t->resolution_due_at && $t->closed_at->lte($t->resolution_due_at))->count();
        $resolutionMissed = $closedTickets->filter(fn ($t) => $t->closed_at && $t->resolution_due_at && $t->closed_at->gt($t->resolution_due_at))->count();

        $total = $closedTickets->count();

        return [
            'total_with_sla' => $total,
            'response_met' => $responseMet,
            'response_missed' => $responseMissed,
            'response_compliance' => $total > 0 ? round(($responseMet / max(1, $responseMet + $responseMissed)) * 100, 1) : 0,
            'resolution_met' => $resolutionMet,
            'resolution_missed' => $resolutionMissed,
            'resolution_compliance' => $total > 0 ? round(($resolutionMet / max(1, $resolutionMet + $resolutionMissed)) * 100, 1) : 0,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];
    }
}