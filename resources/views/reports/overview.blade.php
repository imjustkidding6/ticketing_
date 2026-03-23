<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Reports') }}</h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('reports.export.volume', $filters) }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                    <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    {{ __('Export Volume') }}
                </a>
                <a href="{{ route('reports.export.departments', $filters) }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                    <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    {{ __('Export Departments') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
            @include('reports._filters', ['action' => route('reports.overview')])

            {{-- Volume Stats --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="text-sm font-medium text-gray-500">{{ __('Total Tickets') }}</div>
                    <div class="mt-2 text-3xl font-semibold text-gray-900">{{ $volume['total'] }}</div>
                </div>
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="text-sm font-medium text-gray-500">{{ __('Open') }}</div>
                    <div class="mt-2 text-3xl font-semibold text-blue-600">{{ $volume['open'] }}</div>
                </div>
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="text-sm font-medium text-gray-500">{{ __('Closed') }}</div>
                    <div class="mt-2 text-3xl font-semibold text-green-600">{{ $volume['closed'] }}</div>
                </div>
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="text-sm font-medium text-gray-500">{{ __('In Progress') }}</div>
                    <div class="mt-2 text-3xl font-semibold text-purple-600">{{ $volume['in_progress'] }}</div>
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

            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                {{-- By Priority --}}
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Tickets by Priority') }}</h3>
                    <div class="mt-4 space-y-3">
                        @php
                            $priorityColors = ['critical' => 'bg-red-500', 'high' => 'bg-orange-500', 'medium' => 'bg-yellow-500', 'low' => 'bg-green-500'];
                            $maxPriority = max(1, max($volume['by_priority']));
                        @endphp
                        @foreach($volume['by_priority'] as $priority => $count)
                            <div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="font-medium text-gray-700">{{ ucfirst($priority) }}</span>
                                    <span class="text-gray-900">{{ $count }}</span>
                                </div>
                                <div class="mt-1 h-2 rounded-full bg-gray-100">
                                    <div class="h-2 rounded-full {{ $priorityColors[$priority] }}" style="width: {{ ($count / $maxPriority) * 100 }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- By Status --}}
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Tickets by Status') }}</h3>
                    <div class="mt-4 space-y-3">
                        @php
                            $statusColors = ['open' => 'bg-blue-500', 'assigned' => 'bg-indigo-500', 'in_progress' => 'bg-purple-500', 'on_hold' => 'bg-yellow-500', 'closed' => 'bg-green-500', 'cancelled' => 'bg-gray-500'];
                            $maxStatus = max(1, max($volume['by_status']));
                        @endphp
                        @foreach($volume['by_status'] as $status => $count)
                            <div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="font-medium text-gray-700">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                                    <span class="text-gray-900">{{ $count }}</span>
                                </div>
                                <div class="mt-1 h-2 rounded-full bg-gray-100">
                                    <div class="h-2 rounded-full {{ $statusColors[$status] }}" style="width: {{ ($count / $maxStatus) * 100 }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            @include('reports._charts', ['topData' => $topDepartments, 'topLabel' => __('Departments')])

            {{-- Top Clients & Top Agents --}}
            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <div class="rounded-xl bg-white shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900 px-6 pt-6">{{ __('Top Clients') }}</h3>
                    <div id="overview_top_clients" class="px-2 pb-4"></div>
                </div>
                <div class="rounded-xl bg-white shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900 px-6 pt-6">{{ __('Top Agents') }}</h3>
                    <div id="overview_top_agents" class="px-2 pb-4"></div>
                </div>
            </div>

            {{-- Department Breakdown --}}
            <div class="mt-6 rounded-xl bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">{{ __('Tickets by Department') }}</h3>
                @if($departmentReport->count() > 0)
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="py-2 text-left text-xs font-medium uppercase text-gray-500">{{ __('Department') }}</th>
                                    <th class="py-2 text-right text-xs font-medium uppercase text-gray-500">{{ __('Total') }}</th>
                                    <th class="py-2 text-right text-xs font-medium uppercase text-gray-500">{{ __('Open') }}</th>
                                    <th class="py-2 text-right text-xs font-medium uppercase text-gray-500">{{ __('Closed') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($departmentReport as $dept)
                                    <tr>
                                        <td class="py-2 text-sm text-gray-900">{{ $dept['name'] }}</td>
                                        <td class="py-2 text-right text-sm font-medium text-gray-900">{{ $dept['total'] }}</td>
                                        <td class="py-2 text-right text-sm text-blue-600">{{ $dept['open'] }}</td>
                                        <td class="py-2 text-right text-sm text-green-600">{{ $dept['closed'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="mt-4 text-sm text-gray-500">{{ __('No department data available.') }}</p>
                @endif
            </div>
        </div>
    </div>

    <script>
    function _initOverviewCharts() {
        if (typeof ApexCharts === 'undefined') { setTimeout(_initOverviewCharts, 50); return; }
        var c1 = document.getElementById('overview_top_clients');
        var c2 = document.getElementById('overview_top_agents');
        function renderBarChart(el, rawData, color) {
            if (!rawData || rawData.length === 0) { el.innerHTML = '<p class="text-sm text-gray-500 text-center py-12">{{ __("No data available.") }}</p>'; return; }
            var names = [];
            var values = [];
            for (var i = 0; i < rawData.length; i++) {
                names.push(rawData[i].name);
                values.push(Number(rawData[i].total));
            }
            new ApexCharts(el, {
                chart: { type: 'bar', height: 280, toolbar: { show: false } },
                series: [{ name: 'Tickets', data: values }],
                xaxis: { categories: names },
                yaxis: { labels: { style: { fontSize: '12px' } } },
                plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '60%' } },
                colors: [color], dataLabels: { enabled: true }, grid: { borderColor: '#f3f4f6' }
            }).render();
        }
        renderBarChart(c1, @json(array_values($topClients->toArray())), '#10b981');
        renderBarChart(c2, @json(array_values($topAgents->toArray())), '#8b5cf6');
    }
    setTimeout(function() {
        _initOverviewCharts();
        setTimeout(function() { window.dispatchEvent(new Event('resize')); }, 500);
    }, 200);
    </script>
</x-app-layout>
