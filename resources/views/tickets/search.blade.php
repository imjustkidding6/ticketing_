<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Search Tickets') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
            <!-- Search Form -->
            <div class="mb-6 rounded-xl bg-white p-4 shadow-sm">
                <form method="GET" action="{{ route('tickets.search') }}" class="flex items-center gap-3">
                    <div class="relative flex-1">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                        </div>
                        <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search by ticket number, subject, client name...') }}" class="block w-full rounded-md border-gray-300 pl-10 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" autofocus>
                    </div>
                    <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        {{ __('Search') }}
                    </button>
                </form>
            </div>

            <!-- Results -->
            @if(request('q'))
                <div class="mb-4 text-sm text-gray-500">
                    {{ __('Showing results for') }} "<span class="font-medium text-gray-900">{{ request('q') }}</span>"
                    @if(isset($tickets))
                        &mdash; {{ $tickets->total() }} {{ Str::plural('result', $tickets->total()) }} {{ __('found') }}
                    @endif
                </div>

                <x-data-table>
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Ticket') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Client') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Priority') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Status') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Assigned To') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Created') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse($tickets as $ticket)
                            <tr class="{{ $ticket->isOverdue() ? 'bg-red-50' : '' }}">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-indigo-600">
                                        <a href="{{ route('tickets.show', $ticket) }}">{{ $ticket->ticket_number }}</a>
                                    </div>
                                    <div class="text-sm text-gray-900">{{ Str::limit($ticket->subject, 50) }}</div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ $ticket->client?->name ?? '-' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <x-badge :type="$ticket->priority">{{ ucfirst($ticket->priority) }}</x-badge>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <x-badge :type="$ticket->status">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</x-badge>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ $ticket->assignee?->name ?? __('Unassigned') }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ $ticket->created_at->format('M d, Y') }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <a href="{{ route('tickets.show', $ticket) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('View') }}</a>
                                    <a href="{{ route('tickets.edit', $ticket) }}" class="ml-3 text-indigo-600 hover:text-indigo-900">{{ __('Edit') }}</a>
                                </td>
                            </tr>
                        @empty
                            <x-empty-state :colspan="7" :message="__('No tickets match your search.')">
                                <x-slot name="icon">
                                    <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                    </svg>
                                </x-slot>
                            </x-empty-state>
                        @endforelse
                    </tbody>
                </x-data-table>

                @if(isset($tickets) && $tickets->hasPages())
                    <div class="mt-4">
                        {{ $tickets->appends(request()->query())->links() }}
                    </div>
                @endif
            @else
                <!-- Empty State Before Search -->
                <div class="rounded-xl bg-white p-12 text-center shadow-sm">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                        <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-medium text-gray-900">{{ __('Search for tickets') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ __('Enter a ticket number, subject, or client name to find tickets.') }}</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
