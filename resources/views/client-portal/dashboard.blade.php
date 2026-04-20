<x-client-portal-layout :tenant="$tenant">
    <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
        <!-- Welcome -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">{{ __('Welcome back, :name', ['name' => $client->contact_person ?? $client->name]) }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ __('View and manage your support tickets.') }}</p>
            </div>
            <a href="{{ route('portal.tickets.create', ['tenant' => $tenant->slug]) }}" class="inline-flex items-center rounded-md px-3 py-2 text-sm font-semibold text-white shadow-sm" style="background-color: var(--portal-primary);">
                <svg class="-ml-0.5 mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ __('New Ticket') }}
            </a>
        </div>

        <!-- Stats -->
        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl bg-white p-6 shadow-sm">
                <div class="text-sm font-medium text-gray-500">{{ __('Open Tickets') }}</div>
                <div class="mt-2 text-3xl font-semibold text-gray-900">{{ $stats['open'] }}</div>
            </div>
            <div class="rounded-xl bg-white p-6 shadow-sm">
                <div class="text-sm font-medium text-gray-500">{{ __('Closed Tickets') }}</div>
                <div class="mt-2 text-3xl font-semibold text-gray-900">{{ $stats['closed'] }}</div>
            </div>
            <div class="rounded-xl bg-white p-6 shadow-sm">
                <div class="text-sm font-medium text-gray-500">{{ __('Total Tickets') }}</div>
                <div class="mt-2 text-3xl font-semibold text-gray-900">{{ $stats['total'] }}</div>
            </div>
        </div>

        <!-- Recent Tickets -->
        <div class="mt-8">
            <h2 class="text-lg font-semibold text-gray-900">{{ __('Recent Tickets') }}</h2>
            <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Ticket') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Subject') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Priority') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Status') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse($recentTickets as $ticket)
                            <tr>
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium" style="color: var(--portal-primary);">
                                    <a href="{{ route('portal.tickets.show', ['tenant' => $tenant->slug, 'ticket' => $ticket->id]) }}">
                                        {{ $ticket->ticket_number }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">{{ $ticket->subject }}</td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <x-badge :type="$ticket->priority">{{ ucfirst($ticket->priority) }}</x-badge>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <x-badge :type="$ticket->status">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</x-badge>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ $ticket->created_at->format('M d, Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">
                                    {{ __('No tickets yet.') }}
                                    <a href="{{ route('portal.tickets.create', ['tenant' => $tenant->slug]) }}" class="font-medium" style="color: var(--portal-primary);">{{ __('Submit your first ticket') }}</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-client-portal-layout>
