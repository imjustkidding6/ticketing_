<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RolePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOnPlan(string $slug): Tenant
    {
        $plan = Plan::factory()->create(['slug' => $slug, 'features' => PlanFeature::forPlan($slug)]);
        $license = License::factory()->active()->forPlan($plan)->create();

        return Tenant::factory()->create(['license_id' => $license->id]);
    }

    public function test_permission_catalogue_is_unique_and_grouped(): void
    {
        $all = TenantRoleService::allPermissions();

        $this->assertSame($all, array_values(array_unique($all)), 'Permissions must not repeat across groups.');
        $this->assertGreaterThan(60, count($all));
    }

    /**
     * Every permission must actually be checked somewhere. A permission that is
     * granted but never enforced is decorative — it implies protection that does
     * not exist. This is the check that caught 14 such permissions.
     */
    public function test_no_permission_is_defined_without_being_enforced(): void
    {
        $haystack = '';
        foreach (['app', 'resources/views', 'routes'] as $dir) {
            foreach ($this->phpFilesIn(base_path($dir)) as $file) {
                // The catalogue itself doesn't count as enforcement.
                if (str_ends_with($file, 'TenantRoleService.php')) {
                    continue;
                }
                $haystack .= file_get_contents($file);
            }
        }

        $dead = array_values(array_filter(
            TenantRoleService::allPermissions(),
            fn (string $permission) => ! str_contains($haystack, "'{$permission}'"),
        ));

        $this->assertSame([], $dead, 'Permissions granted but never enforced: '.implode(', ', $dead));
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string $dir): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        foreach ($it as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['php'], true)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    public function test_every_role_grant_is_a_real_permission(): void
    {
        $valid = TenantRoleService::allPermissions();
        $tenant = $this->tenantOnPlan('enterprise');

        foreach (app(TenantRoleService::class)->defaultsForTenant($tenant) as $role => $granted) {
            foreach ($granted as $permission) {
                $this->assertContains($permission, $valid, "Role '{$role}' grants unknown permission '{$permission}'.");
            }
        }
    }

    public function test_every_legacy_permission_maps_onto_current_ones(): void
    {
        $valid = TenantRoleService::allPermissions();

        foreach (TenantRoleService::LEGACY_PERMISSION_MAP as $legacy => $granular) {
            $this->assertNotEmpty($granular, "Legacy permission '{$legacy}' maps to nothing.");

            foreach ($granular as $permission) {
                $this->assertContains($permission, $valid, "Legacy '{$legacy}' maps to unknown '{$permission}'.");
            }
        }
    }

    public function test_setup_creates_every_default_role(): void
    {
        $tenant = $this->tenantOnPlan('enterprise');
        app(TenantRoleService::class)->setupDefaultRoles($tenant);

        foreach (TenantRoleService::defaultRoleNames() as $roleName) {
            $this->assertDatabaseHas('roles', ['name' => $roleName, 'tenant_id' => $tenant->id]);
        }

        $this->assertCount(count(TenantRoleService::allPermissions()), Permission::all());
    }

    public function test_viewer_is_strictly_read_only(): void
    {
        $tenant = $this->tenantOnPlan('enterprise');
        $service = app(TenantRoleService::class);
        $service->setupDefaultRoles($tenant);

        $granted = $service->defaultsForTenant($tenant)['viewer'];

        foreach ($granted as $permission) {
            $this->assertStringStartsWith('view ', $permission, "Viewer must not hold '{$permission}'.");
        }
    }

    public function test_feature_gated_permissions_are_withheld_on_lower_plans(): void
    {
        $service = app(TenantRoleService::class);

        $starterAdmin = $service->defaultsForTenant($this->tenantOnPlan('start'))['admin'];
        $enterpriseAdmin = $service->defaultsForTenant($this->tenantOnPlan('enterprise'))['admin'];

        // Enterprise-only surfaces.
        $this->assertNotContains('use ai assistant', $starterAdmin);
        $this->assertNotContains('view kb articles', $starterAdmin);
        $this->assertContains('use ai assistant', $enterpriseAdmin);
        $this->assertContains('view kb articles', $enterpriseAdmin);

        // Core ticket work is available on every plan.
        $this->assertContains('view tickets', $starterAdmin);
        $this->assertContains('create tickets', $starterAdmin);
    }

    public function test_migration_command_expands_legacy_custom_roles(): void
    {
        $tenant = $this->tenantOnPlan('enterprise');
        $service = app(TenantRoleService::class);
        $service->setupDefaultRoles($tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        // A custom role still holding a pre-expansion permission name.
        Permission::findOrCreate('manage clients', 'web');
        $custom = Role::create(['name' => 'client lead', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $custom->syncPermissions(['manage clients']);

        $this->artisan('roles:migrate-permissions', ['--tenant' => $tenant->id])
            ->assertSuccessful();

        $names = $custom->fresh()->permissions->pluck('name')->all();

        $this->assertContains('view clients', $names);
        $this->assertContains('create clients', $names);
        $this->assertContains('update clients', $names);
        $this->assertContains('delete clients', $names);
        $this->assertContains('export clients', $names);
        $this->assertNotContains('manage clients', $names);
    }

    public function test_migration_dry_run_changes_nothing(): void
    {
        $tenant = $this->tenantOnPlan('enterprise');
        app(TenantRoleService::class)->setupDefaultRoles($tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        Permission::findOrCreate('manage clients', 'web');
        $custom = Role::create(['name' => 'client lead', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $custom->syncPermissions(['manage clients']);

        $this->artisan('roles:migrate-permissions', ['--tenant' => $tenant->id, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(['manage clients'], $custom->fresh()->permissions->pluck('name')->all());
    }
}
