<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Help & Tutorial') }}</h2>
    </x-slot>

    <style>
        :root {
            --bg-app: #F7F8FC;
            --bg-card: #FFFFFF;
            --bg-hover: #F1F2F9;
            --text-primary: #1A1D2E;
            --text-secondary: #5A6079;
            --border-soft: #E6E8F0;
            --shadow-soft: 0 1px 2px rgba(26,29,46,.05), 0 8px 24px -18px rgba(26,29,46,.35);
        }
        .dark {
            --bg-app: #111827; --bg-card: #1F2937; --bg-hover: #374151;
            --text-primary: #F3F4F6; --text-secondary: #9CA3AF; --border-soft: #374151;
        }
        [x-cloak] { display: none !important; }
        [data-help-topic] { scroll-margin-top: 90px; }
    </style>

    <div class="py-8" style="background: var(--bg-app);"
         x-data="helpCentre(@js($searchText), @js($hiddenTopics))">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            {{-- Hero --}}
            <div class="mb-6 rounded-2xl bg-gradient-to-r from-[#4F46E5] to-[#6D28D9] p-8 text-white shadow-lg">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-extrabold sm:text-3xl">
                            {{ $tenant?->name ?? config('app.name') }} — {{ __('Help Center') }}
                        </h1>
                        <p class="mt-1 text-sm text-indigo-100">
                            {{ __('Step-by-step guides for the parts of the system') }}
                            <strong>{{ __('your role can use') }}</strong>.
                            {{ __('About') }} <strong>{{ $totalMinutes }} {{ __('minutes') }}</strong> {{ __('of reading in total') }}.
                        </p>
                    </div>
                    <a href="{{ route('tutorials.download') }}"
                       class="inline-flex items-center gap-2 rounded-lg bg-white/95 px-4 py-2.5 text-sm font-semibold text-[#4338CA] shadow-sm hover:bg-white">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                        {{ __('Download my guide (PDF)') }}
                    </a>
                </div>

                <div class="relative mt-6">
                    <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                    <input type="search" x-model="query" @input="run()"
                           class="w-full rounded-xl border-0 py-3.5 pl-12 pr-4 text-sm text-gray-900 shadow-sm focus:ring-2 focus:ring-white"
                           placeholder="{{ __('Search topics, steps and tutorials (e.g. SLA, merge, timezone)…') }}">
                </div>
            </div>

            {{-- Guided tutorials --}}
            @include('tutorials.partials._guided-tours')

            {{-- The honest empty state: a legitimate search returning nothing
                 reads as a broken search box unless it says why. --}}
            <div x-show="deadEnd" x-cloak class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-5">
                <p class="text-sm font-bold text-amber-900" x-text="deadEnd.heading"></p>
                <p class="mt-1 text-sm text-amber-800" x-text="deadEnd.detail"></p>
            </div>

            <div x-show="noResults" x-cloak class="mb-6 rounded-xl border border-[var(--border-soft)] bg-[var(--bg-card)] p-5">
                <p class="text-sm text-[var(--text-secondary)]">
                    {{ __('Nothing in your guide matches that. Try a different word, or clear the search.') }}
                </p>
            </div>

            <div class="flex flex-col gap-8 lg:flex-row">

                {{-- Contents --}}
                <aside class="lg:w-[24%] shrink-0">
                    <div class="sticky top-24 rounded-2xl border border-[var(--border-soft)] bg-[var(--bg-card)] p-4 shadow-[var(--shadow-soft)]">
                        <p class="px-2 pb-2 text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">{{ __('Contents') }}</p>
                        @foreach($groups as $groupKey => $group)
                            <div data-help-group="{{ $groupKey }}" class="mb-3">
                                <p class="px-2 pb-1 text-[11px] font-bold uppercase tracking-wider text-[var(--text-secondary)]">{{ $group['label'] }}</p>
                                @foreach($group['topics'] as $key => $topic)
                                    <a href="#topic-{{ $key }}" data-help-nav="{{ $key }}"
                                       class="flex items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-sm font-medium text-[var(--text-primary)] hover:bg-[var(--bg-hover)]">
                                        <span>{{ $topic['title'] }}</span>
                                        <span class="shrink-0 text-[11px] text-[var(--text-secondary)]">{{ $topic['minutes'] }}m</span>
                                    </a>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </aside>

                {{-- Topics --}}
                <div class="flex-1 space-y-6">
                    @foreach($topics as $key => $topic)
                        <article id="topic-{{ $key }}" data-help-topic="{{ $key }}"
                                 class="rounded-2xl border border-[var(--border-soft)] bg-[var(--bg-card)] p-6 shadow-[var(--shadow-soft)] sm:p-8">
                            <div class="mb-4 flex flex-wrap items-start justify-between gap-3 border-b border-[var(--border-soft)] pb-4">
                                <div>
                                    <h2 class="flex items-center gap-2 text-xl font-extrabold text-[var(--text-primary)]">
                                        <span class="flex h-6 w-6 items-center justify-center text-[#5B5FF6]">
                                            @include('tutorials.partials._icon', ['icon' => $topic['icon']])
                                        </span>
                                        {{ $topic['title'] }}
                                    </h2>
                                    <p class="mt-1 text-sm text-[var(--text-secondary)]">{{ $topic['summary'] ?? '' }}</p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-[#EEF0FF] px-2.5 py-1 text-xs font-semibold text-[#4B4FD6]">
                                        ~{{ $topic['minutes'] }} {{ __('min') }}
                                    </span>
                                    @if($topic['tour'])
                                        <button type="button"
                                                data-help-start-tour="{{ $topic['tour']['key'] }}"
                                                data-help-tour-url="{{ $topic['tour']['start_url'] }}"
                                                class="inline-flex items-center gap-1.5 rounded-lg bg-[#5B5FF6] px-3 py-2 text-sm font-semibold text-white hover:bg-[#4B4FD6]">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z" /></svg>
                                            {{ __('Start tutorial') }}
                                        </button>
                                    @endif
                                </div>
                            </div>

                            @include('tutorials.partials._blocks', ['blocks' => $topic['blocks']])
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function helpCentre(searchText, hiddenTopics) {
            return {
                query: '',
                deadEnd: null,
                noResults: false,

                run() {
                    const q = this.query.toLowerCase().trim();
                    this.deadEnd = null;

                    let visible = 0;

                    // Filter EVERY region, or the page contradicts itself: a
                    // contents entry for a hidden topic is a broken link.
                    document.querySelectorAll('[data-help-topic]').forEach((el) => {
                        const key = el.dataset.helpTopic;
                        const hit = !q || (searchText[key] ?? '').toLowerCase().includes(q);
                        el.hidden = !hit;
                        document.querySelectorAll(`[data-help-nav="${key}"]`).forEach((n) => { n.hidden = !hit; });
                        if (hit) visible++;
                    });

                    // A group label with nothing under it is the same orphan
                    // problem as a heading with no content.
                    document.querySelectorAll('[data-help-group]').forEach((g) => {
                        g.hidden = ![...g.querySelectorAll('[data-help-nav]')].some((n) => !n.hidden);
                    });

                    document.querySelectorAll('[data-help-faq]').forEach((d) => {
                        if (q && d.textContent.toLowerCase().includes(q)) d.open = true;
                    });

                    window.dispatchEvent(new CustomEvent('help-search', { detail: q }));

                    this.noResults = false;
                    if (q && visible === 0) {
                        // Check the query against what this reader cannot see,
                        // and name the module and the permission to ask for.
                        const match = this.findHidden(q);
                        if (match) {
                            this.deadEnd = {
                                heading: `${match.title} is part of this system, but not part of your access.`,
                                detail: match.permissions.length
                                    ? `Ask an administrator for the "${match.permissions.join('" or "')}" permission and this section will appear here.`
                                    : 'Ask an administrator to enable it for your role.',
                            };
                        } else {
                            this.noResults = true;
                        }
                    }
                },

                findHidden(q) {
                    // Title matches rank above keyword matches, or a broad word
                    // surfaces the wrong module.
                    return hiddenTopics.find((t) => t.title.toLowerCase().includes(q))
                        ?? hiddenTopics.find((t) => t.keywords.toLowerCase().includes(q))
                        ?? null;
                },
            };
        }

        // Starting a tour from the help page: no step lives here, so seed the
        // resume point and go to the first step's screen.
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-help-start-tour]');
            if (!btn) return;

            const key = btn.dataset.helpStartTour;
            const url = btn.dataset.helpTourUrl;

            if (url) {
                sessionStorage.setItem('guided-tour-progress', JSON.stringify({ key, index: 0 }));
                window.location.href = url;
                return;
            }

            window.GuidedTour?.start(key);
        });
    </script>
    @endpush
</x-app-layout>
