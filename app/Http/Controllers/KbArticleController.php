<?php

namespace App\Http\Controllers;

use App\Models\KbArticle;
use App\Models\KbCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class KbArticleController extends Controller
{
    public function index(Request $request): View
    {
        $categories = KbCategory::query()->active()->ordered()->get();

        $articles = KbArticle::query()
            ->with('category')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%");
                });
            })
            ->when($request->category_id, fn ($q, $cat) => $q->where('kb_category_id', $cat))
            ->when($request->published === '1', fn ($q) => $q->published())
            ->when($request->published === '0', fn ($q) => $q->where('is_published', false))
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('knowledge-base.articles.index', compact('articles', 'categories'));
    }

    public function create(): View
    {
        $categories = KbCategory::query()->active()->ordered()->get();

        return view('knowledge-base.articles.create', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kb_category_id' => ['required', 'exists:kb_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $validated['created_by'] = Auth::id();
        $validated['is_published'] = $request->boolean('is_published');

        if ($validated['is_published']) {
            $validated['published_at'] = now();
        }

        KbArticle::create($validated);

        return redirect()->route('knowledge-base.articles.index')
            ->with('success', 'Article created successfully.');
    }

    public function show(KbArticle $article): View
    {
        $article->load('category', 'author');
        $article->increment('views_count');

        return view('knowledge-base.articles.show', compact('article'));
    }

    public function edit(KbArticle $article): View
    {
        $categories = KbCategory::query()->active()->ordered()->get();

        return view('knowledge-base.articles.edit', compact('article', 'categories'));
    }

    public function update(Request $request, KbArticle $article): RedirectResponse
    {
        $validated = $request->validate([
            'kb_category_id' => ['required', 'exists:kb_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $validated['is_published'] = $request->boolean('is_published');

        if ($validated['is_published'] && ! $article->published_at) {
            $validated['published_at'] = now();
        } elseif (! $validated['is_published']) {
            $validated['published_at'] = null;
        }

        $article->update($validated);

        return redirect()->route('knowledge-base.articles.index')
            ->with('success', 'Article updated successfully.');
    }

    public function destroy(KbArticle $article): RedirectResponse
    {
        $article->delete();

        return redirect()->route('knowledge-base.articles.index')
            ->with('success', 'Article deleted successfully.');
    }

    /**
     * JSON search endpoint for article suggestions during ticket creation.
     */
    public function search(Request $request): JsonResponse
    {
        $q = $request->string('q')->trim();

        if ($q->isEmpty() || $q->length() < 3) {
            return response()->json([]);
        }

        $articles = KbArticle::query()
            ->published()
            ->where(function ($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                    ->orWhere('excerpt', 'like', "%{$q}%");
            })
            ->ordered()
            ->limit(5)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'excerpt' => $a->excerpt,
                'url' => route('knowledge-base.articles.show', $a),
            ]);

        return response()->json($articles);
    }
}
