<?php

namespace App\Http\Controllers;

use Cliqueha\AssistantConnector\Models\DesktopToken;
use Illuminate\Http\Request;

/**
 * Self-serve page where a signed-in user generates an API token to connect this
 * workspace to the Jude assistant Hub. The token is stamped with the user's
 * current tenant, so the assistant acts in the workspace it was created from.
 */
class ConnectJudeController extends Controller
{
    public function index()
    {
        return view('connect-jude', [
            'tokens' => DesktopToken::where('user_id', auth()->id())->latest()->get(),
            'fresh' => session('fresh_jude_token'),
        ]);
    }

    public function generate(Request $request)
    {
        $name = trim($request->input('name')) ?: 'Jude';

        $issued = DesktopToken::issue(auth()->user(), $name);
        // Bind the token to the workspace the user is currently in.
        $issued['token']->update(['tenant_id' => session('current_tenant_id')]);

        return back()->with('fresh_jude_token', $issued['plain']);
    }

    public function revoke(DesktopToken $token)
    {
        abort_unless($token->user_id === auth()->id(), 403);
        $token->delete();

        return back()->with('status', 'Token revoked.');
    }
}
