@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6">
        <!-- Active Tenants Card -->
        <div class="bg-white rounded-2xl border border-slate-100 p-6 flex flex-col justify-between transition-all duration-200 hover:shadow-md hover:shadow-indigo-50/10">
            <div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500 tracking-wider uppercase">Active Tenants</span>
                    <div class="p-2 bg-indigo-50 text-indigo-600 rounded-xl">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-slate-900 tracking-tight">{{ $stats['active_tenants'] }}</span>
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700">+2 new</span>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-50 flex items-center justify-between">
                <a href="{{ route('admin.tenants.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-900 transition-colors">{{ $stats['tenants'] }} total</a>
                @if($stats['suspended_tenants'] > 0)
                    <span class="text-xs font-medium text-rose-500">({{ $stats['suspended_tenants'] }} suspended)</span>
                @endif
            </div>
        </div>

        <!-- Active Licenses Card -->
        <div class="bg-white rounded-2xl border border-slate-100 p-6 flex flex-col justify-between transition-all duration-200 hover:shadow-md hover:shadow-indigo-50/10">
            <div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500 tracking-wider uppercase">Active Licenses</span>
                    <div class="p-2 bg-emerald-50 text-emerald-600 rounded-xl">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-slate-900 tracking-tight">{{ $stats['active_licenses'] }}</span>
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700">+3 this mo</span>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-50 flex items-center justify-between">
                <a href="{{ route('admin.licenses.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-900 transition-colors">{{ $stats['licenses'] }} total</a>
                @if($stats['pending_licenses'] > 0)
                    <span class="text-xs font-medium text-amber-600">({{ $stats['pending_licenses'] }} pending)</span>
                @endif
            </div>
        </div>

        <!-- Distributors Card -->
        <div class="bg-white rounded-2xl border border-slate-100 p-6 flex flex-col justify-between transition-all duration-200 hover:shadow-md hover:shadow-indigo-50/10">
            <div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500 tracking-wider uppercase">Distributors</span>
                    <div class="p-2 bg-amber-50 text-amber-600 rounded-xl">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-slate-900 tracking-tight">{{ $stats['distributors'] }}</span>
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700">Active</span>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-50">
                <a href="{{ route('admin.distributors.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-900 transition-colors">View all distributors</a>
            </div>
        </div>

        <!-- Plans Card -->
        <div class="bg-white rounded-2xl border border-slate-100 p-6 flex flex-col justify-between transition-all duration-200 hover:shadow-md hover:shadow-indigo-50/10">
            <div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500 tracking-wider uppercase">Plans</span>
                    <div class="p-2 bg-violet-50 text-violet-600 rounded-xl">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-slate-900 tracking-tight">{{ $stats['plans'] }}</span>
                    <span class="inline-flex items-center rounded-full bg-indigo-50 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700">3 subscription tiers</span>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-50">
                <a href="{{ route('admin.plans.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-900 transition-colors">Configure plans</a>
            </div>
        </div>

        <!-- Tickets Card -->
        <div class="bg-white rounded-2xl border border-slate-100 p-6 flex flex-col justify-between transition-all duration-200 hover:shadow-md hover:shadow-indigo-50/10">
            <div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500 tracking-wider uppercase">Tickets (Month)</span>
                    <div class="p-2 bg-sky-50 text-sky-600 rounded-xl">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 flex items-baseline gap-2">
                    <span class="text-3xl font-bold text-slate-900 tracking-tight">{{ $stats['tickets_this_month'] }}</span>
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700">+12% vs last</span>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-50">
                <span class="text-xs font-semibold text-slate-600">{{ $stats['total_tickets'] }} total tickets</span>
            </div>
        </div>
    </div>

    <!-- Alert Cards & Distribution -->
    <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- License Expiration Alerts -->
        <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
            <div class="flex items-center justify-between pb-4 border-b border-slate-50">
                <h3 class="text-sm font-bold text-slate-900 tracking-tight">License Expiration Alerts</h3>
                <span class="text-[10px] font-semibold text-slate-400 bg-slate-50 px-2 py-0.5 rounded-full">Real-time</span>
            </div>
            
            <div class="mt-6 space-y-4">
                @if($expiredLicenses->isNotEmpty())
                    <div>
                        <h4 class="text-xs font-bold text-rose-600 tracking-wider uppercase mb-2">Expired</h4>
                        <div class="space-y-3">
                            @foreach($expiredLicenses as $license)
                                <div class="flex items-start justify-between gap-4 p-4 rounded-xl border border-rose-100 bg-rose-50/50">
                                    <div class="flex items-start gap-3">
                                        <span class="text-base flex-shrink-0 mt-0.5">🔴</span>
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">{{ $license->tenant?->name ?? 'Unassigned Tenant' }}</p>
                                            <p class="text-xs text-slate-500 mt-1">
                                                {{ $license->plan?->name }} Plan &middot; 
                                                <span class="font-medium text-rose-600">Expired {{ $license->expires_at->diffForHumans() }}</span>
                                            </p>
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center rounded-md bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800">
                                        @if($license->isInGracePeriod())
                                            Grace Period Remaining
                                        @else
                                            Fully Expired
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($expiringLicenses->isNotEmpty())
                    <div class="{{ $expiredLicenses->isNotEmpty() ? 'mt-6' : '' }}">
                        <h4 class="text-xs font-bold text-amber-600 tracking-wider uppercase mb-2 font-semibold">Expiring Within 30 Days</h4>
                        <div class="space-y-3">
                            @foreach($expiringLicenses as $license)
                                <div class="flex items-start justify-between gap-4 p-4 rounded-xl border border-amber-100 bg-amber-50/50">
                                    <div class="flex items-start gap-3">
                                        <span class="text-base flex-shrink-0 mt-0.5">🟡</span>
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">{{ $license->tenant?->name ?? 'Unassigned Tenant' }}</p>
                                            <p class="text-xs text-slate-500 mt-1">
                                                {{ $license->plan?->name }} Plan &middot; 
                                                <code class="text-[10px] text-slate-400">{{ Str::limit($license->license_key, 16) }}</code>
                                            </p>
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center rounded-md px-2.5 py-0.5 text-xs font-semibold 
                                        @if($license->daysUntilExpiry() <= 7) bg-rose-100 text-rose-800
                                        @elseif($license->daysUntilExpiry() <= 14) bg-orange-100 text-orange-800
                                        @else bg-amber-100 text-amber-800
                                        @endif">
                                        {{ $license->daysUntilExpiry() }} days left
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($expiredLicenses->isEmpty() && $expiringLicenses->isEmpty())
                    <div class="flex flex-col items-center justify-center py-10 text-center">
                        <div class="h-10 w-10 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600 mb-3">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-slate-800">All licenses are in good standing</p>
                        <p class="text-xs text-slate-400 mt-0.5">No license alerts require your attention.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Plan Distribution -->
        <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
            <div class="flex items-center justify-between pb-4 border-b border-slate-50">
                <h3 class="text-sm font-bold text-slate-900 tracking-tight">Plan Distribution</h3>
                <span class="text-[10px] font-semibold text-slate-400 bg-slate-50 px-2 py-0.5 rounded-full">Active Subscriptions</span>
            </div>
            
            <div class="mt-6">
                @php
                    $totalActiveLicenses = $planDistribution->sum('count');
                @endphp

                @if($totalActiveLicenses > 0)
                    <!-- Horizontal Progress Bars -->
                    <div class="flex rounded-full overflow-hidden h-3 bg-slate-100 mb-6">
                        @php
                            $colors = ['bg-[#4F46E5]', 'bg-[#10B981]', 'bg-[#F59E0B]', 'bg-[#8B5CF6]', 'bg-[#06B6D4]'];
                        @endphp
                        @foreach($planDistribution as $index => $plan)
                            @if($plan['count'] > 0)
                                <div class="{{ $colors[$index % count($colors)] }} h-full" 
                                     style="width: {{ ($plan['count'] / $totalActiveLicenses) * 100 }}%"
                                     title="{{ $plan['name'] }}: {{ $plan['count'] }}">
                                </div>
                            @endif
                        @endforeach
                    </div>

                    <!-- Modern Legends -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach($planDistribution as $index => $plan)
                            <div class="flex items-center justify-between p-3 rounded-xl border border-slate-100 bg-slate-50/30">
                                <div class="flex items-center">
                                    <span class="w-2.5 h-2.5 rounded-full {{ $colors[$index % count($colors)] }} mr-2.5"></span>
                                    <span class="text-xs font-semibold text-slate-700">{{ $plan['name'] }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs font-bold text-slate-900">{{ $plan['count'] }}</span>
                                    <span class="text-[10px] text-slate-400 ml-1">({{ round(($plan['count'] / $totalActiveLicenses) * 100) }}%)</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-12 text-center">
                        <div class="h-10 w-10 rounded-full bg-slate-50 flex items-center justify-center text-slate-400 mb-3">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-slate-800">No active plans yet</p>
                        <p class="text-xs text-slate-400 mt-0.5">Distributors must assign licenses to tenants.</p>
                    </div>
                @endif
            </div>
        </div>

    </div>

    <!-- Tables Grid -->
    <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- Top Tenants by Activity -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-50">
                <h3 class="text-sm font-bold text-slate-900 tracking-tight">Top Tenants by Activity</h3>
                <a href="{{ route('admin.tenants.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-900">View all</a>
            </div>
            
            <div class="overflow-x-auto mt-4 rounded-xl border border-slate-100">
                @if($topTenants->isNotEmpty())
                    <table class="min-w-full divide-y divide-slate-100">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Tenant</th>
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Plan</th>
                                <th class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Users</th>
                                <th class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Tickets</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach($topTenants as $tenant)
                                <tr class="hover:bg-slate-50/80 transition duration-150 cursor-pointer" onclick="window.location='{{ route('admin.tenants.show', $tenant) }}'">
                                    <td class="whitespace-nowrap">
                                        <div class="text-sm font-bold text-slate-900">{{ $tenant->name }}</div>
                                    </td>
                                    <td class="whitespace-nowrap text-sm text-slate-500">
                                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                            {{ $tenant->license?->plan?->name ?? 'None' }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap text-sm text-slate-900 text-right font-medium">{{ $tenant->users_count }}</td>
                                    <td class="whitespace-nowrap text-sm text-slate-900 text-right font-medium">
                                        <span class="font-bold text-indigo-600">{{ $tenant->ticket_count }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="p-6 text-center">
                        <p class="text-xs text-slate-500">No active tenants found.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Recently Created Tenants -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-50">
                <h3 class="text-sm font-bold text-slate-900 tracking-tight">Recently Created Tenants</h3>
                <a href="{{ route('admin.tenants.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-900">View all</a>
            </div>

            <div class="overflow-x-auto mt-4 rounded-xl border border-slate-100">
                @if($recentTenants->isNotEmpty())
                    <table class="min-w-full divide-y divide-slate-100">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Tenant</th>
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Plan</th>
                                <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach($recentTenants as $tenant)
                                <tr class="hover:bg-slate-50/80 transition duration-150 cursor-pointer" onclick="window.location='{{ route('admin.tenants.show', $tenant) }}'">
                                    <td class="whitespace-nowrap">
                                        <div class="text-sm font-bold text-slate-900">{{ $tenant->name }}</div>
                                    </td>
                                    <td class="whitespace-nowrap text-sm text-slate-500">
                                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                            {{ $tenant->license?->plan?->name ?? 'None' }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap text-sm text-slate-500">
                                        {{ $tenant->created_at->diffForHumans() }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="p-6 text-center">
                        <p class="text-xs text-slate-500">No tenants created yet.</p>
                    </div>
                @endif
            </div>
        </div>

    </div>

    <!-- Quick Actions -->
    <div class="mt-8 bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
        <h3 class="text-sm font-bold text-slate-900 tracking-tight pb-4 border-b border-slate-50 mb-6">Quick Actions</h3>
        <div class="flex flex-wrap gap-4">
            <a href="{{ route('admin.licenses.create') }}" class="inline-flex items-center px-4 py-2.5 bg-indigo-600 text-white rounded-xl text-xs font-semibold hover:bg-indigo-700 transition duration-150">
                <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Create License
            </a>
            <a href="{{ route('admin.distributors.create') }}" class="inline-flex items-center px-4 py-2.5 border border-slate-200 bg-white text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-50 transition duration-150">
                <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Add Distributor
            </a>
        </div>
    </div>
@endsection
