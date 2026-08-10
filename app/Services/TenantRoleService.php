<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TenantRoleService
{
    /**
     * Every permission in the system, grouped by the area it governs.
     *
     * The group key is the heading shown in the custom-role editor; keep the
     * groups meaningful, since that UI is generated from this map.
     *
     * @var array<string, list<string>>
     */
    public const PERMISSION_GROUPS = [
        'Tickets' => [
            'view tickets',
            'view all tickets',
            'create tickets',
            'update tickets',
            'delete tickets',
            'assign tickets',
            'close tickets',
            'reopen tickets',
            'merge tickets',
            'unmerge tickets',
            'escalate tickets',
            'export tickets',
        ],
        'Ticket collaboration' => [
            'view ticket comments',
            'create ticket comments',
            'update ticket comments',
            'delete ticket comments',
            'create internal notes',
            'manage ticket tasks',
            'view attachments',
            'upload attachments',
        ],
        'Clients' => [
            'view clients',
            'create clients',
            'update clients',
            'delete clients',
            'export clients',
        ],
        'Catalog' => [
            'view categories',
            'create categories',
            'update categories',
            'delete categories',
            'view products',
            'create products',
            'update products',
            'delete products',
        ],
        'Departments' => [
            'view departments',
            'create departments',
            'update departments',
            'delete departments',
        ],
        'SLA' => [
            'view sla policies',
            'create sla policies',
            'update sla policies',
            'delete sla policies',
        ],
        'Knowledge base' => [
            'view kb articles',
            'create kb articles',
            'update kb articles',
            'delete kb articles',
            'manage kb categories',
        ],
        'Canned responses' => [
            'view canned responses',
            'create canned responses',
            'update canned responses',
            'delete canned responses',
        ],
        'Reports' => [
            'view reports',
            'view sla reports',
            'view agent performance',
            'view service reports',
            'export reports',
        ],
        'Operations' => [
            'view activity logs',
            'view schedules',
            'manage schedules',
            'view billing',
            'manage billing',
        ],
        'People' => [
            'view members',
            'invite members',
            'update members',
            'remove members',
            'view roles',
            'create roles',
            'update roles',
            'delete roles',
        ],
        'Configuration' => [
            'view settings',
            'update settings',
            'manage branding',
            'manage email settings',
            'manage api tokens',
        ],
        'AI' => [
            'use ai assistant',
            'manage ai settings',
            'manage ai knowledge',
        ],
    ];

    /**
     * Flat list of every permission — the union of PERMISSION_GROUPS.
     *
     * @return list<string>
     */
    public static function allPermissions(): array
    {
        return array_values(array_merge(...array_values(self::PERMISSION_GROUPS)));
    }

    /**
     * Maps each pre-expansion permission onto the granular ones that replaced it.
     *
     * Used by `roles:migrate-permissions` to translate existing roles (including
     * tenant custom roles) without anyone silently losing access.
     *
     * @var array<string, list<string>>
     */
    public const LEGACY_PERMISSION_MAP = [
        'view tickets' => ['view tickets', 'view ticket comments', 'view attachments'],
        'create tickets' => ['create tickets', 'create ticket comments', 'create internal notes', 'manage ticket tasks', 'upload attachments'],
        'update tickets' => ['update tickets', 'update ticket comments', 'manage ticket tasks'],
        'delete tickets' => ['delete tickets', 'delete ticket comments'],
        'assign tickets' => ['assign tickets'],
        'close tickets' => ['close tickets'],
        'reopen tickets' => ['reopen tickets'],
        'merge tickets' => ['merge tickets'],
        'unmerge tickets' => ['unmerge tickets'],
        'view all tickets' => ['view all tickets'],
        'manage clients' => ['view clients', 'create clients', 'update clients', 'delete clients', 'export clients'],
        'manage categories' => ['view categories', 'create categories', 'update categories', 'delete categories'],
        'manage products' => ['view products', 'create products', 'update products', 'delete products'],
        'manage sla' => ['view sla policies', 'create sla policies', 'update sla policies', 'delete sla policies'],
        'view reports' => ['view reports', 'view sla reports', 'view agent performance', 'view service reports', 'export reports'],
        'view activity logs' => ['view activity logs'],
        'manage billing' => ['view billing', 'manage billing'],
        'manage schedules' => ['view schedules', 'manage schedules'],
        'manage users' => ['view members', 'invite members', 'update members', 'remove members'],
        'manage roles' => ['view roles', 'create roles', 'update roles', 'delete roles'],
        'manage departments' => ['view departments', 'create departments', 'update departments', 'delete departments'],
        'manage settings' => ['view settings', 'update settings', 'manage branding', 'manage email settings', 'manage api tokens', 'manage ai settings', 'manage ai knowledge'],
    ];

    /**
     * Backwards-compat alias. Prefer allPermissions().
     *
     * @var list<string>
     *
     * @deprecated
     */
    public const PERMISSIONS = [
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
        'manage clients',
        'manage categories',
        'manage products',
        'manage sla',
        'view reports',
        'view activity logs',
        'manage billing',
        'manage schedules',
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
            'view tickets', 'view all tickets', 'create tickets', 'update tickets',
            'delete tickets', 'assign tickets', 'close tickets', 'export tickets',
            'view ticket comments', 'create ticket comments', 'update ticket comments',
            'delete ticket comments', 'create internal notes', 'manage ticket tasks',
            'view attachments', 'upload attachments',
            'view clients', 'create clients', 'update clients', 'delete clients', 'export clients',
            'view categories', 'create categories', 'update categories', 'delete categories',
            'view products', 'create products', 'update products', 'delete products',
            'view departments',
            'view sla policies', 'create sla policies', 'update sla policies', 'delete sla policies',
            'view reports', 'export reports',
            'view members', 'invite members', 'update members', 'remove members',
            'view roles', 'create roles', 'update roles', 'delete roles',
            'view settings', 'update settings', 'manage branding', 'manage email settings', 'manage api tokens',
        ],
        'manager' => [
            'view tickets', 'view all tickets', 'create tickets', 'update tickets',
            'delete tickets', 'assign tickets', 'close tickets', 'export tickets',
            'view ticket comments', 'create ticket comments', 'update ticket comments',
            'delete ticket comments', 'create internal notes', 'manage ticket tasks',
            'view attachments', 'upload attachments',
            'view clients', 'create clients', 'update clients', 'delete clients', 'export clients',
            'view categories', 'create categories', 'update categories', 'delete categories',
            'view products', 'create products', 'update products', 'delete products',
            'view departments',
            'view sla policies', 'create sla policies', 'update sla policies', 'delete sla policies',
            'view reports', 'export reports',
            'view members', 'invite members', 'update members', 'remove members',
            'view roles',
            'view settings',
        ],
        // Team oversight without configuration access.
        'supervisor' => [
            'view tickets', 'view all tickets', 'create tickets', 'update tickets',
            'assign tickets', 'close tickets', 'export tickets',
            'view ticket comments', 'create ticket comments', 'update ticket comments',
            'create internal notes', 'manage ticket tasks',
            'view attachments', 'upload attachments',
            'view clients', 'create clients', 'update clients', 'export clients',
            'view categories', 'view products', 'view departments', 'view sla policies',
            'view reports', 'export reports',
            'view members',
        ],
        // A senior agent handles tickets end to end but does not oversee the team.
        'senior agent' => [
            'view tickets', 'view all tickets', 'create tickets', 'update tickets',
            'assign tickets', 'close tickets',
            'view ticket comments', 'create ticket comments', 'update ticket comments',
            'create internal notes', 'manage ticket tasks',
            'view attachments', 'upload attachments',
            'view clients', 'create clients', 'update clients',
            'view categories', 'view products', 'view departments', 'view sla policies',
            'view reports',
        ],
        'agent' => [
            'view tickets', 'create tickets', 'update tickets', 'close tickets',
            'view ticket comments', 'create ticket comments', 'update ticket comments',
            'create internal notes', 'manage ticket tasks',
            'view attachments', 'upload attachments',
            // No 'view clients': agents work tickets, they don't browse the client
            // directory. Client details still show on the tickets they handle.
            'view categories', 'view products', 'view departments',
            'view reports',
        ],
        // Read-only across the workspace — auditors, stakeholders, trainees.
        'viewer' => [
            'view tickets', 'view all tickets', 'view ticket comments', 'view attachments',
            'view clients', 'view categories', 'view products', 'view departments',
            'view sla policies', 'view reports',
        ],
        // Finance-facing: billing and reporting, no ticket operations.
        'billing' => [
            'view clients', 'view reports', 'export reports',
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
            'supervisor' => ['view activity logs'],
        ],
        'billing' => [
            'admin' => ['view billing', 'manage billing'],
            'manager' => ['view billing', 'manage billing'],
            'billing' => ['view billing', 'manage billing'],
        ],
        'agent_schedule' => [
            'admin' => ['view schedules', 'manage schedules'],
            'manager' => ['view schedules', 'manage schedules'],
            'supervisor' => ['view schedules', 'manage schedules'],
            'senior agent' => ['view schedules'],
            'agent' => ['view schedules'],
        ],
        'ticket_reopening' => [
            'admin' => ['reopen tickets'],
            'manager' => ['reopen tickets'],
            'supervisor' => ['reopen tickets'],
            'senior agent' => ['reopen tickets'],
            // Agents can reopen their own tickets by default (per requirements).
            'agent' => ['reopen tickets'],
        ],
        'ticket_merging' => [
            'admin' => ['merge tickets', 'unmerge tickets'],
            'manager' => ['merge tickets', 'unmerge tickets'],
            'supervisor' => ['merge tickets', 'unmerge tickets'],
        ],
        'agent_escalation' => [
            'admin' => ['escalate tickets'],
            'manager' => ['escalate tickets'],
            'supervisor' => ['escalate tickets'],
            'senior agent' => ['escalate tickets'],
        ],
        'department_management' => [
            'admin' => ['create departments', 'update departments', 'delete departments'],
            'manager' => ['create departments', 'update departments', 'delete departments'],
        ],
        'knowledge_base' => [
            'admin' => ['view kb articles', 'create kb articles', 'update kb articles', 'delete kb articles', 'manage kb categories'],
            'manager' => ['view kb articles', 'create kb articles', 'update kb articles', 'delete kb articles', 'manage kb categories'],
            'supervisor' => ['view kb articles', 'create kb articles', 'update kb articles'],
            'senior agent' => ['view kb articles', 'create kb articles'],
            'agent' => ['view kb articles'],
            'viewer' => ['view kb articles'],
        ],
        'canned_responses' => [
            'admin' => ['view canned responses', 'create canned responses', 'update canned responses', 'delete canned responses'],
            'manager' => ['view canned responses', 'create canned responses', 'update canned responses', 'delete canned responses'],
            'supervisor' => ['view canned responses', 'create canned responses', 'update canned responses'],
            'senior agent' => ['view canned responses'],
            'agent' => ['view canned responses'],
        ],
        'ai_chatbot' => [
            'admin' => ['use ai assistant', 'manage ai settings', 'manage ai knowledge'],
            'manager' => ['use ai assistant', 'manage ai knowledge'],
            'supervisor' => ['use ai assistant'],
            'senior agent' => ['use ai assistant'],
            'agent' => ['use ai assistant'],
        ],
        'sla_report' => [
            'admin' => ['view sla reports'],
            'manager' => ['view sla reports'],
            'supervisor' => ['view sla reports'],
        ],
        'service_reports' => [
            'admin' => ['view service reports'],
            'manager' => ['view service reports'],
            'supervisor' => ['view service reports'],
        ],
        'detailed_reporting' => [
            'admin' => ['view agent performance'],
            'manager' => ['view agent performance'],
            'supervisor' => ['view agent performance'],
        ],
    ];

    /**
     * Backwards-compat alias (some callers reference this).
     *
     * @var array<string, list<string>>
     */
    public const ROLE_PERMISSIONS = self::BASE_ROLE_PERMISSIONS;

    /**
     * Human-readable blurb per default role, for the member/role pickers.
     *
     * @var array<string, string>
     */
    public const ROLE_DESCRIPTIONS = [
        'admin' => 'Full access to tickets, people, and workspace configuration.',
        'manager' => 'Runs the queue and the team; no workspace configuration.',
        'supervisor' => 'Oversees tickets and agents; read-only on catalog and settings.',
        'senior agent' => 'Handles tickets end to end and can assign work.',
        'agent' => 'Works assigned tickets and talks to clients.',
        'viewer' => 'Read-only access across the workspace.',
        'billing' => 'Billing and reporting only; no ticket operations.',
    ];

    /**
     * The default role names, in order of decreasing access.
     *
     * @return list<string>
     */
    public static function defaultRoleNames(): array
    {
        return array_keys(self::BASE_ROLE_PERMISSIONS);
    }

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
        $existing = Permission::where('guard_name', 'web')->pluck('name')->all();
        $created = false;

        foreach (array_diff(self::allPermissions(), $existing) as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'web']);
            $created = true;
        }

        // syncPermissions() resolves names against Spatie's cached collection, so a
        // stale cache here surfaces as PermissionDoesNotExist for brand-new names.
        if ($created) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
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
     * @return Collection<int, Role>
     */
    public function getTenantRoles(Tenant $tenant): Collection
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
