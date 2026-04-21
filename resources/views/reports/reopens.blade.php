<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Reopen Report') }}</h2>
            @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::DetailedReporting))
                <a href="{{ route('reports.export.reopens', $filters) }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                    <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    {{ __('Export CSV') }}
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
            <!-- Date Filter -->
            <div class="mb-6 overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                <form method="GET" class="flex flex-wrap items-end gap-4">
                    <div>
                        <label for="from" class="block text-sm font-medium text-gray-700">{{ __('From') }}</label>
                        <input type="date" name="from" id="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <div>
                        <label for="to" class="block text-sm font-medium text-gray-700">{{ __('To') }}</label>
                        <input type="date" name="to" id="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Filter') }}</button>
                </form>
            </div>

            {{-- Headline metrics --}}
            <div class="grid gap-6 sm:grid-cols-2 mb-6">
                <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-gray-500">{{ __('Reopened Tickets') }}</p>
                    <p class="mt-2 text-3xl font-bold text-amber-600">{{ $report['total'] }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('With at least one reopen in range') }}</p>
                </div>
                <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-gray-500">{{ __('Avg Reopen Count') }}</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ $report['avg_reopen_count'] }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('Per reopened ticket') }}</p>
                </div>
            </div>

            {{-- Breakdowns --}}
            <div class="grid gap-6 lg:grid-cols-2 mb-6">
                @foreach([
                    ['title' => __('By Department'), 'data' => $report['by_department']],
                    ['title' => __('By Category'), 'data' => $report['by_category']],
                    ['title' => __('By Agent'), 'data' => $report['by_agent']],
                    ['title' => __('By Client'), 'data' => $report['by_client']],
                ] as $section)
                    <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                        <h3 class="text-base font-semibold text-gray-900">{{ $section['title'] }}</h3>
                        @if($section['data']->isEmpty())
                            <p class="mt-3 text-sm text-gray-500">{{ __('No data.') }}</p>
                        @else
                            <ul class="mt-3 divide-y divide-gray-100 text-sm">
                                @foreach($section['data']->take(10) as $name => $count)
                                    <li class="flex items-center justify-between py-2">
                                        <span class="text-gray-700">{{ $name }}</span>
                                        <span class="font-medium text-gray-900">{{ $count }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Per-ticket detail --}}
            <div class="overflow-hidden rounded-xl bg-white shadow-sm mb-6">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('Reopened Tickets') }}</h3>
                    <span class="text-xs text-gray-500">{{ $report['tickets']->count() }} {{ __('tickets') }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-medium uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-left">{{ __('Ticket') }}</th>
                                <th class="px-4 py-2 text-left">{{ __('Client') }}</th>
                                <th class="px-4 py-2 text-left">{{ __('Agent') }}</th>
                                <th class="px-4 py-2 text-center">{{ __('Reopens') }}</th>
                                <th class="px-4 py-2 text-left">{{ __('First Closed') }}</th>
                                <th class="px-4 py-2 text-left">{{ __('Last Reopened') }}</th>
                                <th class="px-4 py-2 text-left">{{ __('Reason') }}</th>
                                <th class="px-4 py-2 text-left">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($report['tickets'] as $t)
                                <tr>
                                    <td class="px-4 py-2">
                                        <a href="{{ route('tickets.show', $t) }}" class="font-medium text-indigo-600 hover:text-indigo-500">{{ $t->ticket_number }}</a>
                                        <div class="text-xs text-gray-500 truncate max-w-[200px]">{{ $t->subject }}</div>
                                    </td>
                                    <td class="px-4 py-2 text-gray-700">{{ $t->client?->name ?? '—' }}</td>
                                    <td class="px-4 py-2 text-gray-700">{{ $t->assignee?->name ?? __('Unassigned') }}</td>
                                    <td class="px-4 py-2 text-center">
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">× {{ $t->reopened_count }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-gray-600">{{ $t->first_closed_at?->format('M j, Y') ?? '—' }}</td>
                                    <td class="px-4 py-2 text-xs text-gray-600">{{ $t->last_reopened_at?->format('M j, Y') ?? '—' }}</td>
                                    <td class="px-4 py-2 text-xs text-gray-700 max-w-[220px] truncate">{{ $t->last_reopen_reason ?? '—' }}</td>
                                    <td class="px-4 py-2"><x-badge :type="$t->status">{{ ucfirst(str_replace('_', ' ', $t->status)) }}</x-badge></td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">{{ __('No reopened tickets in range.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-end">
                <a href="{{ route('reports.overview') }}" class="text-sm text-indigo-600 hover:text-indigo-900">{{ __('Back to Reports') }}</a>
            </div>
        </div>
    </div>
</x-app-layout>
