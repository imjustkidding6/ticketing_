<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Agent Report') }}</h2>
            @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::DetailedReporting))
            <a href="{{ route('reports.export.agents', $filters) }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                {{ __('Export CSV') }}
            </a>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
            @include('reports._filters', ['action' => route('reports.agents'), 'exclude' => ['assigned_to']])

            @include('reports._charts', ['topData' => collect($agentReport)->sortByDesc('total')->take(10)->values(), 'topLabel' => __('Agents'), 'trendTitle' => __('Tickets by Agent')])

            <div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Agent') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Total') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Open') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Closed') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Avg Resolution (hrs)') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Reopened After') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Reopen Rate') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse($agentReport as $agent)
                            <tr>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $agent['name'] }}</td>
                                <td class="px-6 py-4 text-right text-sm font-semibold text-gray-900">{{ $agent['total'] }}</td>
                                <td class="px-6 py-4 text-right text-sm text-blue-600">{{ $agent['open'] }}</td>
                                <td class="px-6 py-4 text-right text-sm text-green-600">{{ $agent['closed'] }}</td>
                                <td class="px-6 py-4 text-right text-sm text-gray-500">{{ $agent['avg_resolution_hours'] }}h</td>
                                <td class="px-6 py-4 text-right text-sm text-amber-700">{{ $agent['reopened_after_closure'] ?? 0 }}</td>
                                <td class="px-6 py-4 text-right text-sm {{ ($agent['reopen_rate'] ?? 0) > 20 ? 'text-red-600 font-semibold' : (($agent['reopen_rate'] ?? 0) > 10 ? 'text-amber-600' : 'text-gray-500') }}">{{ $agent['reopen_rate'] ?? 0 }}%</td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('members.show', $agent['id']) }}#performance" class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                                        </svg>
                                        {{ __('Performance') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500">{{ __('No agent data for this period.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
