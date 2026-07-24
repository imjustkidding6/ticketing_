<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">{{ __('Department Report') }}</h2>
            @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::DetailedReporting))
            <a href="{{ route('reports.export.departments', $filters) }}" class="inline-flex items-center rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                <svg class="h-4 w-4 mr-1 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                {{ __('Export CSV') }}
            </a>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
            @include('reports._filters', ['action' => route('reports.departments'), 'exclude' => ['department_id']])

            @include('reports._charts', ['topData' => $departmentReport->sortByDesc('total')->take(10)->values(), 'topLabel' => __('Departments'), 'trendTitle' => __('Tickets by Department')])

            <div class="mt-6 overflow-hidden rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Department') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Total') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Open') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Closed') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                        @forelse($departmentReport as $dept)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $dept['name'] }}</td>
                                <td class="px-6 py-4 text-right text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $dept['total'] }}</td>
                                <td class="px-6 py-4 text-right text-sm font-medium text-blue-600 dark:text-blue-400">{{ $dept['open'] }}</td>
                                <td class="px-6 py-4 text-right text-sm font-medium text-green-600 dark:text-green-400">{{ $dept['closed'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No department data for this period.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>