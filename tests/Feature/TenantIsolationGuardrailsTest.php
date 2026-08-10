<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Guardrails against cross-tenant leaks in Blade views.
 *
 * Background: the admin SLA revamp repurposed the tenant views in
 * resources/views/sla/* — switching them to layouts.admin and running an
 * unscoped Ticket::withoutGlobalScopes() query inline — so every tenant
 * saw the admin UI and other tenants' tickets. These tests fail any PR
 * that reintroduces either pattern.
 *
 * Rules enforced:
 *  - Views outside resources/views/admin/ must not extend layouts.admin.
 *    Admin screens get their own view under admin/ instead of hijacking
 *    a tenant view.
 *  - withoutGlobalScopes in a view outside admin/ must carry a tenant_id
 *    filter in the same statement (same line), e.g.
 *    AppSetting::withoutGlobalScopes()->where('tenant_id', $tenant->id).
 *    Admin views are exempt: the admin panel is cross-tenant by design.
 */
class TenantIsolationGuardrailsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string> relative path => contents for every
     *                               Blade file outside resources/views/admin/
     */
    private function nonAdminBladeFiles(): array
    {
        $views = [];
        foreach (File::allFiles(resource_path('views')) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            if (! str_ends_with($relative, '.blade.php') || str_starts_with($relative, 'admin/')) {
                continue;
            }
            $views[$relative] = $file->getContents();
        }

        $this->assertNotEmpty($views, 'No Blade views found — is resource_path(\'views\') correct?');

        return $views;
    }

    public function test_views_outside_admin_never_extend_the_admin_layout(): void
    {
        $offenders = [];
        foreach ($this->nonAdminBladeFiles() as $path => $contents) {
            if (preg_match('/@extends\(\s*[\'"]layouts\.admin/', $contents)) {
                $offenders[] = $path;
            }
        }

        $this->assertSame([], $offenders, sprintf(
            "Tenant-facing views must not use the admin layout. Move admin screens to resources/views/admin/ instead of repurposing tenant views: \n - %s",
            implode("\n - ", $offenders)
        ));
    }

    public function test_views_outside_admin_never_bypass_tenant_scope_without_a_tenant_filter(): void
    {
        $offenders = [];
        foreach ($this->nonAdminBladeFiles() as $path => $contents) {
            foreach (explode("\n", $contents) as $i => $line) {
                if (str_contains($line, 'withoutGlobalScopes') && ! str_contains($line, 'tenant_id')) {
                    $offenders[] = $path.':'.($i + 1);
                }
            }
        }

        $this->assertSame([], $offenders, sprintf(
            "withoutGlobalScopes() in a tenant-facing view leaks data across tenants unless the same statement filters by tenant_id (keep the ->where('tenant_id', ...) on the same line): \n - %s",
            implode("\n - ", $offenders)
        ));
    }

    public function test_tenant_sla_page_renders_tenant_view_without_foreign_data(): void
    {
        $plan = Plan::factory()->create(['slug' => 'business', 'features' => PlanFeature::forPlan('business')]);

        $tenantA = Tenant::factory()->create([
            'license_id' => License::factory()->active()->forPlan($plan)->create()->id,
        ]);
        $tenantB = Tenant::factory()->create([
            'license_id' => License::factory()->active()->forPlan($plan)->create()->id,
        ]);

        SlaPolicy::factory()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'ZZZ Foreign Tenant Policy',
        ]);

        $user = User::factory()->create();
        $tenantA->addUser($user, 'admin');

        $roleService = app(TenantRoleService::class);
        $roleService->setupDefaultRoles($tenantA);
        $roleService->syncRole($user, 'admin', $tenantA);

        $response = $this->actingAs($user)
            ->withTenant($tenantA)
            ->withSession(['current_tenant_id' => $tenantA->id])
            ->get($this->tenantUrl('/sla'));

        $response->assertOk()
            ->assertViewIs('sla.index')
            // The admin layout renders an admin-sidebar element; the tenant page must not.
            ->assertDontSee('admin-sidebar')
            ->assertDontSee('ZZZ Foreign Tenant Policy');
    }

    public function test_admin_sla_page_uses_the_admin_view(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/admin/sla')
            ->assertOk()
            ->assertViewIs('admin.sla.index');
    }
}
