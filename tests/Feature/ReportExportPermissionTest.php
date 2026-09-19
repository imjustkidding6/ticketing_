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
use Tests\TestCase;

/**
 * "Export CSV button on Reports page not functioning."
 *
 * The report pages need only `view reports`, but every export action needs an
 * `export …` permission the role matrix deliberately withholds from agents. The
 * buttons were gated on the plan feature alone, so `senior agent`, `agent` and
 * `viewer` saw a control that 403s. The fix is in the view, not the matrix: a
 * rendered control must be one the reader can actually use.
 */
class ReportExportPermissionTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, array{0: string, 1: string}> report path => [export path, permission] */
    private const EXPORTS = [
        '/reports' => ['/reports/export/volume', 'export reports'],
        '/reports/tickets' => ['/reports/export/tickets', 'export tickets'],
        '/reports/clients' => ['/reports/export/clients', 'export clients'],
        '/reports/departments' => ['/reports/export/departments', 'export reports'],
        '/reports/categories' => ['/reports/export/categories', 'export reports'],
        '/reports/products' => ['/reports/export/products', 'export reports'],
        '/reports/reopens' => ['/reports/export/reopens', 'export reports'],
    ];

    private function tenant(): Tenant
    {
        // Enterprise: the reopen report is gated on ticket_reopening. Slug is unique
        // per call because one test builds two workspaces.
        $plan = Plan::factory()->create([
            'slug' => 'enterprise-'.uniqid(),
            'features' => PlanFeature::forPlan('enterprise'),
        ]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);
        SlaPolicy::factory()->create([
            'tenant_id' => $tenant->id, 'priority' => null,
            'client_tier' => null, 'is_active' => true,
        ]);

        return $tenant;
    }

    private function actAs(Tenant $tenant, string $role): User
    {
        $user = User::factory()->create();
        $tenant->addUser($user, 'member');

        $roleService = app(TenantRoleService::class);
        $roleService->setTenantContext($tenant);
        $roleService->setupDefaultRoles($tenant);
        $roleService->syncRole($user, $role, $tenant);

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);

        return $user;
    }

    /**
     * The bug as filed: the button renders and the endpoint refuses it.
     */
    public function test_agent_is_not_offered_an_export_it_cannot_run(): void
    {
        $tenant = $this->tenant();
        $this->actAs($tenant, 'agent');

        foreach (self::EXPORTS as $page => [$export, $permission]) {
            $response = $this->get($this->tenantUrl($page));
            $response->assertOk();
            $response->assertDontSee($this->tenantUrl($export), false);

            $this->get($this->tenantUrl($export))->assertForbidden();
        }
    }

    public function test_viewer_and_senior_agent_are_not_offered_exports_either(): void
    {
        foreach (['viewer', 'senior agent'] as $role) {
            $tenant = $this->tenant();
            $this->actAs($tenant, $role);

            foreach (self::EXPORTS as $page => [$export, $permission]) {
                $this->get($this->tenantUrl($page))
                    ->assertOk()
                    ->assertDontSee($this->tenantUrl($export), false);
            }
        }
    }

    /**
     * The gate must not swing the other way: a role that holds the permission
     * still gets the button, and it still downloads.
     */
    public function test_manager_keeps_every_export_button_and_it_works(): void
    {
        $tenant = $this->tenant();
        $this->actAs($tenant, 'manager');

        foreach (self::EXPORTS as $page => [$export, $permission]) {
            $this->get($this->tenantUrl($page))
                ->assertOk()
                ->assertSee($this->tenantUrl($export), false);

            $this->get($this->tenantUrl($export))->assertOk();
        }
    }

    /**
     * Owners hold no Spatie role and bypass every permission check, so a button
     * gated on a bare can() would vanish for the one person who may always export.
     */
    public function test_owner_keeps_every_export_button(): void
    {
        $tenant = $this->tenant();
        $user = User::factory()->create();
        $tenant->addUser($user, 'owner');

        $roleService = app(TenantRoleService::class);
        $roleService->setTenantContext($tenant);
        $roleService->setupDefaultRoles($tenant);

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);

        foreach (self::EXPORTS as $page => [$export, $permission]) {
            $this->get($this->tenantUrl($page))
                ->assertOk()
                ->assertSee($this->tenantUrl($export), false);
        }
    }
}
