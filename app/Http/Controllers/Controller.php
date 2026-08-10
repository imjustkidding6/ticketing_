<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    /**
     * Check if the current user has a Spatie permission.
     * Aborts with 403 if not.
     */
    protected function checkPermission(string $permission): void
    {
        if (! $this->userCan($permission)) {
            abort(403, 'You do not have permission to '.$permission.'.');
        }
    }

    /**
     * Whether the current user holds a permission, without aborting.
     *
     * Use for shaping a response (hiding a section, widening a query) rather than
     * denying the request outright. Carries the same bypasses as checkPermission().
     */
    protected function userCan(string $permission): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Platform admins on /admin/* operate across tenants by design and hold no
        // tenant role, so tenant permissions don't apply to them there. Controllers
        // that serve both surfaces (e.g. SlaPolicyController) rely on this.
        if ($user->is_admin && request()->routeIs('admin.*')) {
            return true;
        }

        // Owners bypass all permission checks
        $tenant = $user->currentTenant();
        if ($tenant && $user->roleInTenant($tenant) === 'owner') {
            return true;
        }

        return $user->can($permission);
    }
}
