<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        $plans = Plan::withCount('licenses')->get();

        return view('admin.plans.index', compact('plans'));
    }

    public function create(): View
    {
        return view('admin.plans.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:plans'],
            'description' => ['nullable', 'string', 'max:1000'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_tickets_per_month' => ['nullable', 'integer', 'min:1'],
        ]);

        Plan::create($validated);

        return redirect()->route('admin.plans.index')
            ->with('success', 'Plan created successfully.');
    }

    public function edit(Plan $plan): View
    {
        return view('admin.plans.edit', compact('plan'));
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:plans,slug,'.$plan->id],
            'description' => ['nullable', 'string', 'max:1000'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_tickets_per_month' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $plan->update($validated);

        return redirect()->route('admin.plans.index')
            ->with('success', 'Plan updated successfully.');
    }
}
