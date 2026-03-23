<x-client-portal-layout :tenant="$tenant">
    <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
        <!-- Breadcrumb -->
        <nav class="mb-6 flex items-center gap-2 text-sm text-gray-500">
            <a href="{{ route('portal.knowledge-base.index', ['tenant' => $tenant->slug]) }}" class="hover:text-indigo-600">{{ __('Knowledge Base') }}</a>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
            <span class="text-gray-900">{{ $category->name }}</span>
        </nav>

        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">{{ $category->name }}</h1>
            @if($category->description)
                <p class="mt-2 text-sm text-gray-600">{{ $category->description }}</p>
            @endif
        </div>

        @if($articles->isEmpty())
            <div class="rounded-xl bg-white p-12 text-center shadow-sm">
                <p class="text-sm text-gray-500">{{ __('No articles in this category yet.') }}</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach($articles as $article)
                    <a href="{{ route('portal.knowledge-base.article', ['tenant' => $tenant->slug, 'categorySlug' => $category->slug, 'articleSlug' => $article->slug]) }}" class="block rounded-xl bg-white p-6 shadow-sm transition hover:shadow-md">
                        <h3 class="text-base font-semibold text-gray-900 hover:text-indigo-600">{{ $article->title }}</h3>
                        @if($article->excerpt)
                            <p class="mt-2 text-sm text-gray-500 line-clamp-2">{{ $article->excerpt }}</p>
                        @endif
                        <div class="mt-3 flex items-center gap-4 text-xs text-gray-400">
                            <span>{{ $article->published_at?->format('M d, Y') }}</span>
                            <span>{{ number_format($article->views_count) }} {{ __('views') }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-client-portal-layout>
