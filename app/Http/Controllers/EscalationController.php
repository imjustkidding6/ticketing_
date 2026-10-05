<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\EscalationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EscalationController extends Controller
{
    public function __construct(
        private EscalationService $escalationService,
    ) {}

    /**
     * Escalate a ticket to a higher tier.
     */
    public function escalate(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->checkPermission('escalate tickets');

        $validated = $request->validate([
            'to_tier' => ['required', Rule::in(EscalationService::TIERS)],
            'reason' => ['nullable', 'string', 'max:1000'],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ]);

        $currentTier = $ticket->current_tier ?? 'tier_1';
        $toTier = $validated['to_tier'];

        if ($this->escalationService->tierRank($toTier) <= $this->escalationService->tierRank($currentTier)) {
            return redirect()->route('tickets.show', $ticket)
                ->with('error', 'Cannot escalate to the same or lower tier.');
        }

        $toAgent = $this->resolveAgent($validated);

        if ($toAgent && ! $this->agentCanHandleTier($toAgent, $toTier)) {
            return redirect()->route('tickets.show', $ticket)
                ->with('error', "Agent {$toAgent->name} is {$toAgent->support_tier} and cannot handle {$toTier} tickets.");
        }

        $this->escalationService->escalate(
            $ticket,
            $toTier,
            $validated['reason'] ?? null,
            'manual',
            $toAgent,
        );

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Ticket escalated to '.str_replace('_', ' ', $toTier).'.');
    }

    /**
     * Resolve the target agent from validated input.
     */
    private function resolveAgent(array $validated): ?User
    {
        if (empty($validated['assigned_to'])) {
            return null;
        }

        return User::query()
            ->whereHas('tenants', fn ($q) => $q->where('tenant_id', session('current_tenant_id')))
            ->find($validated['assigned_to']);
    }

    /**
     * Check whether an agent's tier (or owner status) allows handling the target tier.
     *
     * The ladder itself lives in EscalationService, which AnalyzeTicketService also uses
     * to filter engineer candidates. Only the "which workspace" answer is the
     * controller's: here it is the one in the URL.
     */
    private function agentCanHandleTier(User $agent, string $toTier): bool
    {
        return $this->escalationService->canHandleTier(
            $agent,
            $toTier,
            Tenant::find(session('current_tenant_id')),
        );
    }
}
