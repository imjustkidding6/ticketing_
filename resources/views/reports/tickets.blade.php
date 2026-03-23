<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Tickets Report') }}</h2>
            <a href="{{ route('reports.export.tickets', $filters) }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                {{ __('Export CSV') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
            @include('reports._filters', ['action' => route('reports.tickets')])

            {{-- Summary Stats --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="text-sm font-medium text-gray-500">{{ __('Total Tickets') }}</div>
                    <div class="mt-2 text-3xl font-semibold text-gray-900">{{ $ticketReport->count() }}</div>
                </div>
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="text-sm font-medium text-gray-500">{{ __('Closed') }}</div>
                    <div class="mt-2 text-3xl font-semibold text-green-600">{{ $resolution['total_closed'] }}</div>
                </div>
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="text-sm font-medium text-gray-500">{{ __('Avg. Resolution') }}</div>
                    <div class="mt-2 text-3xl font-semibold text-gray-900">{{ \App\Models\Ticket::formatHours($resolution['avg_resolution_hours']) }}</div>
                </div>
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="text-sm font-medium text-gray-500">{{ __('Avg. Work Time') }}</div>
                    <div class="mt-2 text-3xl font-semibold text-indigo-600">{{ \App\Models\Ticket::formatHours($resolution['avg_work_hours']) }}</div>
                </div>
            </div>

            {{-- Trend Chart --}}
            @include('reports._charts', ['topData' => $ticketReport->where('status', 'closed')->groupBy('priority')->map(fn($items, $key) => ['name' => ucfirst($key), 'total' => $items->count()])->values(), 'topLabel' => __('Closed by Priority')])

            {{-- Tickets Table --}}
            <div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Ticket') }}</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Client') }}</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Priority') }}</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Status') }}</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Agent') }}</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Created') }}</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Started') }}</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Closed') }}</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Resolution') }}</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Work Time') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse($ticketReport as $t)
                                <tr>
                                    <td class="px-4 py-3 text-sm">
                                        <div class="font-medium text-indigo-600">{{ $t['ticket_number'] }}</div>
                                        <div class="text-xs text-gray-500 truncate max-w-[150px]">{{ $t['subject'] }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ $t['client'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <x-badge :type="$t['priority']">{{ ucfirst($t['priority']) }}</x-badge>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <x-badge :type="$t['status']">{{ ucfirst(str_replace('_', ' ', $t['status'])) }}</x-badge>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ $t['assigned_to'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-500">{{ $t['created_at'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-500">{{ $t['in_progress_at'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-500">{{ $t['closed_at'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-gray-900">{{ $t['resolution_formatted'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-indigo-600">{{ $t['work_formatted'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-4 py-12 text-center text-sm text-gray-500">{{ __('No tickets found for this period.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
