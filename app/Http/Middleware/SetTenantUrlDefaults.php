<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetTenantUrlDefaults
{
    /**
     * Set URL::defaults for the tenant slug so route() can generate
     * tenant-prefixed URLs without explicitly passing the slug parameter.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('slug');

        if (! $slug && ($tenantId = session('current_tenant_id'))) {
            $slug = Tenant::where('id', $tenantId)->value('slug');
        }

        if ($slug) {
            URL::defaults(['slug' => $slug]);
        }

        return $next($request);
    }
}
