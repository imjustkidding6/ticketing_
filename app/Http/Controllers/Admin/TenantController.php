<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function index(): View
    {
        $tenants = Tenant::with(['license.plan'])
            ->withCount('users')
            ->latest()
            ->paginate(15);

        return view('admin.tenants.index', compact('tenants'));
    }

    public function show(Tenant $tenant): View
    {
        $tenant->load(['license.plan', 'license.distributor', 'users']);
        $plans = Plan::active()->get();

        return view('admin.tenants.show', compact('tenant', 'plans'));
    }

    /**
     * Change the subscription plan for a tenant.
     */
    public function changePlan(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
        ]);

        if (! $tenant->license) {
            return redirect()->route('admin.tenants.show', $tenant)
                ->with('error', 'This tenant has no active license.');
        }

        $plan = Plan::findOrFail($validated['plan_id']);
        $tenant->changePlan($plan);

        return redirect()->route('admin.tenants.show', $tenant)
            ->with('success', "Subscription changed to {$plan->name} plan.");
    }

    public function suspend(Tenant $tenant): RedirectResponse
    {
        $tenant->suspend();

        return redirect()->route('admin.tenants.index')
            ->with('success', 'Tenant suspended successfully.');
    }

    public function unsuspend(Tenant $tenant): RedirectResponse
    {
        $tenant->unsuspend();

        return redirect()->route('admin.tenants.index')
            ->with('success', 'Tenant unsuspended successfully.');
    }
}
