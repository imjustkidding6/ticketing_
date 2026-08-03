<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Client;
use App\Models\Distributor;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Services\PlanService;
use App\Services\TenantUrlHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function index(): View
    {
        $tenants = Tenant::with(['license.plan', 'license.distributor'])
            ->withCount('users')
            ->latest()
            ->paginate(15);

        foreach ($tenants as $tenant) {
            $tenant->tickets_count = Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
            $tenant->clients_count = Client::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
        }

        return view('admin.tenants.index', compact('tenants'));
    }

    public function show(Tenant $tenant): View
    {
        $tenant->load(['license.plan', 'license.distributor', 'users']);
        $plans = Plan::active()->get();

        $ticketStats = [
            'total' => Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_spam', false)->count(),
            'open' => Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_spam', false)->open()->count(),
            'closed' => Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_spam', false)->closed()->count(),
            'this_month' => Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)
                ->where('is_spam', false)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
        ];

        $maxTicketsPerMonth = $tenant->license?->plan?->max_tickets_per_month;

        return view('admin.tenants.show', compact('tenant', 'plans', 'ticketStats', 'maxTicketsPerMonth'));
    }

    public function edit(Tenant $tenant): View
    {
        $tenant->load(['license.plan', 'license.distributor']);
        $plans = Plan::active()->get();
        $distributors = Distributor::where('is_active', true)->get();

        $companyName = AppSetting::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('key', 'company_name')
            ->value('value') ?: $tenant->name;

        $contactEmail = AppSetting::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('key', 'company_email')
            ->value('value') ?: ($tenant->owners()->first()?->email ?? $tenant->users()->first()?->email);

        return view('admin.tenants.edit', compact(
            'tenant',
            'plans',
            'distributors',
            'companyName',
            'contactEmail'
        ));
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'plan_id' => ['nullable', 'integer', 'exists:plans,id'],
            'seats' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', 'string', 'in:active,inactive,suspended'],
            'distributor_id' => ['nullable', 'integer', 'exists:distributors,id'],
            'contact_email' => ['nullable', 'email', 'max:255'],
        ]);

        $tenant->name = $validated['name'];

        if ($validated['status'] === 'suspended') {
            $tenant->is_active = false;
            if (! $tenant->isSuspended()) {
                $tenant->suspended_at = now();
            }
        } elseif ($validated['status'] === 'active') {
            $tenant->is_active = true;
            $tenant->suspended_at = null;
        } else {
            $tenant->is_active = false;
            $tenant->suspended_at = null;
        }

        $tenant->save();

        if (array_key_exists('company_name', $validated)) {
            AppSetting::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'key' => 'company_name'],
                ['value' => $validated['company_name'] ?? '', 'type' => 'string', 'group' => 'general']
            );
        }

        if (array_key_exists('contact_email', $validated)) {
            AppSetting::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'key' => 'company_email'],
                ['value' => $validated['contact_email'] ?? '', 'type' => 'string', 'group' => 'general']
            );
        }

        if ($tenant->license) {
            if (! empty($validated['plan_id']) && $tenant->license->plan_id != $validated['plan_id']) {
                $plan = Plan::find($validated['plan_id']);
                if ($plan) {
                    $tenant->changePlan($plan);
                    app(PlanService::class)->clearCache($tenant);
                }
            }

            $licenseUpdates = [];
            if (! empty($validated['seats'])) {
                $licenseUpdates['seats'] = (int) $validated['seats'];
            }
            if (array_key_exists('distributor_id', $validated)) {
                $licenseUpdates['distributor_id'] = $validated['distributor_id'] ? (int) $validated['distributor_id'] : null;
            }

            if (! empty($licenseUpdates)) {
                $tenant->license->update($licenseUpdates);
            }
        }

        return redirect()->route('admin.tenants.index')
            ->with('success', "Tenant '{$tenant->name}' updated successfully.");
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        if (session('current_tenant_id') === $tenant->id) {
            session()->forget('current_tenant_id');
            session()->forget('admin_impersonating');
        }

        if ($tenant->license) {
            $tenant->license->update(['tenant_id' => null]);
        }

        $tenantName = $tenant->name;
        $tenant->delete();

        return redirect()->route('admin.tenants.index')
            ->with('success', "Tenant '{$tenantName}' deleted successfully.");
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

        app(PlanService::class)->clearCache($tenant);

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
