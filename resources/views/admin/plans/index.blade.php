@extends('layouts.admin')

@section('title', 'Plans')

@section('content')
    <div class="mb-6">
        <p class="text-slate-500 dark:text-slate-400 text-sm">Manage subscription plans</p>
    </div>

    <!-- Desktop View (640px and above: hidden sm:block) -->
    <div class="hidden sm:block" x-data="{ search: '', filter: 'all' }">
        <div class="bg-white dark:bg-[var(--bg-card)] rounded-3xl border border-slate-100 dark:border-[var(--border-soft)] shadow-xl overflow-hidden">
            
            <!-- Toolbar: Search, Filter, Create Plan -->
            <div class="p-5 flex flex-col md:flex-row items-center justify-between gap-4 bg-slate-50/50 dark:bg-[var(--bg-header)] border-b border-slate-100 dark:border-[var(--border-soft)]">
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <!-- Search Input -->
                    <x-search-input 
                        model="search" 
                        placeholder="Search plans..." 
                        wrapperClass="w-full md:w-72" 
                    />

                    <!-- Filter Dropdown -->
                    <select x-model="filter" class="py-2 px-3 bg-white dark:bg-[var(--bg-app)] border border-slate-200 dark:border-[var(--border-soft)] rounded-xl text-xs font-semibold text-slate-700 dark:text-[var(--text-primary)] focus:outline-none focus:border-indigo-500">
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <!-- Create Plan Button -->
                @if(Route::has('admin.plans.create'))
                    <a href="{{ route('admin.plans.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold transition duration-150 shadow-xs">
                        Create Plan
                    </a>
                @endif
            </div>

            <!-- Table -->
            @if($plans->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 dark:divide-[var(--border-soft)]">
                        <thead class="bg-slate-50/80 dark:bg-[var(--bg-header)]">
                            <tr>
                                <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-[var(--text-secondary)] uppercase tracking-wider">PLAN</th>
                                <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-[var(--text-secondary)] uppercase tracking-wider">DESCRIPTION</th>
                                <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-[var(--text-secondary)] uppercase tracking-wider">USERS</th>
                                <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-[var(--text-secondary)] uppercase tracking-wider">TICKETS/MONTH</th>
                                <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-[var(--text-secondary)] uppercase tracking-wider">LICENSES</th>
                                <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 dark:text-[var(--text-secondary)] uppercase tracking-wider">STATUS</th>
                                <th class="px-6 py-3.5 text-right text-xs font-semibold text-slate-500 dark:text-[var(--text-secondary)] uppercase tracking-wider">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-[var(--border-soft)] bg-white dark:bg-[var(--bg-card)]">
                            @foreach($plans as $plan)
                                @php
                                    $statusType = $plan->is_active ? 'active' : 'inactive';
                                @endphp
                                <tr x-show="(search === '' || '{{ strtolower(addslashes($plan->name)) }}'.includes(search.toLowerCase()) || '{{ strtolower(addslashes($plan->description ?? '')) }}'.includes(search.toLowerCase())) && (filter === 'all' || filter === '{{ $statusType }}')"
                                    class="hover:bg-slate-50/60 dark:hover:bg-[var(--bg-hover)] transition duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-bold text-slate-900 dark:text-[var(--text-primary)]">{{ $plan->name }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-500 dark:text-[var(--text-secondary)] max-w-xs truncate">
                                        {{ $plan->description ?? 'No description' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-slate-900 dark:text-[var(--text-primary)]">
                                        {{ $plan->max_users ?? 'Unlimited' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-slate-900 dark:text-[var(--text-primary)]">
                                        {{ $plan->max_tickets_per_month ?? 'Unlimited' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-indigo-600 dark:text-indigo-400">
                                        {{ $plan->licenses_count }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($plan->is_active)
                                            <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                        @else
                                            <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="{{ route('admin.plans.edit', $plan) }}" class="inline-flex items-center justify-center px-3.5 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-semibold hover:bg-indigo-700 transition duration-150">
                                            Edit
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <!-- Desktop Empty State -->
                <div class="py-16 px-6 flex flex-col items-center justify-center text-center space-y-3">
                    <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center text-xl">📦</div>
                    <h4 class="text-base font-semibold text-slate-900 dark:text-[var(--text-primary)]">No plans found</h4>
                    <p class="text-xs text-slate-500 dark:text-[var(--text-secondary)]">Create your first subscription plan.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Mobile View (Below 640px: block sm:hidden) -->
    <div class="block sm:hidden space-y-6">
        <!-- Mobile Create Plan Button -->
        @if(Route::has('admin.plans.create'))
            <a href="{{ route('admin.plans.create') }}" class="w-full h-11 inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs rounded-xl shadow-xs transition duration-150">
                + Create Plan
            </a>
        @endif

        <!-- Mobile Plan Cards Loop -->
        @forelse($plans as $plan)
            <div class="bg-white dark:bg-[var(--bg-card)] rounded-3xl border border-slate-100 dark:border-[var(--border-soft)] shadow-lg p-6 space-y-5 w-full">
                
                <!-- Card Header: Plan Name & Status Badge -->
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-[var(--text-primary)] tracking-tight">{{ $plan->name }}</h3>
                    @if($plan->is_active)
                        <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                    @else
                        <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                    @endif
                </div>

                <!-- Description (hidden if null/empty) -->
                @if(!empty($plan->description))
                    <div>
                        <span class="block uppercase tracking-wider text-xs font-semibold text-slate-400 dark:text-slate-500">DESCRIPTION</span>
                        <p class="text-sm font-medium text-slate-700 dark:text-[var(--text-primary)] mt-0.5 leading-relaxed">{{ $plan->description }}</p>
                    </div>
                @endif

                <!-- MAX USERS -->
                <div>
                    <span class="block uppercase tracking-wider text-xs font-semibold text-slate-400 dark:text-slate-500">MAX USERS</span>
                    <span class="text-base font-semibold text-slate-900 dark:text-[var(--text-primary)] mt-0.5 block">{{ $plan->max_users ?? 'Unlimited' }}</span>
                </div>

                <!-- MONTHLY TICKETS -->
                <div>
                    <span class="block uppercase tracking-wider text-xs font-semibold text-slate-400 dark:text-slate-500">MONTHLY TICKETS</span>
                    <span class="text-base font-semibold text-slate-900 dark:text-[var(--text-primary)] mt-0.5 block">{{ $plan->max_tickets_per_month ?? 'Unlimited' }}</span>
                </div>

                <!-- LICENSES -->
                <div>
                    <span class="block uppercase tracking-wider text-xs font-semibold text-slate-400 dark:text-slate-500">LICENSES</span>
                    <span class="text-base font-semibold text-slate-900 dark:text-[var(--text-primary)] mt-0.5 block">{{ $plan->licenses_count }}</span>
                </div>

                <!-- FEATURES -->
                <div>
                    <span class="block uppercase tracking-wider text-xs font-semibold text-slate-400 dark:text-slate-500 mb-2">FEATURES</span>
                    @if(!empty($plan->features) && is_array($plan->features))
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($plan->features as $feature)
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 dark:bg-slate-800 px-3 py-1 text-xs font-medium text-slate-700 dark:text-slate-300">
                                    ✓ {{ is_string($feature) ? Str::title(str_replace('_', ' ', $feature)) : $feature }}
                                </span>
                            @endforeach
                        </div>
                    @else
                        <span class="text-xs text-slate-400 dark:text-slate-500 italic">No features configured</span>
                    @endif
                </div>

                <!-- Edit Plan Button -->
                <div class="pt-2 border-t border-slate-100 dark:border-[var(--border-soft)]">
                    <a href="{{ route('admin.plans.edit', $plan) }}" class="w-full h-11 inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold transition duration-150 shadow-xs">
                        Edit Plan
                    </a>
                </div>
            </div>
        @empty
            <!-- Mobile Empty State Card -->
            <div class="bg-white dark:bg-[var(--bg-card)] rounded-3xl border border-slate-100 dark:border-[var(--border-soft)] p-8 shadow-lg text-center flex flex-col items-center justify-center py-16 space-y-3">
                <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center text-xl mb-1">
                    📦
                </div>
                <h4 class="text-base font-semibold text-slate-900 dark:text-[var(--text-primary)]">No plans found</h4>
                <p class="text-xs text-slate-500 dark:text-[var(--text-secondary)] mb-2">There are no subscription plans yet.</p>
                @if(Route::has('admin.plans.create'))
                    <a href="{{ route('admin.plans.create') }}" class="inline-flex items-center justify-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition duration-150">
                        + Create Plan
                    </a>
                @endif
            </div>
        @endforelse
    </div>
@endsection
