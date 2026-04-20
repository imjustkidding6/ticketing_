<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Welcome back') }}, {{ $profileSummary['name'] }}!</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">

            {{-- ── My Ticket Stats ── --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="text-sm font-medium text-gray-500">{{ __('My Open Tickets') }}</div>
                    <div class="mt-2 text-3xl font-semibold text-blue-600">{{ $myTicketStats['open'] }}</div>
                </div>
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="text-sm font-medium text-gray-500">{{ __('In Progress') }}</div>
                    <div class="mt-2 text-3xl font-semibold text-purple-600">{{ $myTicketStats['in_progress'] }}</div>
                </div>
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="text-sm font-medium text-gray-500">{{ __('Closed This Month') }}</div>
                    <div class="mt-2 text-3xl font-semibold text-green-600">{{ $myTicketStats['closed_this_month'] }}</div>
                </div>
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="text-sm font-medium text-gray-500">{{ __('Resolved Today') }}</div>
                    <div class="mt-2 text-3xl font-semibold text-gray-900">{{ $myPerformance['resolved_today'] }}</div>
                </div>
            </div>

            {{-- ── My Performance ── --}}
            <div class="mt-6 rounded-xl bg-indigo-50 p-6 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-indigo-600">{{ __('My Performance (Last 30 Days)') }}</h3>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <div class="text-xs font-medium text-gray-500">{{ __('Total Closed') }}</div>
                        <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $myTicketStats['total_closed'] }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500">{{ __('Avg. Resolution Time') }}</div>
                        <div class="mt-1 text-2xl font-semibold text-indigo-600">{{ \App\Models\Ticket::formatHours($myPerformance['avg_resolution_hours']) }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500">{{ __('Avg. Work Time') }}</div>
                        <div class="mt-1 text-2xl font-semibold text-indigo-600">{{ \App\Models\Ticket::formatHours($myPerformance['avg_work_hours']) }}</div>
                    </div>
                </div>
            </div>

            {{-- ── Charts Row ── --}}
            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                {{-- My Ticket Trend --}}
                <div class="rounded-xl bg-white shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900 px-6 pt-6">{{ __('My Tickets (14 Days)') }}</h3>
                    <div id="myTrendChart" class="px-2 pb-4"></div>
                </div>

                {{-- My Distribution --}}
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('My Distribution') }}</h3>
                    <div class="mt-4 grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-center text-xs font-medium text-gray-500 mb-2">{{ __('By Status') }}</p>
                            <div id="myStatusChart"></div>
                        </div>
                        <div>
                            <p class="text-center text-xs font-medium text-gray-500 mb-2">{{ __('By Priority') }}</p>
                            <div id="myPriorityChart"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── My Open Tickets + My Tasks ── --}}
            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                {{-- My Open Tickets --}}
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">{{ __('My Open Tickets') }}</h3>
                        <a href="{{ route('tickets.index', ['assigned_to' => Auth::id(), 'status' => 'open']) }}" class="text-sm text-indigo-600 hover:text-indigo-800">{{ __('View all') }}</a>
                    </div>
                    @if($myTickets->count() > 0)
                        <ul class="mt-4 divide-y divide-gray-200">
                            @foreach($myTickets as $ticket)
                                <li class="py-3">
                                    <a href="{{ route('tickets.show', $ticket) }}" class="flex items-center justify-between hover:bg-gray-50 -mx-2 px-2 py-1 rounded-md">
                                        <div class="min-w-0">
                                            <div class="text-sm font-medium text-indigo-600">{{ $ticket->ticket_number }}</div>
                                            <div class="text-sm text-gray-900 truncate">{{ Str::limit($ticket->subject, 40) }}</div>
                                            <div class="text-xs text-gray-500">{{ $ticket->client?->name }} &middot; {{ $ticket->department?->name }}</div>
                                        </div>
                                        <div class="ml-3 flex items-center gap-2 shrink-0">
                                            <x-badge :type="$ticket->priority">{{ ucfirst($ticket->priority) }}</x-badge>
                                            <x-badge :type="$ticket->status">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</x-badge>
                                        </div>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-4 text-sm text-gray-500">{{ __('No open tickets assigned to you.') }}</p>
                    @endif
                </div>

                {{-- My Tasks --}}
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('My Tasks') }}</h3>
                    @if($myTasks->count() > 0)
                        <ul class="mt-4 divide-y divide-gray-200">
                            @foreach($myTasks as $task)
                                <li class="py-3">
                                    <div class="flex items-center justify-between">
                                        <div class="min-w-0">
                                            <div class="text-sm text-gray-900">{{ $task->description }}</div>
                                            @if($task->ticket)
                                                <a href="{{ route('tickets.show', $task->ticket_id) }}" class="text-xs text-indigo-600 hover:text-indigo-800">{{ $task->ticket->ticket_number }} — {{ Str::limit($task->ticket->subject, 30) }}</a>
                                            @endif
                                        </div>
                                        <x-badge :type="$task->status">{{ ucfirst(str_replace('_', ' ', $task->status)) }}</x-badge>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-4 text-sm text-gray-500">{{ __('No pending tasks.') }}</p>
                    @endif
                </div>
            </div>

            {{-- ── Activity Feed ── --}}
            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                {{-- My Activity Feed --}}
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('My Recent Activity') }}</h3>
                    @if($myActivity->count() > 0)
                        <ul class="mt-4 divide-y divide-gray-200">
                            @foreach($myActivity as $entry)
                                <li class="py-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="text-sm text-gray-700">{{ $entry->description ?? ucfirst(str_replace('_', ' ', $entry->action)) }}</p>
                                            @if($entry->ticket)
                                                <a href="{{ route('tickets.show', $entry->ticket_id) }}" class="text-xs text-indigo-600 hover:text-indigo-800">{{ $entry->ticket->ticket_number }}</a>
                                            @endif
                                        </div>
                                        <span class="text-xs text-gray-400 shrink-0">{{ $entry->created_at->diffForHumans() }}</span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-4 text-sm text-gray-500">{{ __('No recent activity.') }}</p>
                    @endif
                </div>
            </div>

            {{-- ── Quick Actions + Profile ── --}}
            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                {{-- Quick Actions --}}
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Quick Actions') }}</h3>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <a href="{{ route('tickets.create') }}" class="flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 hover:border-indigo-300">
                            <svg class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            {{ __('Create Ticket') }}
                        </a>
                        <a href="{{ route('tickets.index', ['assigned_to' => Auth::id()]) }}" class="flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 hover:border-indigo-300">
                            <svg class="h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z" /></svg>
                            {{ __('My Tickets') }}
                        </a>
                        <a href="{{ route('clients.index') }}" class="flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 hover:border-indigo-300">
                            <svg class="h-5 w-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                            {{ __('Clients') }}
                        </a>
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 hover:border-indigo-300">
                            <svg class="h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            {{ __('Profile') }}
                        </a>
                    </div>
                </div>

                {{-- Profile Summary --}}
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Profile Summary') }}</h3>
                    <dl class="mt-4 space-y-3">
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">{{ __('Name') }}</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ $profileSummary['name'] }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">{{ __('Email') }}</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ $profileSummary['email'] }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">{{ __('Role') }}</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ $profileSummary['role'] }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">{{ __('Departments') }}</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ $profileSummary['departments'] }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

        </div>
    </div>

    <script>
    function _initDashboardCharts() {
        if (typeof ApexCharts === 'undefined') { setTimeout(_initDashboardCharts, 50); return; }
        var trendEl = document.getElementById('myTrendChart');
        var trendData = @json($myTrend);
        var trendDates = [];
        var trendCounts = [];
        for (var i = 0; i < trendData.length; i++) {
            trendDates.push(trendData[i].date.substring(5));
            trendCounts.push(Number(trendData[i].count));
        }

        new ApexCharts(trendEl, {
            chart: { type: 'area', height: 240, toolbar: { show: false }, zoom: { enabled: false } },
            series: [{ name: 'My Tickets', data: trendCounts }],
            xaxis: { categories: trendDates, labels: { style: { fontSize: '10px' } } },
            yaxis: { min: 0, forceNiceScale: true, labels: { formatter: function(v) { return Math.round(v); } } },
            colors: ['#6366f1'],
            stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
            dataLabels: { enabled: false },
            grid: { borderColor: '#f3f4f6' }
        }).render();

        // Status donut
        var statusData = @json($myTicketsByStatus);
        var statusLabels = Object.keys(statusData).map(function(s) { return s.replace('_', ' '); });
        var statusValues = Object.values(statusData).map(Number);
        var statusColors = { open: '#3b82f6', assigned: '#6366f1', in_progress: '#8b5cf6', on_hold: '#f59e0b', closed: '#10b981', cancelled: '#6b7280' };
        var sColors = Object.keys(statusData).map(function(s) { return statusColors[s] || '#6b7280'; });

        if (statusValues.length > 0) {
            new ApexCharts(document.getElementById('myStatusChart'), {
                chart: { type: 'donut', height: 180 },
                series: statusValues,
                labels: statusLabels,
                colors: sColors,
                legend: { position: 'bottom', fontSize: '11px' },
                dataLabels: { enabled: false }
            }).render();
        } else {
            document.getElementById('myStatusChart').innerHTML = '<p class="text-sm text-gray-400 text-center py-8">{{ __("No data") }}</p>';
        }

        // Priority donut
        var priorityData = @json($myTicketsByPriority);
        var priorityLabels = Object.keys(priorityData).map(function(p) { return p.charAt(0).toUpperCase() + p.slice(1); });
        var priorityValues = Object.values(priorityData).map(Number);
        var priorityColors = { critical: '#ef4444', high: '#f97316', medium: '#eab308', low: '#22c55e' };
        var pColors = Object.keys(priorityData).map(function(p) { return priorityColors[p] || '#6b7280'; });

        if (priorityValues.length > 0) {
            new ApexCharts(document.getElementById('myPriorityChart'), {
                chart: { type: 'donut', height: 180 },
                series: priorityValues,
                labels: priorityLabels,
                colors: pColors,
                legend: { position: 'bottom', fontSize: '11px' },
                dataLabels: { enabled: false }
            }).render();
        } else {
            document.getElementById('myPriorityChart').innerHTML = '<p class="text-sm text-gray-400 text-center py-8">{{ __("No data") }}</p>';
        }
    }
    setTimeout(function() {
        _initDashboardCharts();
        setTimeout(function() { window.dispatchEvent(new Event('resize')); }, 500);
    }, 200);
    </script>
</x-app-layout>
