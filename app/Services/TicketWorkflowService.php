<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class TicketWorkflowService
{
    /** Valid status transitions */
    private const TRANSITIONS = [
        'open' => ['assigned', 'in_progress', 'on_hold', 'cancelled', 'closed'],
        'assigned' => ['in_progress', 'on_hold', 'cancelled', 'closed', 'open'],
        'in_progress' => ['on_hold', 'closed', 'cancelled', 'open'],
        'on_hold' => ['open', 'in_progress', 'closed', 'cancelled'],
        'cancelled' => ['open'],
        'closed' => ['open'],
    ];

    public function __construct(
        private TicketService $ticketService,
    ) {}

    /**
     * Get valid next statuses for a ticket.
     *
     * @return list<string>
     */
    public function getValidTransitions(Ticket $ticket): array
    {
        return self::TRANSITIONS[$ticket->status] ?? [];
    }

    /**
     * Check if a status transition is valid.
     */
    public function isValidTransition(Ticket $ticket, string $newStatus): bool
    {
        return in_array($newStatus, $this->getValidTransitions($ticket));
    }

    /**
     * Update ticket status with validation and side effects.
     */
    public function updateStatus(Ticket $ticket, string $newStatus, ?string $notes = null): bool
    {
        if (! $this->isValidTransition($ticket, $newStatus)) {
            return false;
        }

        // Prevent duplicate status changes within 5 seconds
        $cacheKey = "ticket_status_{$ticket->id}_{$newStatus}";
        if (Cache::has($cacheKey)) {
            return false;
        }
        Cache::put($cacheKey, true, 5);

        // Cannot close without at least one completed/cancelled task (if tasks exist)
        if ($newStatus === 'closed' && $ticket->tasks()->count() > 0) {
            $incompleteTasks = $ticket->tasks()
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count();
            if ($incompleteTasks > 0) {
                return false;
            }
        }

        $this->ticketService->changeStatus($ticket, $newStatus);

        // Set first_response_at on first move to in_progress
        if ($newStatus === 'in_progress' && ! $ticket->first_response_at) {
            $ticket->update(['first_response_at' => now()]);
        }

        return true;
    }

    /**
     * Advance ticket to the next logical status.
     */
    public function advanceToNextStatus(Ticket $ticket, ?string $notes = null): ?string
    {
        $nextMap = [
            'open' => $ticket->assigned_to ? 'assigned' : null,
            'assigned' => 'in_progress',
            'in_progress' => 'closed',
        ];

        $next = $nextMap[$ticket->status] ?? null;
        if ($next && $this->updateStatus($ticket, $next, $notes)) {
            return $next;
        }

        return null;
    }

    /**
     * Self-assign a ticket to the current user.
     */
    public function selfAssignTicket(Ticket $ticket, User $user): bool
    {
        if (! in_array($ticket->status, ['open'])) {
            return false;
        }

        $this->ticketService->assignTicket($ticket, $user);

        return true;
    }

    /**
     * Mark a ticket as false alarm and close it.
     */
    public function markFalseAlarm(Ticket $ticket, ?string $reason = null): void
    {
        $ticket->update(['is_false_alarm' => true]);

        $this->ticketService->addHistory(
            $ticket,
            'false_alarm',
            description: 'Marked as false alarm.'.($reason ? " Reason: {$reason}" : '')
        );

        $this->ticketService->changeStatus($ticket, 'closed');
    }

    /**
     * Reopen a closed/cancelled ticket.
     */
    public function reopenTicket(Ticket $ticket): bool
    {
        if (! in_array($ticket->status, ['closed', 'cancelled'])) {
            return false;
        }

        $ticket->update([
            'closed_at' => null,
            'reopened_count' => $ticket->reopened_count + 1,
        ]);

        $this->ticketService->changeStatus($ticket->fresh(), 'open');

        return true;
    }

    /**
     * Get workflow metrics for the dashboard.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        $base = fn () => Ticket::query()->notMerged()->notSpam();

        return [
            'open' => $base()->where('status', 'open')->count(),
            'assigned' => $base()->where('status', 'assigned')->count(),
            'in_progress' => $base()->where('status', 'in_progress')->count(),
            'on_hold' => $base()->where('status', 'on_hold')->count(),
            'closed_today' => $base()->where('status', 'closed')->whereDate('closed_at', today())->count(),
            'closed_this_week' => $base()->where('status', 'closed')->where('closed_at', '>=', now()->startOfWeek())->count(),
            'overdue' => $base()->whereNotNull('resolution_due_at')->where('resolution_due_at', '<', now())->whereNotIn('status', ['closed', 'cancelled'])->count(),
            'unassigned' => $base()->whereNull('assigned_to')->whereNotIn('status', ['closed', 'cancelled'])->count(),
        ];
    }

    /**
     * Get workflow analytics for a date range.
     *
     * @return array<string, mixed>
     */
    public function getAnalytics(string $from, string $to): array
    {
        $tickets = Ticket::query()
            ->notMerged()
            ->notSpam()
            ->whereBetween('created_at', [$from, $to])
            ->get();

        $avgResolution = $tickets->filter(fn ($t) => $t->closed_at)
            ->avg(fn ($t) => $t->created_at->diffInHours($t->closed_at));

        $avgResponse = $tickets->filter(fn ($t) => $t->first_response_at)
            ->avg(fn ($t) => $t->created_at->diffInMinutes($t->first_response_at));

        return [
            'total_created' => $tickets->count(),
            'total_closed' => $tickets->where('status', 'closed')->count(),
            'total_cancelled' => $tickets->where('status', 'cancelled')->count(),
            'avg_resolution_hours' => round($avgResolution ?? 0, 1),
            'avg_response_minutes' => round($avgResponse ?? 0, 0),
            'by_priority' => $tickets->groupBy('priority')->map->count(),
            'by_status' => $tickets->groupBy('status')->map->count(),
            'false_alarms' => $tickets->where('is_false_alarm', true)->count(),
        ];
    }
}
