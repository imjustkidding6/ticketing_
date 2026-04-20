<x-client-portal-layout :tenant="$tenant">
    <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Knowledge Base') }}</h1>
            <p class="mt-2 text-sm text-gray-600">{{ __('Browse our help articles to find answers to your questions.') }}</p>
        </div>

        <!-- Search -->
        <div class="mb-8" x-data="kbSearch()" x-init="init()">
            <div class="relative">
                <input type="text" x-model="query" @input.debounce.400ms="search()" placeholder="{{ __('Search articles...') }}" class="block w-full rounded-lg border-gray-300 pl-10 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </div>
            </div>
            <div x-show="results.length > 0" x-cloak class="mt-2 rounded-lg border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
                <template x-for="article in results" :key="article.id">
                    <a :href="article.url" class="block px-4 py-3 hover:bg-gray-50">
                        <div class="text-sm font-medium text-indigo-600" x-text="article.title"></div>
                        <div class="mt-1 text-xs text-gray-500 line-clamp-2" x-text="article.excerpt"></div>
                    </a>
                </template>
            </div>
        </div>

        <!-- Categories Grid -->
        @if($categories->isEmpty())
            <div class="rounded-xl bg-white p-12 text-center shadow-sm">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                </svg>
                <h3 class="mt-4 text-sm font-medium text-gray-900">{{ __('No articles yet') }}</h3>
                <p class="mt-1 text-sm text-gray-500">{{ __('Check back soon for helpful articles.') }}</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($categories as $category)
                    <a href="{{ route('portal.knowledge-base.category', ['tenant' => $tenant->slug, 'categorySlug' => $category->slug]) }}" class="group rounded-xl bg-white p-6 shadow-sm transition hover:shadow-md">
                        <div class="flex items-center gap-3">
                            @if($category->icon)
                                <span class="text-2xl">{{ $category->icon }}</span>
                            @else
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg" style="background-color: color-mix(in srgb, var(--portal-primary) 15%, white);">
                                    <svg class="h-5 w-5" style="color: var(--portal-primary);" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                                    </svg>
                                </div>
                            @endif
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900 group-hover:text-indigo-600">{{ $category->name }}</h3>
                                <span class="text-xs text-gray-500">{{ $category->articles_count }} {{ Str::plural('article', $category->articles_count) }}</span>
                            </div>
                        </div>
                        @if($category->description)
                            <p class="mt-3 text-sm text-gray-500 line-clamp-2">{{ $category->description }}</p>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <script>
        function kbSearch() {
            return {
                query: '',
                results: [],
                init() {},
                async search() {
                    if (this.query.length < 3) {
                        this.results = [];
                        return;
                    }
                    try {
                        const response = await fetch(`{{ route('portal.knowledge-base.search', ['tenant' => $tenant->slug]) }}?q=${encodeURIComponent(this.query)}`);
                        this.results = await response.json();
                    } catch (e) {
                        this.results = [];
                    }
                }
            };
        }
    </script>
</x-client-portal-layout>
