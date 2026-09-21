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
 * Behavioural cover for every file in TenantIsolationGuardrailsTest::DUAL_SURFACE
 * — the files outside App\Http\Controllers\Admin\ that decide what they are from
 * routeIs('admin.*').
 *
 * **This file is the guarantee; the grep next door is only the reminder.** The
 * registry check can prove that a dual-surface file is claimed by a test. It
 * cannot prove the test asserts anything useful, and no static rule can decide
 * whether a scope bypass is right, because surface is a property of the request
 * and not of the file. /admin/sla was cross-tenant only by accident — the scope
 * disappeared because no tenant was in session — and a page that renders is not
 * a page that is scoped. So each test below asserts the data: a second tenant's
 * row exists and must not appear.
 *
 * TutorialController's half of the registry points at HelpPermissionSweepTest,
 * which already walks every seeded role against every topic and tour — a thinner
 * assertion here would pass with the permission filter removed.
 *
 * The admin (cross-tenant) half of SlaPolicyController lives in
 * TenantIsolationGuardrailsTest::test_admin_sla_page_uses_the_admin_view_and_is_cross_tenant
 * and is referenced rather than duplicated.
 */
class DualSurfaceScopeTest extends TestCase
{
    use RefreshDatabase;

    private function businessTenant(): Tenant
    {
        // One shared plan row: plans.slug is unique, and these tests need two
        // workspaces on the same plan.
        $plan = Plan::firstWhere('slug', 'business')
            ?? Plan::factory()->create(['slug' => 'business', 'features' => PlanFeature::forPlan('business')]);

        return Tenant::factory()->create([
            'license_id' => License::factory()->active()->forPlan($plan)->create()->id,
        ]);
    }

    private function adminUserFor(Tenant $tenant): User
    {
        $user = User::factory()->create();
        $tenant->addUser($user, 'admin');

        $roles = app(TenantRoleService::class);
        $roles->setupDefaultRoles($tenant);
        $roles->syncRole($user, 'admin', $tenant);

        return $user;
    }

    public function test_tenant_sla_index_is_scoped_for_a_tenant_user(): void
    {
        $tenantA = $this->businessTenant();
        $tenantB = $this->businessTenant();

        // The tenant SLA screens are tier/priority grids: they never print a
        // policy name, so asserting on names would pass whatever the scope did.
        // The hours are the data, so the hours are what is asserted.
        SlaPolicy::factory()->create([
            'tenant_id' => $tenantA->id, 'client_tier' => 'premium', 'priority' => 'high',
            'response_time_hours' => 17, 'resolution_time_hours' => 1717, 'is_active' => true,
        ]);
        SlaPolicy::factory()->create([
            'tenant_id' => $tenantB->id, 'client_tier' => 'premium', 'priority' => 'high',
            'response_time_hours' => 42, 'resolution_time_hours' => 4242, 'is_active' => true,
        ]);

        $user = $this->adminUserFor($tenantA);

        $this->actingAs($user)
            ->withTenant($tenantA)
            ->withSession(['current_tenant_id' => $tenantA->id])
            ->get($this->tenantUrl('/sla'))
            ->assertOk()
            ->assertViewIs('sla.index')
            ->assertSee('1717h')
            ->assertDontSee('4242h');
    }

    /**
     * The sharper case: with no policy of its own, a leak has nothing to hide
     * behind — the tier grid would quietly render the other workspace's row.
     */
    public function test_tenant_sla_index_shows_an_empty_tier_rather_than_another_tenants_policy(): void
    {
        $tenantA = $this->businessTenant();
        $tenantB = $this->businessTenant();

        SlaPolicy::factory()->create([
            'tenant_id' => $tenantB->id, 'client_tier' => 'premium', 'priority' => 'high',
            'response_time_hours' => 42, 'resolution_time_hours' => 4242, 'is_active' => true,
        ]);

        $user = $this->adminUserFor($tenantA);

        $this->actingAs($user)
            ->withTenant($tenantA)
            ->withSession(['current_tenant_id' => $tenantA->id])
            ->get($this->tenantUrl('/sla'))
            ->assertOk()
            ->assertDontSee('4242h')
            ->assertSee('No policy yet for premium');
    }

    public function test_tenant_sla_tier_editor_is_scoped_for_a_tenant_user(): void
    {
        $tenantA = $this->businessTenant();
        $tenantB = $this->businessTenant();

        SlaPolicy::factory()->create([
            'tenant_id' => $tenantA->id, 'client_tier' => 'premium', 'priority' => 'high',
            'response_time_hours' => 17, 'resolution_time_hours' => 1717, 'is_active' => true,
        ]);
        SlaPolicy::factory()->create([
            'tenant_id' => $tenantB->id, 'client_tier' => 'premium', 'priority' => 'high',
            'response_time_hours' => 42, 'resolution_time_hours' => 4242, 'is_active' => true,
        ]);

        $user = $this->adminUserFor($tenantA);

        $this->actingAs($user)
            ->withTenant($tenantA)
            ->withSession(['current_tenant_id' => $tenantA->id])
            ->get($this->tenantUrl('/sla/tier/premium/edit'))
            ->assertOk()
            ->assertViewIs('sla.edit-tier')
            ->assertSee('value="1717"', false)
            ->assertDontSee('value="4242"', false);
    }

    /**
     * Controller::checkPermission() lets platform admins through, but only on
     * admin.* routes. On a tenant route an is_admin user holds no tenant role
     * and must be refused like anyone else — otherwise the bypass becomes a
     * cross-tenant key to every workspace's pages.
     */
    public function test_platform_admin_bypass_does_not_apply_on_tenant_routes(): void
    {
        $tenant = $this->businessTenant();

        $admin = User::factory()->create(['is_admin' => true]);
        $tenant->addUser($admin, 'member');
        app(TenantRoleService::class)->setupDefaultRoles($tenant);

        $this->actingAs($admin)
            ->withTenant($tenant)
            ->withSession(['current_tenant_id' => $tenant->id])
            ->get($this->tenantUrl('/sla'))
            ->assertForbidden();
    }
}
