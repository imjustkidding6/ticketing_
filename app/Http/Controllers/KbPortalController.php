<?php

namespace App\Http\Controllers;

use App\Enums\PlanFeature;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Tenant;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KbPortalController extends Controller
{
    public function __construct(
        private PlanService $planService,
    ) {}

    private function resolveTenant(string $slug): Tenant
    {
        $tenant = Tenant::where('slug', $slug)
            ->where('is_active', true)
            ->whereNull('suspended_at')
            ->firstOrFail();

        if (! $this->planService->tenantHasFeature($tenant, PlanFeature::KnowledgeBase)) {
            abort(404);
        }

        return $tenant;
    }

    public function index(string $slug): View
    {
        $tenant = $this->resolveTenant($slug);

        $categories = KbCategory::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->active()
            ->withCount(['articles' => fn ($q) => $q->where('is_published', true)])
            ->ordered()
            ->get();

        return view('client-portal.knowledge-base.index', compact('tenant', 'categories'));
    }

    public function category(string $slug, string $categorySlug): View
    {
        $tenant = $this->resolveTenant($slug);

        $category = KbCategory::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $categorySlug)
            ->where('is_active', true)
            ->firstOrFail();

        $articles = KbArticle::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('kb_category_id', $category->id)
            ->published()
            ->ordered()
            ->get();

        return view('client-portal.knowledge-base.category', compact('tenant', 'category', 'articles'));
    }

    public function article(string $slug, string $categorySlug, string $articleSlug): View
    {
        $tenant = $this->resolveTenant($slug);

        $category = KbCategory::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $categorySlug)
            ->where('is_active', true)
            ->firstOrFail();

        $article = KbArticle::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('kb_category_id', $category->id)
            ->where('slug', $articleSlug)
            ->where('is_published', true)
            ->firstOrFail();

        $article->increment('views_count');

        $relatedArticles = KbArticle::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('kb_category_id', $category->id)
            ->where('id', '!=', $article->id)
            ->published()
            ->ordered()
            ->limit(5)
            ->get();

        return view('client-portal.knowledge-base.article', compact('tenant', 'category', 'article', 'relatedArticles'));
    }

    public function search(string $slug, Request $request): JsonResponse
    {
        $tenant = Tenant::where('slug', $slug)->where('is_active', true)->firstOrFail();

        if (! $this->planService->tenantHasFeature($tenant, PlanFeature::KnowledgeBase)) {
            return response()->json([]);
        }

        $q = $request->string('q')->trim();

        if ($q->isEmpty() || $q->length() < 3) {
            return response()->json([]);
        }

        $articles = KbArticle::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->published()
            ->where(function ($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                    ->orWhere('excerpt', 'like', "%{$q}%");
            })
            ->with('category')
            ->ordered()
            ->limit(5)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'excerpt' => $a->excerpt,
                'url' => $a->category ? route('portal.knowledge-base.article', [
                    'slug' => $tenant->slug,
                    'categorySlug' => $a->category->slug,
                    'articleSlug' => $a->slug,
                ]) : null,
            ]);

        return response()->json($articles);
    }
}
