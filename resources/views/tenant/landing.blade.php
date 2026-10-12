<x-client-portal-layout :tenant="$tenant" :hide-nav="true">
    {{-- The hero sits on PAPER. It used to be a second slab of
         var(--portal-primary) directly below the header's slab, so the two fused
         into one block with an invisible seam and a -mt-8 patching it. The
         tenant's colour now appears once, in the band above. --}}
    <div class="mx-auto max-w-4xl px-4 pt-4 sm:px-6 lg:px-8">
        @if($tenant->logo_path)
            <img src="{{ $tenant->logoUrl() }}" alt="{{ $tenant->displayName() }}" class="mb-8 h-12 w-auto">
        @endif
        <h1 class="portal-display text-4xl text-gray-900 sm:text-5xl">{{ __('How can we help?') }}</h1>
        <p class="portal-lede mt-5 text-lg leading-relaxed text-gray-600">
            {{ __('Send us a ticket and we\'ll pick it up. If you\'ve already sent one, you can check where it is.') }}
        </p>
    </div>

    {{-- Two actions, deliberately unequal. They were two identical shadow-lg
         cards, one with a green icon unrelated to this workspace's branding,
         which made a primary action and a secondary one read as peers. --}}
    <div class="mx-auto mt-12 max-w-4xl px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 gap-5 md:grid-cols-5">
            <div class="rounded-xl p-7 md:col-span-3" style="background-color: var(--portal-wash); box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--portal-primary) 18%, transparent);">
                <h2 class="text-xl font-semibold text-gray-900">{{ __('Submit a ticket') }}</h2>
                <p class="mt-2 max-w-sm text-sm leading-relaxed text-gray-600">
                    {{ __('Tell us what\'s going on. You\'ll get a ticket number so you can follow it.') }}
                </p>
                <a href="{{ route('tenant.submit-ticket', ['slug' => $tenant->slug]) }}" class="portal-focus mt-6 inline-flex w-full items-center justify-center rounded-md px-4 py-2.5 text-sm font-semibold text-white shadow-sm sm:w-auto sm:px-6" style="background-color: var(--portal-primary);">
                    {{ __('Submit a ticket') }}
                </a>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-7 md:col-span-2">
                <h2 class="text-xl font-semibold text-gray-900">{{ __('Track a ticket') }}</h2>
                <p class="mt-2 text-sm leading-relaxed text-gray-600">
                    {{ __('Enter your ticket number and email to see its status and any replies.') }}
                </p>
                <a href="{{ route('tenant.track-ticket', ['slug' => $tenant->slug]) }}" class="portal-focus mt-6 inline-flex w-full items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-50 sm:w-auto sm:px-6">
                    {{ __('Track a ticket') }}
                </a>
            </div>
        </div>

        {{-- Contact Info --}}
        @php
            $settings = \App\Models\AppSetting::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('group', 'general')->pluck('value', 'key');
        @endphp
        @if(!empty($settings['company_email']) || !empty($settings['company_phone']))
        <div class="mt-10 border-t border-gray-200 pt-6">
            <h3 class="text-sm font-semibold text-gray-900">{{ __('Need to reach us another way?') }}</h3>
            <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:gap-8">
                @if(!empty($settings['company_phone']))
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                    </svg>
                    <span class="text-sm text-gray-600">{{ $settings['company_phone'] }}</span>
                </div>
                @endif
                @if(!empty($settings['company_email']))
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                    </svg>
                    <span class="text-sm text-gray-600">{{ $settings['company_email'] }}</span>
                </div>
                @endif
            </div>
        </div>
        @endif
    </div>
</x-client-portal-layout>
