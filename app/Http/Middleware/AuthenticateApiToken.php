<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (! $bearer) {
            return response()->json(['message' => 'Missing API token.'], 401);
        }

        $token = ApiToken::findByPlainToken($bearer);

        if (! $token) {
            return response()->json(['message' => 'Invalid API token.'], 401);
        }

        if ($token->isExpired()) {
            return response()->json(['message' => 'API token has expired.'], 401);
        }

        $tenant = $token->tenant;
        if (! $tenant || ! $tenant->is_active) {
            return response()->json(['message' => 'Tenant is not active.'], 403);
        }

        session(['current_tenant_id' => $tenant->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $request->attributes->set('api_token', $token);
        $request->attributes->set('api_tenant', $tenant);

        $token->touchLastUsed();

        return $next($request);
    }
}
