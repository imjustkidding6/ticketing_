<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketEscalation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EscalationService
{
    /**
     * The tier ladder, lowest first. varchar/enum in the database, so this constant is
     * the only enumeration in code — the `Ticket::STATUSES` rule.
     *
     * @var list<string>
     */
    public const TIERS = ['tier_1', 'tier_2', 'tier_3'];

    public function __construct(
        private TicketService $ticketService,
    ) {}

    /**
     * The numeric rank of a tier string; 0 for an untiered member.
     *
     * This lived as a private helper on EscalationController. It moved here because a
     * second consumer arrived (AnalyzeTicketService, filtering engineer candidates for
     * suggestion 7) and two copies of a ladder is exactly the drift CLAUDE.md warns
     * about for Ticket::STATUSES: a tier_4 added to one copy rolls out half-applied.
     */
    public function tierRank(?string $tier): int
    {
        if ($tier === null) {
            return 0;
        }

        return (int) filter_var($tier, FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Whether an agent may handle tickets at `$toTier`.
     *
     * The tenant is a parameter rather than being read from the session: the escalation
     * controller is answering for the workspace in the URL, while AnalyzeTicketService
     * is answering for the workspace the *ticket* belongs to, and inferring it would be
     * the `TenantTime::timezone()` mistake again. Pass null to skip the owner bypass.
     */
    public function canHandleTier(?User $agent, string $toTier, ?Tenant $tenant): bool
    {
        if ($agent === null) {
            return false;
        }

        if ($tenant !== null && $tenant->isOwner($agent)) {
            return true;
        }

        return $this->tierRank($agent->support_tier) >= $this->tierRank($toTier);
    }

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
