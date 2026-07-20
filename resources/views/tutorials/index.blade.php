<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">{{ __('Help & Tutorials') }}</h2>
    </x-slot>

    <x-slot name="headerActions">
        <a href="{{ route('tutorials.download') }}"
           class="rounded-full p-2 text-gray-400 hover:text-gray-600 focus:outline-none dark:text-gray-400 dark:hover:text-gray-200"
           title="{{ __('Download as PDF') }}">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 12m0 0l4.5-4.5M12 12V3" />
            </svg>
        </a>
    </x-slot>

    <div class="py-6" x-data="tutorialSearch()" x-init="filter(); initSpy()">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">{{ __('Everything you need to get the most out of CliqueHA Nexus, on one page. Use the search box to jump straight to what you need, or browse the guides in the sidebar.') }}</p>

            {{-- Search bar (sticky) --}}
            <div class="sticky top-16 z-20 -mx-4 mb-6 border-b border-gray-200 bg-gray-50/95 px-4 py-3 backdrop-blur dark:border-gray-700 dark:bg-gray-900/95 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                    <input type="search"
                           x-model="query"
                           @input="filter()"
                           placeholder="{{ __('Search tutorials — e.g. SLA, escalation, public portal...') }}"
                           class="block w-full rounded-lg border-gray-300 py-2.5 pl-10 pr-10 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:placeholder-gray-500 dark:focus:border-indigo-400"
                           aria-label="{{ __('Search tutorials') }}">
                    <button type="button" x-show="query" x-cloak @click="query = ''; filter()" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300" aria-label="{{ __('Clear search') }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <p x-show="query" x-cloak class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    <span x-text="visibleCount"></span> {{ __('of') }} {{ count($tutorials) }} {{ __('guides match') }} “<span x-text="query" class="font-medium text-gray-700 dark:text-gray-200"></span>”
                </p>
            </div>

            <div class="flex gap-8">
                {{-- Sticky side nav --}}
                <nav class="hidden w-56 shrink-0 lg:block">
                    <div class="sticky top-36 space-y-1">
                        <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('Guides') }}</p>
                        @foreach($tutorials as $slug => $tutorial)
                            <a href="#tut-{{ $slug }}"
                               data-tut-nav="tut-{{ $slug }}"
                               @click="activeSection = 'tut-{{ $slug }}'"
                               :class="activeSection === 'tut-{{ $slug }}' ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100'"
                               class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded text-indigo-600 dark:text-indigo-400">
                                    @include('tutorials.partials._icon', ['icon' => $tutorial['icon']])
                                </span>
                                <span>{{ __($tutorial['title']) }}</span>
                            </a>
                        @endforeach
                    </div>
                </nav>

                {{-- All tutorials, stacked & scrollable --}}
                <div class="min-w-0 flex-1 space-y-6">
                    @foreach($tutorials as $slug => $tutorial)
                        <section id="tut-{{ $slug }}" data-tut-section class="scroll-mt-36 rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800 sm:p-8">
                            @include('tutorials.partials.' . $slug)
                        </section>
                    @endforeach

                    {{-- No results --}}
                    <div x-show="visibleCount === 0" x-cloak class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-800">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('No tutorials match your search.') }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Try a different keyword, or') }} <button type="button" @click="query = ''; filter()" class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">{{ __('clear the search') }}</button>.</p>
                    </div>
                </div>
            </div>

            @if($onboardingDismissed)
                <div class="mt-8 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800" x-data="{ resetting: false }">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Onboarding Checklist') }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Show the setup checklist on your dashboard again.') }}</p>
                        </div>
                        <button type="button"
                                @click="resetting = true; fetch('{{ route('onboarding.reset') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } }).then(() => window.location.href = '{{ route('dashboard') }}')"
                                :disabled="resetting"
                                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                            <span x-show="!resetting">{{ __('Reset & Show') }}</span>
                            <span x-show="resetting" x-cloak>{{ __('Redirecting...') }}</span>
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        function tutorialSearch() {
            return {
                query: '',
                visibleCount: {{ count($tutorials) }},
                activeSection: 'tut-{{ array_key_first($tutorials) }}',
                filter() {
                    const q = this.query.trim().toLowerCase();
                    let visible = 0;
                    this.$root.querySelectorAll('[data-tut-section]').forEach((section) => {
                        const match = !q || section.textContent.toLowerCase().includes(q);
                        section.style.display = match ? '' : 'none';
                        const nav = this.$root.querySelector('[data-tut-nav="' + section.id + '"]');
                        if (nav) nav.style.display = match ? '' : 'none';
                        if (match) visible++;
                    });
                    this.visibleCount = visible;
                },
                // Scrollspy: highlight the guide whose section is currently in view.
                initSpy() {
                    const sections = Array.from(this.$root.querySelectorAll('[data-tut-section]'));
                    const inView = new Set();
                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach((entry) => {
                            if (entry.isIntersecting) {
                                inView.add(entry.target.id);
                            } else {
                                inView.delete(entry.target.id);
                            }
                        });
                        // Highlight the topmost section that is currently in the band.
                        const topmost = sections.find((section) => inView.has(section.id));
                        if (topmost) {
                            this.activeSection = topmost.id;
                        }
                    }, { rootMargin: '-140px 0px -55% 0px', threshold: 0 });
                    sections.forEach((section) => observer.observe(section));
                },
            };
        }
    </script>
    @endpush
</x-app-layout>