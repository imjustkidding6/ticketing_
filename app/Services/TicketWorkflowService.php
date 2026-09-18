<?php

namespace App\Services;

use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\TicketAlreadyAssignedException;
use App\Models\Ticket;
use App\Models\User;
use App\Support\TenantTime;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TicketWorkflowService
{
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
        return $ticket->allowedTransitions();
    }

    /**
     * Check if a status transition is valid.
     */
    public function isValidTransition(Ticket $ticket, string $newStatus): bool
    {
        return $ticket->canTransitionTo($newStatus);
    }

    /**
     * Update ticket status, enforcing the transition map.
     *
     * @throws InvalidStatusTransitionException when the move isn't allowed
     */
    public function updateStatus(Ticket $ticket, string $newStatus, ?string $notes = null): bool
    {
        if ($ticket->status === $newStatus) {
            return false;
        }

        if (! $this->isValidTransition($ticket, $newStatus)) {
            throw new InvalidStatusTransitionException($ticket->status, $newStatus);
        }

        // Collapse a double-submit of the same transition. Distinct from a rule
        // violation: the caller asked for something legal that has just happened,
        // so report "nothing to do" rather than raising.
        $cacheKey = "ticket_status_{$ticket->id}_{$newStatus}";
        if (Cache::has($cacheKey)) {
            return false;
        }
        Cache::put($cacheKey, true, 5);

        // changeStatus() enforces the task rule and stamps first_response_at
        // and in_progress_at; don't duplicate either here.
        $this->ticketService->changeStatus($ticket, $newStatus);

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

        try {
            $this->ticketService->assignTicket($ticket, $user, onlyIfUnassigned: true);
        } catch (TicketAlreadyAssignedException) {
            return false;
        }

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
    public function reopenTicket(Ticket $ticket, ?string $reason = null): bool
    {
        if (! in_array($ticket->status, ['closed', 'cancelled'])) {
            return false;
        }

        $nextCount = $ticket->reopened_count + 1;

        DB::transaction(function () use ($ticket, $reason, $nextCount) {
            $description = 'Ticket reopened (cycle '.($nextCount + 1).')';
            if ($reason) {
                $description .= ' — reason: '.$reason;
            }

            $this->ticketService->addHistory(
                $ticket, 'reopened', 'status', $ticket->status, 'open', $description
            );

            $ticket->update([
                'reopened_count' => $nextCount,
                'last_reopened_at' => now(),
                'last_reopen_reason' => $reason,
            ]);

            // changeStatus() clears closed_at on the way out of closed.
            $this->ticketService->changeStatus($ticket->fresh(), 'open');
        });

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
            'pending' => $base()->where('status', 'pending')->count(),
            'closed_today' => $base()->where('status', 'closed')->whereBetween('closed_at', TenantTime::dayRange())->count(),
            'closed_this_week' => $base()->where('status', 'closed')->where('closed_at', '>=', TenantTime::startOfWeek())->count(),
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
            ->avg(fn ($t) => $t->getEffectiveResolutionTimeHours());

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
