{{--
    The guided tours belonging to this written topic.

    A topic and its tour are the same subject taught two ways, so the tour is
    offered where the reader already is rather than only from the index.
--}}
<div
    x-data="topicTours(@js($slug))"
    x-init="load()"
    x-show="tours.length > 0"
    x-cloak
    class="flex flex-wrap gap-2"
>
    <template x-for="tour in tours" :key="tour.key">
        <button
            type="button"
            @click="start(tour)"
            class="inline-flex items-center gap-2 rounded-lg bg-[#5B5FF6] px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-[#4B4FD6]"
            :title="`${tour.title} · ~${tour.minutes} min`"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
            {{ __('Start tutorial') }}
        </button>
    </template>
</div>

@once
@push('scripts')
<script>
    function topicTours(topic) {
        return {
            tours: [],
            async load() {
                try {
                    const res = await fetch(@js(route('tours.index')), { headers: { Accept: 'application/json' } });
                    if (!res.ok) return;
                    const all = (await res.json()).tours ?? [];
                    this.tours = all.filter((t) => t.topic === topic);
                } catch {
                    /* no button, rather than a broken one */
                }
            },
            start(tour) {
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
