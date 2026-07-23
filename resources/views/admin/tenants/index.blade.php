@extends('layouts.admin')

@section('title', 'Tenants')

@section('content')
    <div class="mb-6">
        <p class="text-slate-500 dark:text-slate-400 text-sm">View and manage all registered tenants</p>
    </div>

    <!-- Desktop Table View (640px and above: sm:block) -->
    <div class="hidden sm:block bg-white shadow overflow-hidden rounded-lg">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Users</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($tenants as $tenant)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">{{ $tenant->name }}</div>
                            <div class="text-sm text-gray-500">{{ $tenant->slug }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $tenant->license?->plan?->name ?? 'No license' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $tenant->users_count }} / {{ $tenant->license?->seats ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($tenant->isSuspended())
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Suspended</span>
                            @elseif($tenant->is_active)
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            @localdt($tenant->created_at, 'M d, Y')
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                            <a href="{{ route('admin.tenants.show', $tenant) }}" class="text-indigo-600 hover:text-indigo-900">View</a>
                            @if($tenant->isSuspended())
                                <form action="{{ route('admin.tenants.unsuspend', $tenant) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-green-600 hover:text-green-900">Unsuspend</button>
                                </form>
                            @else
                                <form action="{{ route('admin.tenants.suspend', $tenant) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to suspend this tenant?')">
                                    @csrf
                                    <button type="submit" class="text-red-600 hover:text-red-900">Suspend</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No tenants found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Mobile SaaS Container View (Below 640px: block sm:hidden) -->
    <div class="block sm:hidden" x-data="{ search: '', filter: 'all' }">
        <!-- Main Single Container -->
        <div class="bg-white dark:bg-[var(--bg-card)] rounded-3xl border border-slate-100 dark:border-[var(--border-soft)] shadow-xl overflow-hidden">
            
            <!-- Search & Filters Header Section -->
            <div class="p-4 space-y-3 bg-slate-50/50 dark:bg-[var(--bg-header)]">
                <!-- Search Input -->
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input type="text" 
                           x-model="search" 
                           placeholder="Search tenants..." 
                           class="w-full pl-10 pr-4 py-2.5 bg-white dark:bg-[var(--bg-app)] border border-slate-200 dark:border-[var(--border-soft)] rounded-xl text-sm text-slate-900 dark:text-[var(--text-primary)] placeholder-slate-400 focus:outline-none focus:border-indigo-500 transition duration-150">
                </div>

                <!-- Status Filter Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1">
                    <button type="button" 
                            @click="filter = 'all'" 
                            :class="filter === 'all' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-[var(--bg-app)] text-slate-600 dark:text-[var(--text-secondary)] border border-slate-200 dark:border-[var(--border-soft)]'" 
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition duration-150 whitespace-nowrap">
                        All
                    </button>
                    <button type="button" 
                            @click="filter = 'active'" 
                            :class="filter === 'active' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-[var(--bg-app)] text-slate-600 dark:text-[var(--text-secondary)] border border-slate-200 dark:border-[var(--border-soft)]'" 
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition duration-150 whitespace-nowrap">
                        Active
                    </button>
                    <button type="button" 
                            @click="filter = 'suspended'" 
                            :class="filter === 'suspended' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-[var(--bg-app)] text-slate-600 dark:text-[var(--text-secondary)] border border-slate-200 dark:border-[var(--border-soft)]'" 
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition duration-150 whitespace-nowrap">
                        Suspended
                    </button>
                    <button type="button" 
                            @click="filter = 'inactive'" 
                            :class="filter === 'inactive' ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-[var(--bg-app)] text-slate-600 dark:text-[var(--text-secondary)] border border-slate-200 dark:border-[var(--border-soft)]'" 
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition duration-150 whitespace-nowrap">
                        Inactive
                    </button>
                </div>
            </div>

            <!-- Divider Line -->
            <div class="border-b border-slate-100 dark:border-[var(--border-soft)]"></div>

            <!-- Tenant Cards List or Empty State Inside Container -->
            <div class="p-4 space-y-4">
                @if($tenants->isNotEmpty())
                    @foreach($tenants as $tenant)
                        @php
                            $statusType = $tenant->isSuspended() ? 'suspended' : ($tenant->is_active ? 'active' : 'inactive');
                        @endphp
                        <div x-show="(search === '' || '{{ strtolower(addslashes($tenant->name)) }}'.includes(search.toLowerCase()) || '{{ strtolower(addslashes($tenant->slug)) }}'.includes(search.toLowerCase())) && (filter === 'all' || filter === '{{ $statusType }}')"
                             class="rounded-2xl border border-slate-100 dark:border-[var(--border-soft)] bg-white dark:bg-[var(--bg-app)] shadow-sm p-5 space-y-4">
                            
                            <!-- Tenant Name -->
                            <div>
                                <h3 class="text-lg font-bold text-slate-900 dark:text-[var(--text-primary)] tracking-tight">{{ $tenant->name }}</h3>
                                <p class="text-xs text-slate-400 dark:text-slate-500 font-mono mt-0.5">{{ $tenant->slug }}</p>
                            </div>

                            <!-- Information Fields Grid -->
                            <div class="grid grid-cols-2 gap-4 pt-3 border-t border-slate-100 dark:border-[var(--border-soft)]">
                                <div>
                                    <span class="block uppercase tracking-wider text-xs text-slate-400 dark:text-slate-500 font-semibold">PLAN</span>
                                    <span class="text-base font-semibold text-slate-900 dark:text-[var(--text-primary)] mt-0.5 block">{{ $tenant->license?->plan?->name ?? 'No license' }}</span>
                                </div>
                                <div>
                                    <span class="block uppercase tracking-wider text-xs text-slate-400 dark:text-slate-500 font-semibold">USERS</span>
                                    <span class="text-base font-semibold text-slate-900 dark:text-[var(--text-primary)] mt-0.5 block">{{ $tenant->users_count }} / {{ $tenant->license?->seats ?? '-' }}</span>
                                </div>
                                <div>
                                    <span class="block uppercase tracking-wider text-xs text-slate-400 dark:text-slate-500 font-semibold">STATUS</span>
                                    <div class="mt-1">
                                        @if($tenant->isSuspended())
                                            <span class="px-2.5 py-0.5 inline-flex items-center gap-1 text-xs leading-5 font-semibold rounded-full bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-400"><span class="w-1.5 h-1.5 rounded-full bg-rose-600"></span> Suspended</span>
                                        @elseif($tenant->is_active)
                                            <span class="px-2.5 py-0.5 inline-flex items-center gap-1 text-xs leading-5 font-semibold rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400"><span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span> Active</span>
                                        @else
                                            <span class="px-2.5 py-0.5 inline-flex items-center gap-1 text-xs leading-5 font-semibold rounded-full bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300"><span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span> Inactive</span>
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <span class="block uppercase tracking-wider text-xs text-slate-400 dark:text-slate-500 font-semibold">CREATED</span>
                                    <span class="text-base font-semibold text-slate-900 dark:text-[var(--text-primary)] mt-0.5 block">@localdt($tenant->created_at, 'M d, Y')</span>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="pt-3 border-t border-slate-100 dark:border-[var(--border-soft)]">
                                <div class="grid grid-cols-2 gap-3">
                                    <a href="{{ route('admin.tenants.show', $tenant) }}" class="inline-flex items-center justify-center h-10 px-4 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition duration-150 shadow-xs">
                                        View
                                    </a>
                                    @if($tenant->isSuspended())
                                        <form action="{{ route('admin.tenants.unsuspend', $tenant) }}" method="POST" class="w-full">
                                            @csrf
                                            <button type="submit" class="w-full inline-flex items-center justify-center h-10 px-4 border border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 text-xs font-semibold rounded-xl transition duration-150">
                                                Unsuspend
                                            </button>
                                        </form>
                                    @else
                                        <form action="{{ route('admin.tenants.suspend', $tenant) }}" method="POST" class="w-full" onsubmit="return confirm('Are you sure you want to suspend this tenant?')">
                                            @csrf
                                            <button type="submit" class="w-full inline-flex items-center justify-center h-10 px-4 border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 text-xs font-semibold rounded-xl transition duration-150">
                                                Suspend
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <!-- Empty State Inside Container -->
                    <div class="py-16 px-6 flex flex-col items-center justify-center text-center space-y-3">
                        <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center text-xl">
                            📁
                        </div>
                        <h4 class="text-base font-semibold text-slate-900 dark:text-[var(--text-primary)]">No tenants found</h4>
                        <p class="text-xs text-slate-500 dark:text-[var(--text-secondary)]">Create your first tenant to get started.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Mobile Footer Outside Container -->
        <div class="mt-4 text-center space-y-2">
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Showing {{ $tenants->firstItem() ?? 0 }} to {{ $tenants->lastItem() ?? 0 }} of {{ $tenants->total() }} tenants
            </p>
        </div>
    </div>

    <!-- Pagination links for Desktop & Mobile -->
    <div class="mt-6">
        {{ $tenants->links() }}
    </div>
@endsection
