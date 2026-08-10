<?php

namespace App\Assistant\Concerns;

/**
 * Permission checks for assistant tools.
 *
 * The token API has no HTTP session-based auth flow, so tools can't use the
 * controllers' checkPermission() (which aborts 403). Instead each tool asks
 * for the permissions its UI equivalent requires and returns an error array
 * the Hub can read back to the user.
 *
 * Mirrors Controller::checkPermission(): owners bypass all checks. Requires the
 * Spatie team id to be set for the active tenant — SetTenantContext does that.
 */
trait AuthorizesTenantUser
{
    /**
     * Returns an error payload when the user lacks the permission, else null.
     *
     * @return array{error: string}|null
     */
    protected function denyUnless(mixed $user, string $permission): ?array
    {
        if ($this->userMay($user, $permission)) {
            return null;
        }

        return ['error' => 'You do not have permission to '.$permission.' in this workspace.'];
    }

    /**
     * Whether the user holds the permission in their active tenant.
     */
    protected function userMay(mixed $user, string $permission): bool
    {
        if (! $user) {
            return false;
        }

        // Owners bypass all permission checks.
        $tenant = $user->currentTenant();
        if ($tenant && $user->roleInTenant($tenant) === 'owner') {
            return true;
        }

        return $user->can($permission);
    }
}
