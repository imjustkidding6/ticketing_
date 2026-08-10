<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantRoleService;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Translates roles from the coarse permission set onto the granular one.
 *
 * Default roles are re-seeded from TenantRoleService (which also creates the
 * roles added since: supervisor, senior agent, viewer, billing). Tenant custom
 * roles are expanded through LEGACY_PERMISSION_MAP so nobody silently loses
 * access when the old permission names disappear.
 */
class MigrateRolePermissions extends Command
{
    protected $signature = 'roles:migrate-permissions
                            {--dry-run : Show what would change without writing}
                            {--tenant= : Limit to a single tenant id}';

    protected $description = 'Migrate roles from the legacy permission names to the granular set';

    public function handle(TenantRoleService $roles): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun) {
            $roles->ensurePermissionsExist();
        }

        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($q, $id) => $q->where('id', $id))
            ->with('license.plan')
            ->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants matched.');

            return self::SUCCESS;
        }

        $defaults = TenantRoleService::defaultRoleNames();
        $migrated = 0;
        $custom = 0;

        foreach ($tenants as $tenant) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

            if (! $dryRun) {
                $roles->setupDefaultRoles($tenant);
            }
            $migrated++;

            // Custom roles keep their own grants — expand them in place.
            $customRoles = Role::where('tenant_id', $tenant->id)
                ->whereNotIn('name', $defaults)
                ->with('permissions')
                ->get();

            foreach ($customRoles as $role) {
                $current = $role->permissions->pluck('name')->all();
                $expanded = $this->expand($current);

                if ($expanded === [] || $expanded == $current) {
                    continue;
                }

                $this->line("  tenant {$tenant->id} · role '{$role->name}': ".count($current).' → '.count($expanded).' permissions');

                if (! $dryRun) {
                    $role->syncPermissions($expanded);
                }
                $custom++;
            }
        }

        $verb = $dryRun ? 'Would migrate' : 'Migrated';
        $this->info("{$verb} {$migrated} tenant(s) and {$custom} custom role(s).");

        return self::SUCCESS;
    }

    /**
     * Expand a list of permission names onto the granular set.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    private function expand(array $names): array
    {
        $valid = TenantRoleService::allPermissions();
        $expanded = [];

        foreach ($names as $name) {
            foreach (TenantRoleService::LEGACY_PERMISSION_MAP[$name] ?? [$name] as $granular) {
                // Drop anything that is neither a legacy name nor a current one.
                if (in_array($granular, $valid, true)) {
                    $expanded[] = $granular;
                }
            }
        }

        sort($expanded);

        return array_values(array_unique($expanded));
    }
}
