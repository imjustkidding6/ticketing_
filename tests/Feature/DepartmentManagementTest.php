<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\Department;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\TenantRoleService;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createEnterpriseTenant(): Tenant
    {
        $plan = Plan::factory()->create([
            'slug' => 'enterprise',
            'features' => PlanFeature::forPlan('enterprise'),
        ]);
        $license = License::factory()->active()->forPlan($plan)->create();

        return Tenant::factory()->create(['license_id' => $license->id]);
    }

    private function createStarterTenant(): Tenant
    {
        $plan = Plan::factory()->start()->create([
            'features' => PlanFeature::forPlan('start'),
        ]);
        $license = License::factory()->active()->forPlan($plan)->create();

        return Tenant::factory()->create(['license_id' => $license->id]);
    }

    private function setupTenantContext(Tenant $tenant): User
    {
        $user = User::factory()->create();
        $tenant->addUser($user, 'member');

        $roleService = app(TenantRoleService::class);
        $roleService->setupDefaultRoles($tenant);
        $roleService->syncRole($user, 'admin', $tenant);

        $this->actingAs($user)
            ->withTenant($tenant)
            ->withSession(['current_tenant_id' => $tenant->id]);

        return $user;
    }

    public function test_departments_index_returns_403_for_starter_plan(): void
    {
        $tenant = $this->createStarterTenant();
        $this->setupTenantContext($tenant);

        $this->get($this->tenantUrl('/departments'))
            ->assertForbidden();
    }

    public function test_departments_index_accessible_for_enterprise_plan(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $this->get($this->tenantUrl('/departments'))
            ->assertOk();
    }

    public function test_can_create_department(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $this->post($this->tenantUrl('/departments'), [
            'name' => 'Engineering',
            'code' => 'ENG',
            'color' => '#3b82f6',
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('departments', [
            'tenant_id' => $tenant->id,
            'name' => 'Engineering',
            'code' => 'ENG',
        ]);
    }

    public function test_can_update_department(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $dept = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Old Name']);

        $this->put($this->tenantUrl("/departments/{$dept->id}"), [
            'name' => 'New Name',
            'code' => $dept->code,
            'color' => $dept->color ?? '#3b82f6',
            'is_active' => true,
        ])->assertRedirect();

        $dept->refresh();
        $this->assertEquals('New Name', $dept->name);
    }

    public function test_cannot_delete_default_department(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $dept = Department::factory()->create([
            'tenant_id' => $tenant->id,
            'is_default' => true,
        ]);

        $this->delete($this->tenantUrl("/departments/{$dept->id}"))
            ->assertRedirect();

        $this->assertDatabaseHas('departments', ['id' => $dept->id]);
    }

    public function test_cannot_delete_department_with_categories(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $dept = Department::factory()->create([
            'tenant_id' => $tenant->id,
            'is_default' => false,
        ]);

        TicketCategory::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $dept->id,
        ]);

        $this->delete($this->tenantUrl("/departments/{$dept->id}"))
            ->assertRedirect();

        $this->assertDatabaseHas('departments', ['id' => $dept->id]);
    }

    public function test_can_delete_empty_non_default_department(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $dept = Department::factory()->create([
            'tenant_id' => $tenant->id,
            'is_default' => false,
        ]);

        $this->delete($this->tenantUrl("/departments/{$dept->id}"))
            ->assertRedirect();

        $this->assertDatabaseMissing('departments', ['id' => $dept->id]);
    }

    /**
     * The toggle that decides whether tickets in this department are asked for the technical
     * half of the declared detail set (App\Support\TicketTechnicalFields). It rides on
     * `update departments`, already enforced by the controller.
     */
    public function test_a_department_can_be_marked_as_handling_technical_work(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $dept = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Human Resources']);

        // Default off: the safe reading of an unanswered question is "not technical", which
        // asks for fewer fields rather than fabricating a requirement.
        $this->assertFalse($dept->refresh()->is_technical);

        $this->get($this->tenantUrl("/departments/{$dept->id}/edit"))
            ->assertOk()
            ->assertSee('Handles technical work');

        $this->put($this->tenantUrl("/departments/{$dept->id}"), [
            'name' => 'Technical Software',
            'code' => $dept->code,
            'color' => $dept->color ?? '#3b82f6',
            'is_active' => true,
            'is_technical' => true,
        ])->assertRedirect();

        $this->assertTrue($dept->refresh()->is_technical);

        // And off again — the checkbox posts its own hidden 0, so unticking persists.
        $this->put($this->tenantUrl("/departments/{$dept->id}"), [
            'name' => 'Technical Software',
            'code' => $dept->code,
            'color' => $dept->color ?? '#3b82f6',
            'is_active' => true,
            'is_technical' => false,
        ])->assertRedirect();

        $this->assertFalse($dept->refresh()->is_technical);
    }

    public function test_the_seeded_technical_defaults_are_marked_technical(): void
    {
        $tenant = $this->createEnterpriseTenant();

        DepartmentSeeder::seedForTenant($tenant);

        $byCode = Department::withoutGlobalScopes()->where('tenant_id', $tenant->id)->pluck('is_technical', 'code');

        $this->assertTrue((bool) $byCode['SOFTWARE']);
        $this->assertTrue((bool) $byCode['HARDWARE']);
        // Everything else stays off: HR, Sales, Finance and the rest ask only who is
        // affected and whether there is a workaround.
        $this->assertFalse((bool) $byCode['SALES']);
        $this->assertFalse((bool) $byCode['CS']);
    }
}
