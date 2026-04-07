<x-client-portal-layout :tenant="$tenant" :hide-nav="true">
    {{-- Hero Section --}}
    <div class="-mt-8 pb-20 pt-20" style="background-color: var(--portal-primary);">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 text-center">
            @if($tenant->logo_path)
                <img src="{{ $tenant->logoUrl() }}" alt="{{ $tenant->name }}" class="mx-auto h-16 w-auto mb-6">
            @endif
            <h1 class="text-3xl font-bold text-white sm:text-4xl">{{ __('How can we help you today?') }}</h1>
            <p class="mt-4 text-lg text-white/70 max-w-2xl mx-auto">
                {{ __('Submit a support ticket or track an existing one. Our team is here to help you resolve any issues quickly.') }}
            </p>
        </div>
    </div>

    {{-- Action Cards --}}
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 mt-10">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            {{-- Submit New Ticket --}}
            <div class="rounded-xl bg-white p-8 shadow-lg hover:shadow-xl transition-shadow">
                <div class="flex items-center gap-4 mb-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg" style="background-color: color-mix(in srgb, var(--portal-primary) 15%, white);">
                        <svg class="h-6 w-6" style="color: var(--portal-primary);" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">{{ __('Submit New Ticket') }}</h2>
                        <p class="text-sm text-gray-500">{{ __('Create a new support request') }}</p>
                    </div>
                </div>
                <p class="text-sm text-gray-600 mb-6">
                    {{ __('Describe your issue and we\'ll respond accordingly. You\'ll receive a ticket number to track your request.') }}
                </p>
                <a href="{{ route('tenant.submit-ticket', ['slug' => $tenant->slug]) }}" class="flex items-center justify-center w-full rounded-md px-4 py-2.5 text-sm font-semibold text-white shadow-sm" style="background-color: var(--portal-primary);">
                    {{ __('Submit New Ticket') }}
                    <svg class="ml-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </a>
            </div>

            {{-- Track Existing Ticket --}}
            <div class="rounded-xl bg-white p-8 shadow-lg hover:shadow-xl transition-shadow">
                <div class="flex items-center gap-4 mb-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-100">
                        <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">{{ __('Track Existing Ticket') }}</h2>
                        <p class="text-sm text-gray-500">{{ __('Check your ticket status') }}</p>
                    </div>
                </div>
                <p class="text-sm text-gray-600 mb-6">
                    {{ __('Enter your ticket number and email address to view the current status, updates, and communication history.') }}
                </p>
                <a href="{{ route('tenant.track-ticket', ['slug' => $tenant->slug]) }}" class="flex items-center justify-center w-full rounded-md border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    {{ __('Track Ticket') }}
                    <svg class="ml-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </a>
            </div>
        </div>

        {{-- Contact Info --}}
        @php
            $settings = \App\Models\AppSetting::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('group', 'general')->pluck('value', 'key');
        @endphp
        @if(!empty($settings['company_email']) || !empty($settings['company_phone']))
        <div class="mt-10 text-center">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">{{ __('Need immediate assistance?') }}</h3>
            <div class="flex flex-col sm:flex-row justify-center items-center gap-4 sm:gap-8">
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
