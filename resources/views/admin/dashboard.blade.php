@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    {{-- Stats Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Active Tenants</dt>
                            <dd class="text-3xl font-semibold text-gray-900">{{ $stats['active_tenants'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-5 py-3">
                <a href="{{ route('admin.tenants.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">{{ $stats['tenants'] }} total</a>
                @if($stats['suspended_tenants'] > 0)
                    <span class="text-sm text-red-500 ml-2">({{ $stats['suspended_tenants'] }} suspended)</span>
                @endif
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Active Licenses</dt>
                            <dd class="text-3xl font-semibold text-gray-900">{{ $stats['active_licenses'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-5 py-3">
                <a href="{{ route('admin.licenses.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">{{ $stats['licenses'] }} total</a>
                @if($stats['pending_licenses'] > 0)
                    <span class="text-sm text-yellow-600 ml-2">({{ $stats['pending_licenses'] }} pending)</span>
                @endif
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Distributors</dt>
                            <dd class="text-3xl font-semibold text-gray-900">{{ $stats['distributors'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-5 py-3">
                <a href="{{ route('admin.distributors.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">View all</a>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Plans</dt>
                            <dd class="text-3xl font-semibold text-gray-900">{{ $stats['plans'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-5 py-3">
                <a href="{{ route('admin.plans.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">View all</a>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-cyan-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Tickets (Month)</dt>
                            <dd class="text-3xl font-semibold text-gray-900">{{ $stats['tickets_this_month'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-5 py-3">
                <span class="text-sm text-gray-500">{{ $stats['total_tickets'] }} total</span>
            </div>
        </div>
    </div>

    <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- License Expiration Alerts --}}
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">License Expiration Alerts</h3>
            </div>
            <div class="p-6">
                @if($expiredLicenses->isNotEmpty())
                    <div class="mb-4">
                        <h4 class="text-sm font-semibold text-red-700 mb-2">Expired</h4>
                        @foreach($expiredLicenses as $license)
                            <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $license->tenant?->name ?? 'Unassigned' }}</p>
                                    <p class="text-xs text-gray-500">{{ $license->plan?->name }} &middot; Expired {{ $license->expires_at->diffForHumans() }}</p>
                                </div>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                    @if($license->isInGracePeriod())
                                        Grace ({{ $license->daysUntilFullExpiry() }}d)
                                    @else
                                        Fully Expired
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($expiringLicenses->isNotEmpty())
                    <div>
                        <h4 class="text-sm font-semibold text-yellow-700 mb-2">Expiring Within 30 Days</h4>
                        @foreach($expiringLicenses as $license)
                            <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $license->tenant?->name ?? 'Unassigned' }}</p>
                                    <p class="text-xs text-gray-500">{{ $license->plan?->name }} &middot; <code class="text-xs">{{ Str::limit($license->license_key, 14) }}</code></p>
                                </div>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                    @if($license->daysUntilExpiry() <= 7) bg-red-100 text-red-800
                                    @elseif($license->daysUntilExpiry() <= 14) bg-orange-100 text-orange-800
                                    @else bg-yellow-100 text-yellow-800
                                    @endif">
                                    {{ $license->daysUntilExpiry() }}d left
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($expiredLicenses->isEmpty() && $expiringLicenses->isEmpty())
                    <p class="text-sm text-gray-500 text-center py-4">No license alerts at this time.</p>
                @endif
            </div>
        </div>

        {{-- Plan Distribution --}}
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Plan Distribution</h3>
            </div>
            <div class="p-6">
                @php
                    $totalActiveLicenses = $planDistribution->sum('count');
                @endphp

                @if($totalActiveLicenses > 0)
                    {{-- Visual bar --}}
                    <div class="flex rounded-full overflow-hidden h-6 mb-4">
                        @php
                            $colors = ['bg-indigo-500', 'bg-green-500', 'bg-yellow-500', 'bg-purple-500', 'bg-cyan-500'];
                        @endphp
                        @foreach($planDistribution as $index => $plan)
                            @if($plan['count'] > 0)
                                <div class="{{ $colors[$index % count($colors)] }}" style="width: {{ ($plan['count'] / $totalActiveLicenses) * 100 }}%"
                                     title="{{ $plan['name'] }}: {{ $plan['count'] }}">
                                </div>
                            @endif
                        @endforeach
                    </div>

                    {{-- Legend --}}
                    <div class="space-y-3">
                        @foreach($planDistribution as $index => $plan)
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <span class="w-3 h-3 rounded-full {{ $colors[$index % count($colors)] }} mr-2"></span>
                                    <span class="text-sm text-gray-700">{{ $plan['name'] }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-sm font-medium text-gray-900">{{ $plan['count'] }}</span>
                                    <span class="text-xs text-gray-500 ml-1">({{ $totalActiveLicenses > 0 ? round(($plan['count'] / $totalActiveLicenses) * 100) : 0 }}%)</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-500 text-center py-4">No active tenant subscriptions yet.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Combined Tenants Table --}}
    @php
        $topIds    = $topTenants->pluck('id');
        $recentIds = $recentTenants->pluck('id');
        $allTenants = $topTenants->merge($recentTenants)->unique('id')->map(function ($tenant) use ($topIds, $recentIds) {
            $inTop    = $topIds->contains($tenant->id);
            $inRecent = $recentIds->contains($tenant->id);
            $tenant->view_type = ($inTop && $inRecent) ? 'both' : ($inTop ? 'top' : 'recent');
            return $tenant;
        });
    @endphp

    <div class="mt-6 bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900">Tenants</h3>
            <div class="flex gap-1 p-1 bg-gray-100 rounded-lg text-sm">
                <button onclick="setTenantFilter('all')" id="tenant-btn-all"
                    class="tenant-filter-btn px-3 py-1 rounded-md bg-white text-gray-900 font-medium transition-all">All</button>
                <button onclick="setTenantFilter('top')" id="tenant-btn-top"
                    class="tenant-filter-btn px-3 py-1 rounded-md text-gray-500 transition-all">Top activity</button>
                <button onclick="setTenantFilter('recent')" id="tenant-btn-recent"
                    class="tenant-filter-btn px-3 py-1 rounded-md text-gray-500 transition-all">Recently created</button>
            </div>
        </div>
        <div class="overflow-x-auto">
            @if($allTenants->isNotEmpty())
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tenant</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Plan</th>
                            <th id="tenant-th-users" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Users</th>
                            <th id="tenant-th-tickets" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Tickets</th>
                            <th id="tenant-th-created" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden">Created</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody id="tenant-table-body" class="divide-y divide-gray-200">
                        @foreach($allTenants as $tenant)
                            <tr data-type="{{ $tenant->view_type }}">
                                <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ $tenant->name }}</td>
                                <td class="px-6 py-3 text-sm text-gray-500">{{ $tenant->license?->plan?->name ?? '-' }}</td>
                                <td class="px-6 py-3 text-sm text-gray-900 text-right td-users">{{ $tenant->users_count }}</td>
                                <td class="px-6 py-3 text-sm text-gray-900 text-right td-tickets">{{ $tenant->ticket_count }}</td>
                                <td class="px-6 py-3 text-sm text-gray-500 td-created hidden">{{ $tenant->created_at->diffForHumans() }}</td>
                                <td class="px-6 py-3 text-right">
                                    <a href="{{ route('admin.tenants.show', $tenant) }}" class="text-indigo-600 hover:text-indigo-900 text-sm">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div id="tenant-empty-state" class="hidden p-6 text-center text-sm text-gray-500">No tenants found.</div>
            @else
                <div class="p-6">
                    <p class="text-sm text-gray-500 text-center">No tenants yet.</p>
                </div>
            @endif
        </div>
    </div>

    <script>
        function setTenantFilter(filter) {
            document.querySelectorAll('.tenant-filter-btn').forEach(btn => {
                btn.classList.remove('bg-white', 'text-gray-900', 'font-medium');
                btn.classList.add('text-gray-500');
            });
            const active = document.getElementById('tenant-btn-' + filter);
            active.classList.add('bg-white', 'text-gray-900', 'font-medium');
            active.classList.remove('text-gray-500');

            const showActivity = filter !== 'recent';
            const showCreated  = filter !== 'top';

            document.getElementById('tenant-th-users').classList.toggle('hidden', !showActivity);
            document.getElementById('tenant-th-tickets').classList.toggle('hidden', !showActivity);
            document.getElementById('tenant-th-created').classList.toggle('hidden', !showCreated);

            let visible = 0;
            document.querySelectorAll('#tenant-table-body tr').forEach(row => {
                const type  = row.dataset.type;
                const match = filter === 'all' || type === filter || type === 'both';
                row.classList.toggle('hidden', !match);
                if (match) {
                    visible++;
                    row.querySelectorAll('.td-users, .td-tickets').forEach(el => el.classList.toggle('hidden', !showActivity));
                    row.querySelectorAll('.td-created').forEach(el => el.classList.toggle('hidden', !showCreated));
                }
            });

            const emptyState = document.getElementById('tenant-empty-state');
            if (emptyState) emptyState.classList.toggle('hidden', visible > 0);
        }
    </script>

    {{-- Quick Actions --}}
    <div class="mt-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-medium text-gray-900">Quick Actions</h2>
        </div>
        <div class="flex space-x-4">
            <a href="{{ route('admin.licenses.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                Create License
            </a>
            <a href="{{ route('admin.distributors.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                Add Distributor
            </a>
        </div>
    </div>
@endsection