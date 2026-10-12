<x-client-portal-layout :tenant="$tenant" :hide-nav="true">
    {{-- One composed block, not a narrow strip down the left of a wide screen.
         On a large viewport the hero and the two actions sit side by side, so
         the page fills its width instead of leaving a dead right half; below
         lg they stack. The tenant's colour appears once, in the header band —
         this content is on paper. --}}
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8 lg:py-24">
        {{-- items-center, because the hero is three lines and the action column
             is two cards: aligned to the top they read as two unrelated things
             of unequal length. --}}
        <div class="grid grid-cols-1 items-center gap-10 lg:grid-cols-12 lg:gap-16">

            <div class="lg:col-span-5">
                @if($tenant->logo_path)
                    <img src="{{ $tenant->logoUrl() }}" alt="{{ $tenant->displayName() }}" class="mb-7 h-12 w-auto">
                @endif
                <h1 class="portal-display text-4xl text-gray-900 sm:text-5xl">{{ __('How can we help?') }}</h1>
                <p class="mt-5 text-lg leading-relaxed text-gray-600">
                    {{ __('Send us a ticket and we\'ll pick it up. If you\'ve already sent one, you can check where it is.') }}
                </p>

                {{-- Contact details belong with the words, not stranded under the
                     cards where they read as a page footer. --}}
                @php
                    $settings = \App\Models\AppSetting::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('group', 'general')->pluck('value', 'key');
                @endphp
                @if(!empty($settings['company_email']) || !empty($settings['company_phone']))
                <div class="mt-8 border-t border-gray-200 pt-6">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('Need to reach us another way?') }}</h2>
                    <div class="mt-3 space-y-2">
                        @if(!empty($settings['company_phone']))
                        <p class="text-sm text-gray-600">{{ $settings['company_phone'] }}</p>
                        @endif
                        @if(!empty($settings['company_email']))
                        <p class="text-sm text-gray-600">{{ $settings['company_email'] }}</p>
                        @endif
                    </div>
                </div>
                @endif
            </div>

            {{-- Two actions, deliberately unequal: they were identical shadowed
                 cards, one with a green icon unrelated to this workspace. --}}
            <div class="space-y-4 lg:col-span-7">
                <div class="rounded-xl p-6 sm:p-8" style="background-color: var(--portal-wash); box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--portal-primary) 20%, transparent);">
                    <h2 class="text-xl font-semibold text-gray-900">{{ __('Submit a ticket') }}</h2>
                    <p class="mt-2 max-w-md text-sm leading-relaxed text-gray-600">
                        {{ __('Tell us what\'s going on. You\'ll get a ticket number so you can follow it.') }}
                    </p>
                    <a href="{{ route('tenant.submit-ticket', ['slug' => $tenant->slug]) }}" class="portal-focus mt-6 inline-flex items-center justify-center rounded-md px-6 py-2.5 text-sm font-semibold text-white shadow-sm" style="background-color: var(--portal-primary);">
                        {{ __('Submit a ticket') }}
                    </a>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6 sm:p-8">
                    <h2 class="text-xl font-semibold text-gray-900">{{ __('Track a ticket') }}</h2>
                    <p class="mt-2 max-w-md text-sm leading-relaxed text-gray-600">
                        {{ __('Enter your ticket number and email to see its status and any replies.') }}
                    </p>
                    <a href="{{ route('tenant.track-ticket', ['slug' => $tenant->slug]) }}" class="portal-focus mt-6 inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-6 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-50">
                        {{ __('Track a ticket') }}
                    </a>
                </div>
            </div>

        </div>
    </div>
</x-client-portal-layout>
