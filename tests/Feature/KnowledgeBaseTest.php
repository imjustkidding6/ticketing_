<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantUrlHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeBaseTest extends TestCase
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

        $this->actingAs($user)
            ->withTenant($tenant)
            ->withSession(['current_tenant_id' => $tenant->id]);

        return $user;
    }

    public function test_categories_index_requires_auth(): void
    {
        $tenant = $this->createEnterpriseTenant();

        $this->get(app(TenantUrlHelper::class)->tenantUrl($tenant, '/knowledge-base/categories'))
            ->assertRedirect('/login');
    }

    public function test_categories_index_returns_403_for_starter_plan(): void
    {
        $tenant = $this->createStarterTenant();
        $this->setupTenantContext($tenant);

        $this->get($this->tenantUrl('/knowledge-base/categories'))
            ->assertForbidden();
    }

    public function test_categories_index_works_for_business_plan(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        KbCategory::factory()->create(['tenant_id' => $tenant->id]);

        $this->get($this->tenantUrl('/knowledge-base/categories'))
            ->assertOk()
            ->assertViewIs('knowledge-base.categories.index');
    }

    public function test_can_create_category(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $this->post($this->tenantUrl('/knowledge-base/categories'), [
            'name' => 'Test Category',
            'description' => 'A test description',
            'sort_order' => 1,
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('kb_categories', [
            'tenant_id' => $tenant->id,
            'name' => 'Test Category',
            'slug' => 'test-category',
        ]);
    }

    public function test_can_update_category(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $category = KbCategory::factory()->create(['tenant_id' => $tenant->id]);

        $this->put($this->tenantUrl("/knowledge-base/categories/{$category->id}"), [
            'name' => 'Updated Name',
            'description' => 'Updated desc',
            'sort_order' => 5,
            'is_active' => false,
        ])->assertRedirect();

        $category->refresh();
        $this->assertEquals('Updated Name', $category->name);
        $this->assertFalse($category->is_active);
    }

    public function test_cannot_delete_category_with_articles(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $category = KbCategory::factory()->create(['tenant_id' => $tenant->id]);
        KbArticle::factory()->create([
            'tenant_id' => $tenant->id,
            'kb_category_id' => $category->id,
        ]);

        $this->delete($this->tenantUrl("/knowledge-base/categories/{$category->id}"))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('kb_categories', ['id' => $category->id]);
    }

    public function test_can_delete_empty_category(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $category = KbCategory::factory()->create(['tenant_id' => $tenant->id]);

        $this->delete($this->tenantUrl("/knowledge-base/categories/{$category->id}"))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('kb_categories', ['id' => $category->id]);
    }

    public function test_can_create_article(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $user = $this->setupTenantContext($tenant);

        $category = KbCategory::factory()->create(['tenant_id' => $tenant->id]);

        $this->post($this->tenantUrl('/knowledge-base/articles'), [
            'kb_category_id' => $category->id,
            'title' => 'How to Reset Password',
            'content' => 'Go to settings and click reset.',
            'excerpt' => 'Password reset guide',
            'sort_order' => 0,
            'is_published' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('kb_articles', [
            'tenant_id' => $tenant->id,
            'title' => 'How to Reset Password',
            'created_by' => $user->id,
            'is_published' => true,
        ]);

        $article = KbArticle::where('title', 'How to Reset Password')->first();
        $this->assertNotNull($article->published_at);
    }

    public function test_publishing_sets_published_at(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $category = KbCategory::factory()->create(['tenant_id' => $tenant->id]);
        $article = KbArticle::factory()->unpublished()->create([
            'tenant_id' => $tenant->id,
            'kb_category_id' => $category->id,
        ]);

        $this->put($this->tenantUrl("/knowledge-base/articles/{$article->id}"), [
            'kb_category_id' => $category->id,
            'title' => $article->title,
            'content' => $article->content,
            'is_published' => true,
        ])->assertRedirect();

        $article->refresh();
        $this->assertTrue($article->is_published);
        $this->assertNotNull($article->published_at);
    }

    public function test_search_returns_published_articles(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $category = KbCategory::factory()->create(['tenant_id' => $tenant->id]);

        KbArticle::factory()->published()->create([
            'tenant_id' => $tenant->id,
            'kb_category_id' => $category->id,
            'title' => 'Password Reset Guide',
            'excerpt' => 'How to reset',
        ]);

        KbArticle::factory()->unpublished()->create([
            'tenant_id' => $tenant->id,
            'kb_category_id' => $category->id,
            'title' => 'Password Draft',
        ]);

        $this->getJson($this->tenantUrl('/knowledge-base/search?q=Password'))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['title' => 'Password Reset Guide']);
    }

    public function test_search_returns_empty_for_short_query(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $this->getJson($this->tenantUrl('/knowledge-base/search?q=ab'))
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_article_show_increments_views(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $category = KbCategory::factory()->create(['tenant_id' => $tenant->id]);
        $article = KbArticle::factory()->published()->create([
            'tenant_id' => $tenant->id,
            'kb_category_id' => $category->id,
            'views_count' => 5,
        ]);

        $this->get($this->tenantUrl("/knowledge-base/articles/{$article->id}"))
            ->assertOk();

        $article->refresh();
        $this->assertEquals(6, $article->views_count);
    }
}
