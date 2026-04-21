<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\SlaPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SlaPolicyController extends Controller
{
    /**
     * Display a listing of SLA policies.
     */
    public function index(): View
    {
        $policies = SlaPolicy::query()->latest()->paginate(20);

        return view('sla.index', compact('policies'));
    }

    /**
     * Show the form for creating a new SLA policy.
     */
    public function create(): View
    {
        $priorities = ['critical', 'high', 'medium', 'low'];
        $tiers = Client::tiers();

        return view('sla.create', compact('priorities', 'tiers'));
    }

    /**
     * Store newly created SLA policies (batch by priority level).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'client_tier' => ['nullable', 'in:basic,premium,enterprise'],
            'is_active' => ['nullable', 'boolean'],
            'priorities' => ['required', 'array'],
            'priorities.*.enabled' => ['required'],
            'priorities.*.response_time_hours' => ['required_if:priorities.*.enabled,1', 'nullable', 'integer', 'min:1'],
            'priorities.*.resolution_time_hours' => ['required_if:priorities.*.enabled,1', 'nullable', 'integer', 'min:1'],
        ]);

        $validPriorities = ['low', 'medium', 'high', 'critical'];
        $isActive = $request->boolean('is_active', true);
        $clientTier = $validated['client_tier'] ?? null;
        $created = 0;

        foreach ($validated['priorities'] as $priority => $data) {
            if (! in_array($priority, $validPriorities, true) || ! ($data['enabled'] ?? false)) {
                continue;
            }

            SlaPolicy::updateOrCreate(
                [
                    'tenant_id' => session('current_tenant_id'),
                    'client_tier' => $clientTier,
                    'priority' => $priority,
                ],
                [
                    'name' => $validated['name'].' - '.ucfirst($priority),
                    'description' => $validated['description'] ?? null,
                    'response_time_hours' => $data['response_time_hours'],
                    'resolution_time_hours' => $data['resolution_time_hours'],
                    'is_active' => $isActive,
                ]
            );
            $created++;
        }

        if ($created === 0) {
            return back()->withInput()->withErrors(['priorities' => __('At least one priority level must be enabled.')]);
        }

        return redirect()->route('sla.index')
            ->with('success', __(':count SLA policies created.', ['count' => $created]));
    }

    /**
     * Show the form for editing an SLA policy.
     */
    public function edit(SlaPolicy $sla): View
    {
        return view('sla.edit', ['policy' => $sla]);
    }

    /**
     * Update the specified SLA policy.
     */
    public function update(Request $request, SlaPolicy $sla): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'client_tier' => ['nullable', 'in:basic,premium,enterprise'],
            'priority' => ['nullable', 'in:low,medium,high,critical'],
            'response_time_hours' => ['required', 'integer', 'min:1'],
            'resolution_time_hours' => ['required', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $sla->update($validated);

        return redirect()->route('sla.index')
            ->with('success', 'SLA policy updated.');
    }

    /**
     * Remove the specified SLA policy.
     */
    public function destroy(SlaPolicy $sla): RedirectResponse
    {
        $sla->delete();

        return redirect()->route('sla.index')
            ->with('success', 'SLA policy deleted.');
    }
}
