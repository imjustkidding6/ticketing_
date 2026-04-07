<x-client-portal-layout :tenant="$tenant" :hide-nav="true">
    <div class="mx-auto max-w-lg px-4 sm:px-6 lg:px-8">
        <div class="rounded-xl bg-white p-8 shadow-sm">
            <div class="text-center mb-6">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full mb-4" style="background-color: color-mix(in srgb, var(--portal-primary) 15%, white);">
                    <svg class="h-8 w-8" style="color: var(--portal-primary);" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </div>
                <h2 class="text-2xl font-semibold text-gray-900">{{ __('Find Your Ticket') }}</h2>
                <p class="mt-2 text-sm text-gray-500">{{ __('Enter your ticket number and email address to view the current status and updates.') }}</p>
            </div>

            <form method="GET" action="{{ route('tenant.track-ticket', ['slug' => $tenant->slug]) }}" class="space-y-5">
                <div>
                    <label for="ticket_number" class="block text-sm font-medium text-gray-700">{{ __('Ticket Number') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="ticket_number" id="ticket_number" value="{{ request('ticket_number') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="e.g., TKT-2026-001234">
                    <p class="mt-1 text-xs text-gray-400">{{ __('The ticket number was provided when you submitted your request') }}</p>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">{{ __('Email Address') }} <span class="text-red-500">*</span></label>
                    <input type="email" name="email" id="email" value="{{ request('email') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="your@email.com">
                    <p class="mt-1 text-xs text-gray-400">{{ __('Use the same email address you used when submitting the ticket') }}</p>
                </div>

                <button type="submit" class="w-full rounded-md px-4 py-2.5 text-sm font-semibold text-white shadow-sm" style="background-color: var(--portal-primary);">
                    <span class="inline-flex items-center">
                        <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        {{ __('Find Ticket') }}
                    </span>
                </button>
            </form>

            @if($searched)
                <div class="mt-6 rounded-md bg-yellow-50 border border-yellow-200 p-4">
                    <p class="text-sm text-yellow-800">{{ __('No ticket found matching that ticket number and email. Please check your details and try again.') }}</p>
                </div>
            @endif

            <div class="mt-8 border-t border-gray-200 pt-6">
                <div class="rounded-lg bg-gray-50 p-4">
                    <h3 class="font-semibold text-gray-900 mb-2 flex items-center text-sm">
                        <svg class="h-4 w-4 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                        </svg>
                        {{ __('Can\'t find your ticket number?') }}
                    </h3>
                    <div class="text-sm text-gray-600 space-y-1">
                        <p>{{ __('Ticket numbers typically start with "TKT-" followed by the date and a code') }}</p>
                        <p>{{ __('Make sure you\'re using the exact email address from the original submission') }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-4 space-y-2">
                <a href="{{ route('tenant.submit-ticket', ['slug' => $tenant->slug]) }}" class="flex items-center justify-center w-full rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                    <svg class="mr-2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ __('Submit New Ticket') }}
                </a>
                <a href="{{ route('tenant.landing', ['slug' => $tenant->slug]) }}" class="flex items-center justify-center w-full rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                    <svg class="mr-2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                    </svg>
                    {{ __('Back to Home') }}
                </a>
            </div>
        </div>
    </div>
</x-client-portal-layout>
