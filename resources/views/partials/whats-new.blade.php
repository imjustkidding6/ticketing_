{{--
    The floating "What's new" pill.

    Content comes from config/whats_new.php; this file only renders it. The pill is
    an invitation to the help centre rather than a second place release notes live,
    so it carries three lines and a link, and the detail stays in config/help.php
    where it is permission-scoped and searchable.

    Dismissal is per browser (localStorage, keyed by the release string), wrapped in
    try/catch because a private window or blocked site data throws on access rather
    than returning null. When storage is unavailable the pill simply shows — the
    failure mode is seeing a release note twice, not a broken page.

    It sits above the AI assistant launcher when that is present and takes its place
    when it is not, so the three floating controls never overlap.
--}}
@php
    $wnRelease = (string) config('whats_new.release');
    $wnItems = (array) config('whats_new.items', []);
    $wnOffset = $aiAssistantVisible ?? false ? '10.5rem' : '6rem';
@endphp

<div x-data="{
        open: false,
        show: false,
        release: @js($wnRelease),
        init() {
            // Absent key means never dismissed, so the pill shows. A throw means we
            // cannot tell, and showing it is the kinder of the two wrong answers.
            try {
                this.show = localStorage.getItem('whats-new:' + this.release) !== 'dismissed';
            } catch (e) {
                this.show = true;
            }
        },
        dismiss() {
            this.open = false;
            this.show = false;
            try {
                localStorage.setItem('whats-new:' + this.release, 'dismissed');
            } catch (e) {
                // Nothing to do: it will appear again next visit, which is acceptable.
            }
        },
     }"
     x-show="show"
     x-cloak
     class="fixed z-40"
     style="right: 1.5rem; bottom: {{ $wnOffset }};">

    {{-- The panel. Anchored bottom-right of the pill so it opens inward and never
         off-screen on a narrow viewport. --}}
    <div x-show="open"
         x-cloak
         @click.outside="open = false"
         x-transition
         class="absolute bottom-full right-0 mb-3 w-80 max-w-[calc(100vw-3rem)] rounded-xl border border-gray-200 bg-white p-4 shadow-xl">

        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">{{ config('whats_new.title') }}</h2>
                <p class="mt-0.5 text-xs text-gray-500">{{ config('whats_new.summary') }}</p>
            </div>
            <button type="button"
                    @click="dismiss()"
                    aria-label="{{ __('Dismiss') }}"
                    class="-m-1 rounded p-1 text-gray-400 transition-colors hover:text-gray-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        @if (! empty($wnItems))
            <ul class="mt-3 space-y-2">
                @foreach ($wnItems as $wnItem)
                    <li class="flex gap-2 text-xs leading-relaxed text-gray-600">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-500"></span>
                        <span>{{ $wnItem }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        <a href="{{ $wnUrl }}"
           @click="dismiss()"
           data-tour="whats-new-link"
           class="mt-4 flex w-full items-center justify-center gap-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
            {{ __('Read the guide') }}
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
            </svg>
        </a>
    </div>

    <button type="button"
            @click="open = ! open"
            data-tour="whats-new"
            class="flex items-center gap-2 rounded-full bg-white px-4 py-3 text-sm font-semibold text-gray-700 shadow-lg ring-1 ring-gray-200 transition-colors hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
        <span class="relative flex h-2 w-2">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-indigo-400 opacity-75"></span>
            <span class="relative inline-flex h-2 w-2 rounded-full bg-indigo-500"></span>
        </span>
        {{ config('whats_new.title') }}
    </button>
</div>
