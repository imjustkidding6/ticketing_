<x-client-portal-layout :tenant="$tenant">
    <div class="mx-auto max-w-lg px-4 sm:px-6 lg:px-8">
        <div class="rounded-xl bg-white p-8 shadow-sm">
            <h2 class="text-2xl font-semibold text-gray-900">{{ __('Track a Ticket') }}</h2>
            <p class="mt-2 text-sm text-gray-500">{{ __('Enter your ticket number and email to check its status.') }}</p>

            <form method="GET" action="{{ route('tenant.track-ticket', ['slug' => $tenant->slug]) }}" class="mt-8 space-y-5">
                <div>
                    <label for="ticket_number" class="block text-sm font-medium text-gray-700">{{ __('Ticket Number') }}</label>
                    <input type="text" name="ticket_number" id="ticket_number" value="{{ request('ticket_number') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="TKT-XXXXXXX">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">{{ __('Email Address') }}</label>
                    <input type="email" name="email" id="email" value="{{ request('email') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="you@example.com">
                </div>

                <button type="submit" class="w-full rounded-md px-4 py-2.5 text-sm font-semibold text-white shadow-sm" style="background-color: var(--portal-primary);">
                    {{ __('Track Ticket') }}
                </button>
            </form>

            @if($searched && $ticket)
                <div class="mt-8 rounded-lg border border-gray-200 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-lg font-medium text-gray-900">{{ $ticket->ticket_number }}</h3>
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                            @if(in_array($ticket->status, ['open', 'assigned', 'in_progress'])) bg-blue-100 text-blue-800
                            @elseif($ticket->status === 'on_hold') bg-yellow-100 text-yellow-800
                            @elseif($ticket->status === 'closed') bg-green-100 text-green-800
                            @else bg-gray-100 text-gray-800
                            @endif">
                            {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                        </span>
                    </div>
                    <p class="text-sm font-medium text-gray-900">{{ $ticket->subject }}</p>
                    <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <span class="text-gray-500">{{ __('Priority') }}:</span>
                            <span class="font-medium text-gray-900">{{ ucfirst($ticket->priority) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">{{ __('Department') }}:</span>
                            <span class="font-medium text-gray-900">{{ $ticket->department?->name ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">{{ __('Created') }}:</span>
                            <span class="font-medium text-gray-900">{{ $ticket->created_at->format('M d, Y') }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">{{ __('Updated') }}:</span>
                            <span class="font-medium text-gray-900">{{ $ticket->updated_at->diffForHumans() }}</span>
                        </div>
                    </div>
                </div>
            @elseif($searched && !$ticket)
                <div class="mt-8 rounded-md bg-yellow-50 border border-yellow-200 p-4">
                    <p class="text-sm text-yellow-800">{{ __('No ticket found matching that ticket number and email. Please check your details and try again.') }}</p>
                </div>
            @endif
        </div>
    </div>
</x-client-portal-layout>
