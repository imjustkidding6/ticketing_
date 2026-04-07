<?php

namespace App\Http\Middleware;

use App\Models\Client;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientPortalAccess
{
    /**
     * Handle an incoming request.
     *
     * Resolves the tenant from the route slug and ensures the authenticated
     * user is a client belonging to that tenant.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->route('tenant');

        if (! $tenant instanceof Tenant) {
            $tenant = Tenant::where('slug', $tenant)->where('is_active', true)->first();
        }

        if (! $tenant || $tenant->isSuspended()) {
            abort(404);
        }

        $request->merge(['portal_tenant' => $tenant]);

        $user = $request->user();

        if (! $user) {
            return redirect()->route('portal.login', ['tenant' => $tenant->slug]);
        }

        $client = Client::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('status', Client::STATUS_ACTIVE)
            ->first();

        if (! $client) {
            abort(403, 'You do not have access to this portal.');
        }

        $request->merge(['portal_client' => $client]);

        return $next($request);
    }
}
