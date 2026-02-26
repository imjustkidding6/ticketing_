<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSession
{
    /**
     * Handle an incoming request.
     *
     * Ensures the authenticated user has a valid tenant set in session.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isAdmin()) {
            return $next($request);
        }

        $tenantId = session('current_tenant_id');

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);

            if (! $tenant || ! $user->belongsToTenant($tenant) || ! $tenant->is_active || $tenant->isSuspended()) {
                $user->clearCurrentTenant();

                return redirect()->route('tenant.select');
            }

            return $next($request);
        }

        $tenants = $user->tenants()
            ->where('is_active', true)
            ->whereNull('suspended_at')
            ->get();

        if ($tenants->count() === 1) {
            $user->setCurrentTenant($tenants->first());

            return $next($request);
        }

        if ($tenants->count() > 1) {
            return redirect()->route('tenant.select');
        }

        return redirect()->route('dashboard.no-tenant');
    }
}
