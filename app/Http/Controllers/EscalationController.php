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
        $validated = $request->validate([
            'to_tier' => ['required', 'in:tier_1,tier_2,tier_3'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ]);

        $currentTier = $ticket->current_tier ?? 'tier_1';
        $toTier = $validated['to_tier'];

        if ($this->tierRank($toTier) <= $this->tierRank($currentTier)) {
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
     * Resolve the numeric rank for a tier string.
     */
    private function tierRank(?string $tier): int
    {
        if ($tier === null) {
            return 0;
        }

        return (int) filter_var($tier, FILTER_SANITIZE_NUMBER_INT);
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
     */
    private function agentCanHandleTier(User $agent, string $toTier): bool
    {
        $isOwner = Tenant::find(session('current_tenant_id'))
            ?->isOwner($agent) ?? false;

        return $isOwner || $this->tierRank($agent->support_tier) >= $this->tierRank($toTier);
    }
}
