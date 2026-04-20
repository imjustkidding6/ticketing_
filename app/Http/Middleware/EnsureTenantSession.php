<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSession
{
    /**
     * Handle an incoming request.
     *
     * Resolves tenant from URL slug prefix (primary) or session (fallback).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isAdmin()) {
            if ($request->route('slug')) {
                $request->route()->forgetParameter('slug');
            }

            return $next($request);
        }

        // Primary: resolve from URL slug prefix
        $slug = $request->route('slug');

        if ($slug) {
            $tenant = Tenant::where('slug', $slug)
                ->where('is_active', true)
                ->whereNull('suspended_at')
                ->first();

            if (! $tenant || ! $user->belongsToTenant($tenant)) {
                return redirect('/')
                    ->with('error', 'You do not have access to this organization.');
            }

            session(['current_tenant_id' => $tenant->id]);
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

            // Remove slug from route parameters so it is not passed to controllers
            $request->route()->forgetParameter('slug');

            return $next($request);
        }

        // Fallback: session-based resolution (for non-slug-prefixed routes)
        $tenantId = session('current_tenant_id');

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);

            if (! $tenant || ! $user->belongsToTenant($tenant) || ! $tenant->is_active || $tenant->isSuspended()) {
                $user->clearCurrentTenant();

                return redirect()->route('tenant.select');
            }

            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

            return $next($request);
        }

        $tenants = $user->tenants()
            ->where('is_active', true)
            ->whereNull('suspended_at')
            ->get();

        if ($tenants->count() === 1) {
            $user->setCurrentTenant($tenants->first());
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenants->first()->id);

            return $next($request);
        }

        if ($tenants->count() > 1) {
            return redirect()->route('tenant.select');
        }

        return redirect()->route('dashboard.no-tenant');
    }
}
