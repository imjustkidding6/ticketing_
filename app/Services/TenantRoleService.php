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
        'view tickets',
        'create tickets',
        'update tickets',
        'delete tickets',
        'assign tickets',
        'close tickets',
        'view all tickets',
        'manage clients',
        'manage categories',
        'manage products',
        'manage sla',
        'view reports',
        'manage users',
        'manage roles',
        'manage departments',
        'manage settings',
    ];

    /**
     * Role definitions with their permissions.
     *
     * @var array<string, list<string>>
     */
    public const ROLE_PERMISSIONS = [
        'admin' => [
            'view tickets',
            'create tickets',
            'update tickets',
            'delete tickets',
            'assign tickets',
            'close tickets',
            'view all tickets',
            'manage clients',
            'manage categories',
            'manage products',
            'manage sla',
            'view reports',
            'manage users',
            'manage roles',
            'manage departments',
            'manage settings',
        ],
        'manager' => [
            'view tickets',
            'create tickets',
            'update tickets',
            'delete tickets',
            'assign tickets',
            'close tickets',
            'view all tickets',
            'manage clients',
            'manage categories',
            'manage products',
            'manage sla',
            'view reports',
            'manage users',
            'manage departments',
        ],
        'agent' => [
            'view tickets',
            'create tickets',
            'update tickets',
            'close tickets',
            'view reports',
        ],
    ];

    /**
     * Set up default roles and permissions for a new tenant.
     */
    public function setupDefaultRoles(Tenant $tenant): void
    {
        $this->setTenantContext($tenant);
        $this->ensurePermissionsExist();

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
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
