<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketEscalation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

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

        $updates = [
            'current_tier' => $toTier,
            'escalation_count' => $ticket->escalation_count + 1,
            'last_escalated_at' => now(),
        ];

        if ($toAgent) {
            $updates['assigned_to'] = $toAgent->id;
        }

        $ticket->update($updates);

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
