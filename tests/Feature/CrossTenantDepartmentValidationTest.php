<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\ApiToken;
use App\Models\Department;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A `department_id` arriving from a request must be rejected when it belongs to another
 * workspace. Validation runs before any model, so the global TenantScope never sees the
 * value — a bare `exists:departments,id` therefore accepts an id from any tenant on the
 * platform. These cover the three surfaces that accept one.
 *
 * Each test asserts the 422/redirect-with-errors AND that nothing was written, because a
 * rule that reports an error while the write still lands is the failure that matters.
 */
class CrossTenantDepartmentValidationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: User} */
    private function setupContext(string $role = 'admin'): array
    {
        $plan = Plan::factory()->create(['slug' => 'business', 'features' => PlanFeature::forPlan('business')]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);
        $user = User::factory()->create();
        $tenant->addUser($user, $role);

        $roleService = app(TenantRoleService::class);
        $roleService->setupDefaultRoles($tenant);
        $roleService->syncRole($user, $role, $tenant);

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);

        return [$tenant, $user];
    }

    /** A department owned by a second, unrelated workspace. */
    private function foreignDepartment(): Department
    {
        $other = Tenant::factory()->withLicense()->create();

        return Department::factory()->create(['tenant_id' => $other->id]);
    }

    public function test_creating_a_category_rejects_another_workspaces_department(): void
    {
        [$tenant] = $this->setupContext();
        $foreign = $this->foreignDepartment();

        $this->post($this->tenantUrl('/categories'), [
            'name' => 'Smuggled',
            'department_id' => $foreign->id,
            'color' => '#ff0000',
            'is_active' => true,
        ])->assertSessionHasErrors('department_id');

        $this->assertDatabaseMissing('ticket_categories', ['name' => 'Smuggled']);
    }

    public function test_updating_a_category_rejects_another_workspaces_department(): void
    {
        [$tenant] = $this->setupContext();
        $own = Department::factory()->create(['tenant_id' => $tenant->id]);
        $foreign = $this->foreignDepartment();

        $category = TicketCategory::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $own->id,
        ]);

        $this->put($this->tenantUrl('/categories/'.$category->id), [
            'name' => $category->name,
            'department_id' => $foreign->id,
            'color' => '#ff0000',
            'is_active' => true,
        ])->assertSessionHasErrors('department_id');

        $this->assertSame($own->id, $category->fresh()->department_id);
    }

    public function test_a_category_still_accepts_its_own_workspaces_department(): void
    {
        [$tenant] = $this->setupContext();
        $own = Department::factory()->create(['tenant_id' => $tenant->id]);

        $this->post($this->tenantUrl('/categories'), [
            'name' => 'Legitimate',
            'department_id' => $own->id,
            'color' => '#00ff00',
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ticket_categories', [
            'name' => 'Legitimate',
            'tenant_id' => $tenant->id,
            'department_id' => $own->id,
        ]);
    }

    public function test_creating_a_member_rejects_another_workspaces_department(): void
    {
        [$tenant] = $this->setupContext();
        $foreign = $this->foreignDepartment();

        $this->post($this->tenantUrl('/members'), [
            'name' => 'Smuggled Member',
            'email' => 'smuggled@example.com',
            'password' => 'Password!2345',
            'password_confirmation' => 'Password!2345',
            'role' => 'agent',
            'department_ids' => [$foreign->id],
        ])->assertSessionHasErrors('department_ids.0');

        $this->assertDatabaseMissing('users', ['email' => 'smuggled@example.com']);
    }

    public function test_the_category_lookup_endpoint_rejects_another_workspaces_department(): void
    {
        [$tenant, $user] = $this->setupContext();
        $foreign = $this->foreignDepartment();

        $plain = 'tk_'.str_repeat('a', 32);
        ApiToken::create([
            'tenant_id' => $tenant->id,
            'name' => 'test',
            'token' => hash('sha256', $plain),
            'created_by' => $user->id,
        ]);

        // A bare `exists` rule answers "does department N exist anywhere on the platform",
        // which turns a 422-vs-200 into an existence oracle for other workspaces' ids.
        $this->getJson('/api/v1/categories?department_id='.$foreign->id, [
            'Authorization' => 'Bearer '.$plain,
        ])->assertStatus(422)->assertJsonValidationErrors('department_id');
    }
}
