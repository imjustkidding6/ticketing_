{{--
    Guided tutorials.

    The written guide and the tours are the same content model seen two ways, so
    a topic and its tour can never disagree. The list is fetched rather than
    rendered inline because the permission check belongs on the server — a user
    is only offered a tour they can actually complete.
--}}
<section
    x-data="guidedTourCards()"
    x-init="load()"
    x-show="visible.length > 0"
    x-cloak
    class="mb-8 rounded-2xl border border-[var(--border-soft)] bg-[var(--bg-card)] p-6 shadow-[var(--shadow-soft)]"
>
    <div class="mb-1 flex items-center gap-2">
        <svg class="h-5 w-5 text-[#5B5FF6]" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
        </svg>
        <h2 class="text-lg font-bold text-[var(--text-primary)]">{{ __('Guided tutorials') }}</h2>
    </div>
    <p class="mb-5 text-sm text-[var(--text-secondary)]">
        {{ __('These walk you through the real screens: the page dims, the next thing to click is spotlighted, and a pointer shows you where to go. You can start one any time — stopping halfway changes nothing.') }}
    </p>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <template x-for="tour in visible" :key="tour.key">
            <div class="flex flex-col rounded-xl border border-[var(--border-soft)] bg-[var(--bg-app)] p-4">
                <div class="mb-2 flex items-start justify-between gap-3">
                    <h3 class="text-sm font-bold leading-snug text-[var(--text-primary)]" x-text="tour.title"></h3>
                    {{-- An honest estimate of the tour, before the user commits. --}}
                    <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-[#EEF0FF] px-2.5 py-1 text-xs font-semibold text-[#4B4FD6]">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span x-text="`~${tour.minutes} min`"></span>
                    </span>
                </div>

                <p class="mb-3 text-xs text-[var(--text-secondary)]" x-text="tour.summary"></p>

                <p class="mb-4 text-xs text-[var(--text-secondary)]">
                    <span x-text="`${tour.steps} steps`"></span>
                    <template x-if="tour.category">
                        <span><span class="mx-1">·</span><span x-text="tour.category"></span></span>
                    </template>
                </p>

                <button
                    type="button"
                    @click="start(tour)"
                    class="mt-auto inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#5B5FF6] px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-[#4B4FD6]"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M8 5v14l11-7z" />
                    </svg>
                    {{ __('Start tutorial') }}
                </button>
            </div>
        </template>
    </div>
</section>

@once
@push('scripts')
<script>
    function guidedTourCards() {
        return {
            tours: [],
            visible: [],
            async load() {
                try {
                    const res = await fetch(@js(route('tours.index')), { headers: { Accept: 'application/json' } });
                    if (res.ok) this.tours = (await res.json()).tours ?? [];
                } catch {
                    /* the section stays hidden */
                }
                this.visible = this.tours;

                // The search filters this region too — a tutorial card left
                // standing under a query that hides its topic contradicts the
                // rest of the page.
                window.addEventListener('help-search', (e) => {
                    const q = (e.detail || '').toLowerCase().trim();
                    this.visible = !q
                        ? this.tours
                        : this.tours.filter((t) => `${t.title} ${t.summary} ${t.category}`.toLowerCase().includes(q));
                });
            },
            start(tour) {
                // No step lives on the help page, so the runner saves progress
                // and then offers the way to the first step's screen. Going
                // straight there is the same thing with one less click.
                if (tour.start_url) {
                    sessionStorage.setItem(
                        'guided-tour-progress',
                        JSON.stringify({ key: tour.key, index: 0 }),
                    );
                    window.location.href = tour.start_url;
                    return;
                }

                window.GuidedTour?.start(tour.key);
            },
        };
    }
</script>
@endpush
@endonce
