<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AgentPerformanceService
{
    /**
     * Get performance report for a single agent.
     *
     * @return array<string, mixed>
     */
    public function getAgentPerformanceReport(User $agent, string $from, string $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        $assignedTickets = Ticket::where('assigned_to', $agent->id)
            ->notMerged()
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $closedTickets = $assignedTickets->where('status', 'closed');

        $avgResolutionHours = $closedTickets->avg(function ($ticket) {
            return $ticket->created_at->diffInHours($ticket->closed_at);
        });

        $avgResponseMinutes = $assignedTickets->filter(fn ($t) => $t->first_response_at)
            ->avg(function ($ticket) {
                return $ticket->created_at->diffInMinutes($ticket->first_response_at);
            });

        // Reopen analysis for this agent (lifetime, not range — reopen could happen months after close)
        $everClosedByAgent = Ticket::where('assigned_to', $agent->id)
            ->notMerged()
            ->whereNotNull('first_closed_at')
            ->count();
        $reopenedAfterAgentClosure = Ticket::where('assigned_to', $agent->id)
            ->notMerged()
            ->where('reopened_count', '>', 0)
            ->count();
        $totalReopens = (int) (Ticket::where('assigned_to', $agent->id)
            ->notMerged()
            ->sum('reopened_count') ?? 0);
        $avgReopensPerReopenedTicket = $reopenedAfterAgentClosure > 0
            ? round($totalReopens / $reopenedAfterAgentClosure, 2)
            : 0;

        return [
            'agent' => $agent,
            'total_assigned' => $assignedTickets->count(),
            'open' => $assignedTickets->whereIn('status', ['open', 'assigned', 'in_progress', 'on_hold'])->count(),
            'closed' => $closedTickets->count(),
            'cancelled' => $assignedTickets->where('status', 'cancelled')->count(),
            'avg_resolution_hours' => round($avgResolutionHours ?? 0, 1),
            'avg_response_minutes' => round($avgResponseMinutes ?? 0, 0),
            'by_priority' => $assignedTickets->groupBy('priority')->map->count()->toArray(),
            'by_status' => $assignedTickets->groupBy('status')->map->count()->toArray(),
            'overdue' => $assignedTickets->filter(fn ($t) => $t->isOverdue())->count(),
            'reopen' => [
                'ever_closed' => $everClosedByAgent,
                'reopened_after_closure' => $reopenedAfterAgentClosure,
                'total_reopens' => $totalReopens,
                'avg_reopens_per_ticket' => $avgReopensPerReopenedTicket,
                'reopen_rate' => $everClosedByAgent > 0
                    ? round(($reopenedAfterAgentClosure / $everClosedByAgent) * 100, 1)
                    : 0,
            ],
        ];
    }

    /**
     * Get performance reports for all agents.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getAllAgentsPerformance(string $from, string $to): Collection
    {
        $agents = User::whereHas('tenants', fn ($q) => $q->where('tenant_id', session('current_tenant_id')))
            ->orderBy('name')
            ->get();

        return $agents->map(fn ($agent) => $this->getAgentPerformanceReport($agent, $from, $to));
    }

    /**
     * Get team-wide performance metrics.
     *
     * @return array<string, mixed>
     */
    public function getTeamPerformanceMetrics(string $from, string $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        $tickets = Ticket::notMerged()->whereBetween('created_at', [$start, $end])->get();
        $closedTickets = $tickets->where('status', 'closed');

        return [
            'total_tickets' => $tickets->count(),
            'total_closed' => $closedTickets->count(),
            'closure_rate' => $tickets->count() > 0
                ? round(($closedTickets->count() / $tickets->count()) * 100, 1)
                : 0,
            'avg_resolution_hours' => round($closedTickets->avg(fn ($t) => $t->created_at->diffInHours($t->closed_at)) ?? 0, 1),
            'total_overdue' => $tickets->filter(fn ($t) => $t->isOverdue())->count(),
            'total_unassigned' => $tickets->whereNull('assigned_to')->count(),
        ];
    }
}
