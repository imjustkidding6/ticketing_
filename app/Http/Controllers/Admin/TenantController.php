<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Services\TenantUrlHelper;
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

        $ticketStats = [
            'total' => Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(),
            'open' => Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->open()->count(),
            'closed' => Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->closed()->count(),
            'this_month' => Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
        ];

        $maxTicketsPerMonth = $tenant->license?->plan?->max_tickets_per_month;

        return view('admin.tenants.show', compact('tenant', 'plans', 'ticketStats', 'maxTicketsPerMonth'));
    }

    /**
     * Impersonate a tenant — switch into their context for support.
     */
    public function impersonate(Tenant $tenant): RedirectResponse
    {
        session()->put('admin_impersonating', true);
        session()->put('admin_return_url', route('admin.tenants.show', $tenant));
        session()->put('current_tenant_id', $tenant->id);

        return redirect()->to(
            app(TenantUrlHelper::class)->tenantUrl($tenant, '/dashboard')
        )->with('success', "Now viewing as tenant: {$tenant->name}");
    }

    /**
     * Stop impersonating and return to admin panel.
     */
    public function stopImpersonation(): RedirectResponse
    {
        $returnUrl = session()->pull('admin_return_url', route('admin.dashboard'));
        session()->forget('admin_impersonating');
        session()->forget('current_tenant_id');

        return redirect($returnUrl)
            ->with('success', 'Returned to admin panel.');
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

    /**
     * Update the max users (seats) for a tenant's license.
     */
    public function updateSeats(Request $request, Tenant $tenant): RedirectResponse
    {
        if (! $tenant->license) {
            return redirect()->route('admin.tenants.show', $tenant)
                ->with('error', 'This tenant has no active license.');
        }

        $validated = $request->validate([
            'seats' => ['required', 'integer', 'min:1'],
        ]);

        $tenant->license->update(['seats' => $validated['seats']]);

        return redirect()->route('admin.tenants.show', $tenant)
            ->with('success', "Max users updated to {$validated['seats']}.");
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
