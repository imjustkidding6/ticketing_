<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AppSetting;

/**
 * The per-tenant opt-in for the AI agents configuration screens.
 *
 * Two independent questions: the plan (`feature:ai_agents` on the route) and whether
 * this workspace has switched the feature on. A concern rather than middleware because
 * the exemption is per-action — the setup hub is where the switch lives, so it must stay
 * reachable while the flag is off, or the feature can never be turned back on.
 *
 * 403, not 404: the route exists for every Enterprise workspace and there is nothing to
 * conceal. The screen is off, not secret.
 *
 * `AppSetting::get()` reads `session('current_tenant_id')` and returns the default when
 * it is absent, so this is a route-and-UI gate only. Nothing session-free — the catalogue
 * installer, the artisan command, a later selection service — may consult the flag.
 */
trait RequiresAiAgentsEnabled
{
    protected function ensureAiAgentsEnabled(): void
    {
        abort_unless(
            (bool) AppSetting::get('ai_agents_enabled', false),
            403,
            'AI agents are switched off for this workspace.',
        );
    }
}
