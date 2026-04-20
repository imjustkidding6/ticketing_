<?php

namespace App\Http\Controllers;

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
        return view('sla.create');
    }

    /**
     * Store a newly created SLA policy.
     */
    public function store(Request $request): RedirectResponse
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

        SlaPolicy::create($validated);

        return redirect()->route('sla.index')
            ->with('success', 'SLA policy created.');
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
