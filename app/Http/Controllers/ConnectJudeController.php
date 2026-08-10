<?php

namespace App\Http\Controllers;

use Cliqueha\AssistantConnector\Models\DesktopToken;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

/**
 * Self-serve page where a signed-in user generates an API token to connect this
 * workspace to the Jude assistant Hub. The token is stamped with the user's
 * current tenant, so the assistant acts in the workspace it was created from.
 */
class ConnectJudeController extends Controller
{
    /**
     * These routes are not behind EnsureTenantSession, so the Spatie team id
     * isn't set for us — sync it from the session before checking permissions.
     */
    private function authorizeJude(): void
    {
        if ($tenantId = session('current_tenant_id')) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        }

        $this->checkPermission('manage api tokens');
    }

    public function index()
    {
        $this->authorizeJude();

        return view('connect-jude', [
            'tokens' => DesktopToken::where('user_id', auth()->id())->latest()->get(),
            'fresh' => session('fresh_jude_token'),
        ]);
    }

    public function generate(Request $request)
    {
        $this->authorizeJude();

        $name = trim($request->input('name')) ?: 'Jude';

        $issued = DesktopToken::issue(auth()->user(), $name);
        // Bind the token to the workspace the user is currently in.
        $issued['token']->update(['tenant_id' => session('current_tenant_id')]);

        return back()->with('fresh_jude_token', $issued['plain']);
    }

    public function revoke(DesktopToken $token)
    {
        $this->authorizeJude();

        abort_unless($token->user_id === auth()->id(), 403);
        $token->delete();

        return back()->with('status', 'Token revoked.');
    }
}
