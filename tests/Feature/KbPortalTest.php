<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KbPortalTest extends TestCase
{
    use RefreshDatabase;

    private function createBusinessTenant(): Tenant
    {
        $plan = Plan::factory()->create([
            'slug' => 'business',
            'features' => PlanFeature::forPlan('business'),
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

    public function test_portal_kb_returns_404_for_starter_tenant(): void
    {
        $tenant = $this->createStarterTenant();

        $this->get(route('portal.knowledge-base.index', ['slug' => $tenant->slug]))
            ->assertNotFound();
    }

    public function test_portal_kb_returns_404_for_suspended_tenant(): void
    {
        $tenant = $this->createBusinessTenant();
        $tenant->update(['suspended_at' => now()]);

        $this->get(route('portal.knowledge-base.index', ['slug' => $tenant->slug]))
            ->assertNotFound();
    }

    public function test_portal_kb_index_shows_categories(): void
    {
        $tenant = $this->createBusinessTenant();

        $category = KbCategory::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Getting Started',
        ]);

        KbArticle::factory()->published()->create([
            'tenant_id' => $tenant->id,
            'kb_category_id' => $category->id,
        ]);

        $this->get(route('portal.knowledge-base.index', ['slug' => $tenant->slug]))
            ->assertOk()
            ->assertSee('Getting Started');
    }

    public function test_portal_category_shows_published_articles(): void
    {
        $tenant = $this->createBusinessTenant();

        $category = KbCategory::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'FAQ',
        ]);

        KbArticle::factory()->published()->create([
            'tenant_id' => $tenant->id,
            'kb_category_id' => $category->id,
            'title' => 'Published Article',
        ]);

        KbArticle::factory()->unpublished()->create([
            'tenant_id' => $tenant->id,
            'kb_category_id' => $category->id,
            'title' => 'Draft Article',
        ]);

        $this->get(route('portal.knowledge-base.category', [
            'slug' => $tenant->slug,
            'categorySlug' => $category->slug,
        ]))
            ->assertOk()
            ->assertSee('Published Article')
            ->assertDontSee('Draft Article');
    }

    public function test_portal_article_increments_views(): void
    {
        $tenant = $this->createBusinessTenant();

        $category = KbCategory::factory()->create(['tenant_id' => $tenant->id]);
        $article = KbArticle::factory()->published()->create([
            'tenant_id' => $tenant->id,
            'kb_category_id' => $category->id,
            'views_count' => 10,
        ]);

        $this->get(route('portal.knowledge-base.article', [
            'slug' => $tenant->slug,
            'categorySlug' => $category->slug,
            'articleSlug' => $article->slug,
        ]))
            ->assertOk();

        $article->refresh();
        $this->assertEquals(11, $article->views_count);
    }

    public function test_portal_article_returns_404_for_unpublished(): void
    {
        $tenant = $this->createBusinessTenant();

        $category = KbCategory::factory()->create(['tenant_id' => $tenant->id]);
        $article = KbArticle::factory()->unpublished()->create([
            'tenant_id' => $tenant->id,
            'kb_category_id' => $category->id,
        ]);

        $this->get(route('portal.knowledge-base.article', [
            'slug' => $tenant->slug,
            'categorySlug' => $category->slug,
            'articleSlug' => $article->slug,
        ]))
            ->assertNotFound();
    }

    public function test_portal_search_returns_matching_articles(): void
    {
        $tenant = $this->createBusinessTenant();

        $category = KbCategory::factory()->create(['tenant_id' => $tenant->id]);
        KbArticle::factory()->published()->create([
            'tenant_id' => $tenant->id,
            'kb_category_id' => $category->id,
            'title' => 'Password Reset Guide',
        ]);

        $this->getJson(route('portal.knowledge-base.search', [
            'slug' => $tenant->slug,
            'q' => 'Password',
        ]))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['title' => 'Password Reset Guide']);
    }

    public function test_portal_search_respects_tenant_scoping(): void
    {
        $plan = Plan::factory()->create([
            'slug' => 'business',
            'features' => PlanFeature::forPlan('business'),
        ]);
        $license1 = License::factory()->active()->forPlan($plan)->create();
        $license2 = License::factory()->active()->forPlan($plan)->create();
        $tenant1 = Tenant::factory()->create(['license_id' => $license1->id]);
        $tenant2 = Tenant::factory()->create(['license_id' => $license2->id]);

        $category1 = KbCategory::factory()->create(['tenant_id' => $tenant1->id]);
        $category2 = KbCategory::factory()->create(['tenant_id' => $tenant2->id]);

        KbArticle::factory()->published()->create([
            'tenant_id' => $tenant1->id,
            'kb_category_id' => $category1->id,
            'title' => 'Tenant One Article',
        ]);

        KbArticle::factory()->published()->create([
            'tenant_id' => $tenant2->id,
            'kb_category_id' => $category2->id,
            'title' => 'Tenant Two Article',
        ]);

        $this->getJson(route('portal.knowledge-base.search', [
            'slug' => $tenant1->slug,
            'q' => 'Tenant',
        ]))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['title' => 'Tenant One Article']);
    }
}
