<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\TenantUrlHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantController extends Controller
{
    /**
     * Show the tenant selection page.
     */
    public function select(Request $request): View
    {
        $tenants = $request->user()->tenants()
            ->with('license.plan')
            ->where('is_active', true)
            ->whereNull('suspended_at')
            ->get();

        return view('tenant.select', compact('tenants'));
    }

    /**
     * Switch to the selected tenant.
     */
    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
        ]);

        $tenant = Tenant::findOrFail($validated['tenant_id']);

        if (! $request->user()->belongsToTenant($tenant)) {
            abort(403, 'You do not belong to this organization.');
        }

        if (! $tenant->is_active || $tenant->isSuspended()) {
            return back()->withErrors(['tenant_id' => 'This organization is not currently active.']);
        }

        $request->user()->setCurrentTenant($tenant);

        return redirect()->to(
            app(TenantUrlHelper::class)->tenantUrl($tenant, '/dashboard')
        );
    }
}
