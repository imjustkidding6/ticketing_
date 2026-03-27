<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Services\EscalationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EscalationController extends Controller
{
    public function __construct(
        private EscalationService $escalationService,
    ) {}

    /**
     * Escalate a ticket.
     */
    public function escalate(Request $request, Ticket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'to_tier' => ['required', 'in:tier_1,tier_2,tier_3'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'assigned_to' => ['nullable', \Illuminate\Validation\Rule::exists('users', 'id')->whereNull('deleted_at')],
        ]);

        $toAgent = ! empty($validated['assigned_to'])
            ? User::query()
                ->whereHas('tenants', fn ($q) => $q->where('tenant_id', session('current_tenant_id')))
                ->find($validated['assigned_to'])
            : null;

        $this->escalationService->escalate(
            $ticket,
            $validated['to_tier'],
            $validated['reason'] ?? null,
            'manual',
            $toAgent,
        );

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Ticket escalated to '.str_replace('_', ' ', $validated['to_tier']).'.');
    }
}
