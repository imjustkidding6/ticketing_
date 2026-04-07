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
        $cat = TicketCategory::factory()->create(['tenant_id' => $tenant->id]);

        $this->put($this->tenantUrl("/categories/{$cat->id}"), [
            'name' => 'Updated Category',
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
}
