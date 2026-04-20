<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TenantRoleService
{
    /**
     * All available permissions in the system.
     *
     * @var list<string>
     */
    public const PERMISSIONS = [
        // Ticket CRUD + lifecycle
        'view tickets',
        'create tickets',
        'update tickets',
        'delete tickets',
        'assign tickets',
        'close tickets',
        'reopen tickets',
        'merge tickets',
        'unmerge tickets',
        'view all tickets',
        // Domain objects
        'manage clients',
        'manage categories',
        'manage products',
        'manage sla',
        // Operations / reporting
        'view reports',
        'view activity logs',
        'manage billing',
        'manage schedules',
        // People / configuration
        'manage users',
        'manage roles',
        'manage departments',
        'manage settings',
    ];

    /**
     * Base permissions that apply to every plan.
     *
     * @var array<string, list<string>>
     */
    private const BASE_ROLE_PERMISSIONS = [
        'admin' => [
            'view tickets', 'create tickets', 'update tickets', 'delete tickets',
            'assign tickets', 'close tickets', 'view all tickets',
            'manage clients', 'manage categories', 'manage products', 'manage sla',
            'view reports',
            'manage users', 'manage roles', 'manage departments', 'manage settings',
        ],
        'manager' => [
            'view tickets', 'create tickets', 'update tickets', 'delete tickets',
            'assign tickets', 'close tickets', 'view all tickets',
            'manage clients', 'manage categories', 'manage products', 'manage sla',
            'view reports',
            'manage users', 'manage departments',
        ],
        'agent' => [
            'view tickets', 'create tickets', 'update tickets', 'close tickets',
            'view reports',
        ],
    ];

    /**
     * Permissions added on top of the base when the tenant plan includes a given feature.
     * Key = PlanFeature value, value = [role => extra permissions].
     *
     * @var array<string, array<string, list<string>>>
     */
    private const FEATURE_PERMISSIONS = [
        'audit_logs' => [
            'admin' => ['view activity logs'],
            'manager' => ['view activity logs'],
            'agent' => [],
        ],
        'billing' => [
            'admin' => ['manage billing'],
            'manager' => ['manage billing'],
            'agent' => [],
        ],
        'agent_schedule' => [
            'admin' => ['manage schedules'],
            'manager' => ['manage schedules'],
            'agent' => [],
        ],
        'ticket_reopening' => [
            'admin' => ['reopen tickets'],
            'manager' => ['reopen tickets'],
            // Agents can reopen their own tickets by default (per requirements).
            'agent' => ['reopen tickets'],
        ],
        'ticket_merging' => [
            'admin' => ['merge tickets', 'unmerge tickets'],
            'manager' => ['merge tickets', 'unmerge tickets'],
            'agent' => [],
        ],
    ];

    /**
     * Backwards-compat alias (some callers reference this).
     *
     * @var array<string, list<string>>
     */
    public const ROLE_PERMISSIONS = self::BASE_ROLE_PERMISSIONS;

    /**
     * Compute the default role => [permissions] map for a given tenant, based on its plan.
     *
     * @return array<string, list<string>>
     */
    public function defaultsForTenant(Tenant $tenant): array
    {
        $features = (array) ($tenant->license?->plan?->features ?? []);

        $roles = [];
        foreach (self::BASE_ROLE_PERMISSIONS as $role => $perms) {
            $roles[$role] = $perms;
            foreach ($features as $feature) {
                $extra = self::FEATURE_PERMISSIONS[$feature][$role] ?? [];
                if (! empty($extra)) {
                    $roles[$role] = array_values(array_unique(array_merge($roles[$role], $extra)));
                }
            }
        }

        return $roles;
    }

    /**
     * Set up default roles and permissions for a tenant, plan-aware.
     * Only the 3 default roles (admin/manager/agent) are reset; any custom roles
     * are left untouched. Only called for NEW tenants — safe to re-run since
     * it uses syncPermissions which normalizes the list.
     */
    public function setupDefaultRoles(Tenant $tenant): void
    {
        $this->setTenantContext($tenant);
        $this->ensurePermissionsExist();

        // Reload to ensure the license/plan relationship reflects any just-activated
        // license (e.g. during seeding or fresh tenant onboarding).
        $tenant->load('license.plan');

        foreach ($this->defaultsForTenant($tenant) as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions);
        }
    }

    /**
     * Ensure all permissions exist in the system.
     */
    public function ensurePermissionsExist(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    /**
     * Assign a role to a user within a tenant context.
     */
    public function assignRole(User $user, string $roleName, Tenant $tenant): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $user->assignRole($roleName);
    }

    /**
     * Remove a role from a user within a tenant context.
     */
    public function removeRole(User $user, string $roleName, Tenant $tenant): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $user->removeRole($roleName);
    }

    /**
     * Sync a user's role within a tenant context.
     */
    public function syncRole(User $user, string $roleName, Tenant $tenant): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $user->syncRoles([$roleName]);
    }

    /**
     * Get all roles for a tenant.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Role>
     */
    public function getTenantRoles(Tenant $tenant): \Illuminate\Database\Eloquent\Collection
    {
        return Role::where('tenant_id', $tenant->id)->get();
    }

    /**
     * Set the permission registrar to the given tenant context.
     */
    public function setTenantContext(Tenant $tenant): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    }
}
