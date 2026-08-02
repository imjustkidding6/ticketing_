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
        $user = Auth::user();

        if (! $user) {
            abort(403);
        }

        // Owners bypass all permission checks
        $tenant = $user->currentTenant();
        if ($tenant && $user->roleInTenant($tenant) === 'owner') {
            return;
        }

        if (! $user->can($permission)) {
            abort(403, 'You do not have permission to '.$permission.'.');
        }
    }
}
