<x-client-portal-layout :tenant="$tenant">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        <!-- Breadcrumb -->
        <nav class="mb-6 flex items-center gap-2 text-sm text-gray-500">
            <a href="{{ route('portal.knowledge-base.index', ['tenant' => $tenant->slug]) }}" class="hover:text-indigo-600">{{ __('Knowledge Base') }}</a>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
            <a href="{{ route('portal.knowledge-base.category', ['tenant' => $tenant->slug, 'categorySlug' => $category->slug]) }}" class="hover:text-indigo-600">{{ $category->name }}</a>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
            <span class="text-gray-900 truncate max-w-xs">{{ $article->title }}</span>
        </nav>

        <!-- Article -->
        <article class="rounded-xl bg-white p-8 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">{{ $article->title }}</h1>

            <div class="mt-3 flex items-center gap-4 text-sm text-gray-500">
                @if($article->published_at)
                    <span>{{ $article->published_at->format('M d, Y') }}</span>
                @endif
                <span>{{ number_format($article->views_count) }} {{ __('views') }}</span>
            </div>

            @if($article->excerpt)
                <div class="mt-6 rounded-lg bg-gray-50 p-4 text-sm text-gray-600 italic">
                    {{ $article->excerpt }}
                </div>
            @endif

            <div class="mt-6 prose prose-sm max-w-none text-gray-700">
                {!! nl2br(e($article->content)) !!}
            </div>
        </article>

        <!-- Related Articles -->
        @if($relatedArticles->isNotEmpty())
            <div class="mt-8">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Related Articles') }}</h2>
                <div class="space-y-3">
                    @foreach($relatedArticles as $related)
                        <a href="{{ route('portal.knowledge-base.article', ['tenant' => $tenant->slug, 'categorySlug' => $category->slug, 'articleSlug' => $related->slug]) }}" class="block rounded-lg bg-white p-4 shadow-sm transition hover:shadow-md">
                            <h3 class="text-sm font-medium text-indigo-600">{{ $related->title }}</h3>
                            @if($related->excerpt)
                                <p class="mt-1 text-xs text-gray-500 line-clamp-1">{{ $related->excerpt }}</p>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-client-portal-layout>
