<?php

namespace App\Assistant;

use App\Models\Tenant;
use Cliqueha\AssistantConnector\Models\DesktopToken;

/**
 * The token API is stateless, so set the active tenant from the token.
 *
 * A token generated on the "Connect Jude" page is stamped with the workspace it
 * was created in — use that so the assistant acts in the right workspace. Fall
 * back to the user's current/first workspace for tokens with no tenant (e.g.
 * ones minted via the CLI).
 */
class SetTenantContext
{
    public function __invoke($user): void
    {
        $token = DesktopToken::findByPlain(request()->bearerToken());

        if ($token && $token->tenant_id) {
            $tenant = Tenant::find($token->tenant_id);
            if ($tenant && $user->belongsToTenant($tenant)) {
                $user->setCurrentTenant($tenant);

                return;
            }
        }

        $user->ensureCurrentTenant();
    }
}
