<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketEscalation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EscalationService
{
    public function __construct(
        private TicketService $ticketService,
    ) {}

    /**
     * Escalate a ticket to a higher tier.
     */
    public function escalate(
        Ticket $ticket,
        string $toTier,
        ?string $reason = null,
        string $triggerType = 'manual',
        ?User $toAgent = null,
    ): TicketEscalation {
        $fromTier = $ticket->current_tier;

        return DB::transaction(function () use ($ticket, $toTier, $reason, $triggerType, $toAgent, $fromTier) {
            return $this->performEscalation($ticket, $toTier, $reason, $triggerType, $toAgent, $fromTier);
        });
    }

    /**
     * The escalation writes themselves: escalation record, ticket, history.
     */
    private function performEscalation(
        Ticket $ticket,
        string $toTier,
        ?string $reason,
        string $triggerType,
        ?User $toAgent,
        string $fromTier,
    ): TicketEscalation {
        $escalation = TicketEscalation::create([
            'ticket_id' => $ticket->id,
            'escalated_by' => Auth::id(),
            'escalated_from_user_id' => $ticket->assigned_to,
            'escalated_to_user_id' => $toAgent?->id,
            'from_tier' => $fromTier,
            'to_tier' => $toTier,
            'trigger_type' => $triggerType,
            'reason' => $reason,
        ]);

        $ticket->update([
            'current_tier' => $toTier,
            'escalation_count' => $ticket->escalation_count + 1,
            'last_escalated_at' => now(),
        ]);

        // Hand the ticket over through the assignment service rather than writing
        // assigned_to directly, so an escalated ticket gets the same assignment
        // record, history entry and notification as any other reassignment.
        // The controller has already checked the target agent's tier.
        if ($toAgent && $toAgent->id !== $ticket->assigned_to) {
            $this->ticketService->assignTicket($ticket->fresh(), $toAgent);
            $ticket->refresh();
        }

        $this->ticketService->addHistory(
            $ticket,
            'escalated',
            'current_tier',
            $fromTier,
            $toTier,
            "Escalated from {$fromTier} to {$toTier}.".($reason ? " Reason: {$reason}" : '')
        );

        return $escalation;
    }

    /**
     * De-escalate a ticket to a lower tier.
     */
    public function deescalate(Ticket $ticket, string $toTier, ?string $reason = null): TicketEscalation
    {
        return $this->escalate($ticket, $toTier, $reason, 'manual');
    }
}
