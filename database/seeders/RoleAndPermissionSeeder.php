<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Services\TenantRoleService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (TenantRoleService::allPermissions() as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Reset every tenant's default roles (admin, manager, supervisor,
        // senior agent, agent, viewer, billing) back to the plan-aware
        // defaults from TenantRoleService. syncPermissions() replaces the
        // role's permission list outright; tenant custom roles are untouched.
        $roleService = new TenantRoleService;

        Tenant::query()->cursor()->each(function (Tenant $tenant) use ($roleService) {
            $roleService->setupDefaultRoles($tenant);
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
