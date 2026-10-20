<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\Department;
use App\Models\License;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryProductTest extends TestCase
{
    use RefreshDatabase;

    private function setupContext(): array
    {
        $plan = Plan::factory()->create(['slug' => 'business', 'features' => PlanFeature::forPlan('business')]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);
        $user = User::factory()->create();
        $tenant->addUser($user, 'admin');

        $roleService = app(TenantRoleService::class);
        $roleService->setupDefaultRoles($tenant);
        $roleService->syncRole($user, 'admin', $tenant);

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);

        return [$tenant, $user];
    }

    public function test_list_categories(): void
    {
        [$tenant] = $this->setupContext();

        $this->get($this->tenantUrl('/categories'))->assertOk();
    }

    public function test_create_category(): void
    {
        [$tenant] = $this->setupContext();
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);

        $this->post($this->tenantUrl('/categories'), [
            'name' => 'Test Category',
            'department_id' => $dept->id,
            'color' => '#ff0000',
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('ticket_categories', ['name' => 'Test Category', 'tenant_id' => $tenant->id]);
    }

    public function test_update_category(): void
    {
        [$tenant] = $this->setupContext();
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
        $cat = TicketCategory::factory()->create(['tenant_id' => $tenant->id]);

        $this->put($this->tenantUrl("/categories/{$cat->id}"), [
            'name' => 'Updated Category',
            'department_id' => $dept->id,
            'color' => '#00ff00',
            'is_active' => true,
        ])->assertRedirect();

        $cat->refresh();
        $this->assertEquals('Updated Category', $cat->name);
    }

    public function test_delete_category(): void
    {
        [$tenant] = $this->setupContext();
        $cat = TicketCategory::factory()->create(['tenant_id' => $tenant->id]);

        $this->delete($this->tenantUrl("/categories/{$cat->id}"))->assertRedirect();

        $this->assertDatabaseMissing('ticket_categories', ['id' => $cat->id]);
    }

    public function test_list_products(): void
    {
        [$tenant] = $this->setupContext();

        $this->get($this->tenantUrl('/products'))->assertOk();
    }

    public function test_create_product(): void
    {
        [$tenant] = $this->setupContext();

        $this->post($this->tenantUrl('/products'), [
            'name' => 'Test Product',
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('products', ['name' => 'Test Product', 'tenant_id' => $tenant->id]);
    }

    public function test_update_product(): void
    {
        [$tenant] = $this->setupContext();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $this->put($this->tenantUrl("/products/{$product->id}"), [
            'name' => 'Updated Product',
            'is_active' => true,
        ])->assertRedirect();

        $product->refresh();
        $this->assertEquals('Updated Product', $product->name);
    }

    public function test_delete_product(): void
    {
        [$tenant] = $this->setupContext();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $this->delete($this->tenantUrl("/products/{$product->id}"))->assertRedirect();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_public_categories_api(): void
    {
        [$tenant] = $this->setupContext();
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
        TicketCategory::factory()->create(['tenant_id' => $tenant->id, 'department_id' => $dept->id]);

        $this->get($this->tenantUrl("/api/categories?department_id={$dept->id}"))->assertOk()->assertJsonCount(1);
    }

    public function test_public_products_api(): void
    {
        [$tenant] = $this->setupContext();
        $cat = TicketCategory::factory()->create(['tenant_id' => $tenant->id]);
        Product::factory()->create(['tenant_id' => $tenant->id, 'category_id' => $cat->id]);

        $this->get($this->tenantUrl("/api/products?category_id={$cat->id}"))->assertOk()->assertJsonCount(1);
    }

    /**
     * P10: the two detail-field gates live on the CATEGORY, not the department. The toggle
     * rides on `update categories`, already enforced by the controller.
     */
    public function test_a_category_can_be_marked_as_covering_technical_work(): void
    {
        [$tenant] = $this->setupContext();
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
        $cat = TicketCategory::factory()->create(['tenant_id' => $tenant->id, 'department_id' => $dept->id]);

        // Default off: the safe reading of an unanswered question is "not technical", which
        // asks for fewer fields rather than fabricating a requirement.
        $this->assertFalse($cat->refresh()->is_technical);

        $this->get($this->tenantUrl("/categories/{$cat->id}/edit"))
            ->assertOk()
            ->assertSee('Covers technical work');

        $this->put($this->tenantUrl("/categories/{$cat->id}"), [
            'name' => $cat->name,
            'department_id' => $dept->id,
            'color' => '#3b82f6',
            'is_active' => true,
            'is_technical' => true,
        ])->assertRedirect();

        $this->assertTrue($cat->refresh()->is_technical);

        // And off again — the checkbox posts its own hidden 0, so unticking persists.
        $this->put($this->tenantUrl("/categories/{$cat->id}"), [
            'name' => $cat->name,
            'department_id' => $dept->id,
            'color' => '#3b82f6',
            'is_active' => true,
            'is_technical' => false,
        ])->assertRedirect();

        $this->assertFalse($cat->refresh()->is_technical);
    }

    public function test_a_category_can_opt_out_of_the_impact_questions_and_the_form_states_the_cost(): void
    {
        [$tenant] = $this->setupContext();
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
        $cat = TicketCategory::factory()->create(['tenant_id' => $tenant->id, 'department_id' => $dept->id]);

        // Default ON, the opposite of is_technical: a new category keeps asking the two
        // questions the priority rubric needs, and the workspace opts OUT.
        $this->assertTrue($cat->refresh()->asks_impact);

        // The checkbox must say what switching it off COSTS, not just what it does — and
        // the form must also say what a ticket with NO category gets, which is nothing.
        $this->get($this->tenantUrl("/categories/{$cat->id}/edit"))
            ->assertOk()
            ->assertSee('Ask who is affected and whether there is a workaround')
            ->assertSee('can no longer be judged critical', false)
            ->assertSee('no category at all is asked neither', false);

        $this->put($this->tenantUrl("/categories/{$cat->id}"), [
            'name' => $cat->name,
            'department_id' => $dept->id,
            'color' => '#3b82f6',
            'is_active' => true,
            'asks_impact' => false,
        ])->assertRedirect();

        $this->assertFalse($cat->refresh()->asks_impact);

        // And back on — the hidden 0 companion is what makes unticking reach the controller
        // at all, so both directions are pinned.
        $this->put($this->tenantUrl("/categories/{$cat->id}"), [
            'name' => $cat->name,
            'department_id' => $dept->id,
            'color' => '#3b82f6',
            'is_active' => true,
            'asks_impact' => true,
        ])->assertRedirect();

        $this->assertTrue($cat->refresh()->asks_impact);
    }

    public function test_a_category_created_through_the_form_asks_the_impact_questions_by_default(): void
    {
        [$tenant] = $this->setupContext();
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);

        $this->get($this->tenantUrl('/categories/create'))
            ->assertOk()
            ->assertSee('Ask who is affected and whether there is a workaround');

        // No key posted at all (an older client, or a form without the field): the column
        // default decides, and it decides in favour of asking.
        $this->post($this->tenantUrl('/categories'), [
            'name' => 'Access requests',
            'department_id' => $dept->id,
            'color' => '#3b82f6',
            'is_active' => true,
        ])->assertRedirect();

        $created = TicketCategory::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('name', 'Access requests')
            ->sole();

        $this->assertTrue($created->asks_impact);
        $this->assertFalse($created->is_technical);
    }
}
