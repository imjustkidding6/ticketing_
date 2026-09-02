{{--
    Guided tour launcher.

    Lives in the header rather than as a floating bubble: the bottom corners are
    already taken (the sidebar's user panel on the left, the AI assistant and
    feedback widgets on the right), and a launcher buried under one of them is
    a feature nobody can reach.

    Always available, from any page, as often as the user likes — there is no
    "you have already seen this" state to reset. The list is fetched rather than
    rendered inline so the permission check stays on the server.
--}}
<div x-data="tourLauncher()" x-init="init()" class="relative print:hidden" x-show="tours.length > 0" x-cloak>
    <button
        type="button"
        @click="open = !open"
        class="relative rounded-full p-2 text-gray-400 hover:text-gray-600 focus:outline-none dark:hover:text-gray-200"
        :aria-expanded="open"
        aria-haspopup="true"
        :title="'{{ __('Guided tours') }}'"
    >
        <span class="sr-only">{{ __('Guided tours') }}</span>
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
        </svg>
    </button>

    <div
        x-show="open"
        x-cloak
        @click.outside="open = false"
        @keydown.escape.window="open = false"
        x-transition
        class="absolute right-0 z-50 mt-2 w-80 rounded-xl border border-gray-200 bg-white p-2 shadow-xl dark:border-gray-700 dark:bg-gray-800"
    >
        <p class="px-2 pb-1 pt-1.5 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Show me how') }}</p>

        <template x-for="tour in tours" :key="tour.key">
            <button
                type="button"
                @click="start(tour.key)"
                class="block w-full rounded-md px-2 py-2 text-left hover:bg-gray-50 dark:hover:bg-gray-700"
            >
                <span class="block text-sm font-medium text-gray-900 dark:text-gray-100" x-text="tour.title"></span>
                <span class="block text-xs text-gray-500 dark:text-gray-400" x-text="tour.summary"></span>
                {{-- An honest estimate of the tour, shown before the user commits. --}}
                <span class="mt-0.5 block text-[11px] text-gray-400" x-text="`${tour.minutes} min · ${tour.steps} steps`"></span>
            </button>
        </template>
    </div>
</div>

@once
@push('scripts')
<script>
    function tourLauncher() {
        return {
            open: false,
            tours: [],
            async init() {
                try {
                    const res = await fetch(@js(route('tours.index')), { headers: { Accept: 'application/json' } });
                    if (res.ok) this.tours = (await res.json()).tours ?? [];
                } catch {
                    /* the launcher simply does not appear */
                }
            },
            start(key) {
                this.open = false;
                window.GuidedTour?.start(key);
            },
        };
    }
</script>
@endpush
@endonce
