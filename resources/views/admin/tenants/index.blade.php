@extends('layouts.admin')

@section('title', 'Tenants')

@section('content')
<style>
    .tenant-btn-action {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        height: 40px !important;
        border-radius: 12px !important;
        font-size: 14px !important;
        font-weight: 500 !important;
        color: #ffffff !important;
        border: none !important;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
        transition: all 0.2s ease !important;
        opacity: 1 !important;
        visibility: visible !important;
        text-decoration: none !important;
        cursor: pointer !important;
    }
    .tenant-btn-action span {
        color: #ffffff !important;
    }

    /* View: Neutral/dark button */
    .tenant-btn-view {
        background-color: #1e293b !important; /* slate-800 */
    }
    .tenant-btn-view:hover {
        background-color: #0f172a !important; /* slate-900 */
    }
    html.dark .tenant-btn-view {
        background-color: #334155 !important; /* slate-700 */
    }
    html.dark .tenant-btn-view:hover {
        background-color: #475569 !important; /* slate-600 */
    }

    /* Edit: Blue/Purple button */
    .tenant-btn-edit {
        background-color: #4f46e5 !important; /* indigo-600 */
    }
    .tenant-btn-edit:hover {
        background-color: #4338ca !important; /* indigo-700 */
    }

    /* Delete: Red button */
    .tenant-btn-delete {
        background-color: #e11d48 !important; /* rose-600 */
    }
    .tenant-btn-delete:hover {
        background-color: #be123c !important; /* rose-700 */
    }
</style>

<div x-data="{ 
    search: '', 
    filter: 'all',
    deleteModalOpen: false,
    deleteTenantName: '',
    deleteFormAction: '',
    relatedRecordsWarning: '',
    confirmDelete(tenant) {
        this.deleteTenantName = tenant.name;
        this.deleteFormAction = tenant.deleteUrl;
        let details = [];
        if (tenant.usersCount > 0) details.push(tenant.usersCount + ' user(s)');
        if (tenant.ticketsCount > 0) details.push(tenant.ticketsCount + ' ticket(s)');
        if (tenant.clientsCount > 0) details.push(tenant.clientsCount + ' client(s)');
        if (tenant.hasLicense) details.push('1 license');
        if (details.length > 0) {
            this.relatedRecordsWarning = 'Warning: This tenant has related records (' + details.join(', ') + '). Deleting this tenant will permanently remove all associated data.';
        } else {
            this.relatedRecordsWarning = '';
        }
        this.deleteModalOpen = true;
    }
}">
    <div class="mb-6">
        <p class="text-slate-500 dark:text-slate-400 text-sm">View and manage all registered tenants</p>
    </div>

    <!-- Desktop Table View (640px and above: sm:block) -->
    <div class="hidden sm:block bg-white dark:bg-[var(--bg-card)] shadow overflow-hidden rounded-lg border border-slate-100 dark:border-[var(--border-soft)]">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-800">
            <thead class="bg-gray-50 dark:bg-slate-800/50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Plan</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Users</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Created</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-[var(--bg-card)] divide-y divide-gray-200 dark:divide-slate-800">
                @forelse($tenants as $tenant)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900 dark:text-slate-100">{{ $tenant->name }}</div>
                            <div class="text-sm text-gray-500 dark:text-slate-400">{{ $tenant->slug }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-slate-400">
                            {{ $tenant->license?->plan?->name ?? 'No license' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-slate-400">
                            {{ $tenant->users_count }} / {{ $tenant->license?->seats ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($tenant->isSuspended())
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-rose-950/40 dark:text-rose-400">Suspended</span>
                            @elseif($tenant->is_active)
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-emerald-950/40 dark:text-emerald-400">Active</span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-slate-800 dark:text-slate-300">Inactive</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-slate-400">
                            @localdt($tenant->created_at, 'M d, Y')
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex items-center gap-2 sm:gap-3">
                                {{-- 👁 View --}}
                                <a href="{{ route('admin.tenants.show', $tenant) }}" 
                                   class="tenant-btn-action tenant-btn-view w-28 gap-1.5" 
                                   title="View Tenant">
                                    <span>👁</span>
                                    <span>View</span>
                                </a>

                                {{-- ✏️ Edit --}}
                                <a href="{{ route('admin.tenants.edit', $tenant) }}" 
                                   class="tenant-btn-action tenant-btn-edit w-28 gap-1.5" 
                                   title="Edit Tenant">
                                    <span>✏️</span>
                                    <span>Edit</span>
                                </a>

                                {{-- 🗑 Delete --}}
                                <button type="button" 
                                        @click="confirmDelete({
                                            id: {{ $tenant->id }},
                                            name: '{{ addslashes($tenant->name) }}',
                                            deleteUrl: '{{ route('admin.tenants.destroy', $tenant) }}',
                                            usersCount: {{ $tenant->users_count ?? 0 }},
                                            ticketsCount: {{ $tenant->tickets_count ?? 0 }},
                                            clientsCount: {{ $tenant->clients_count ?? 0 }},
                                            hasLicense: {{ $tenant->license ? 'true' : 'false' }}
                                        })"
                                        class="tenant-btn-action tenant-btn-delete w-28 gap-1.5" 
                                        title="Delete Tenant">
                                    <span>🗑</span>
                                    <span>Delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500 dark:text-slate-400">No tenants found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Mobile SaaS Container View (Below 640px: block sm:hidden) -->
    <div class="block sm:hidden">
        <!-- Main Single Container -->
        <div class="bg-white dark:bg-[var(--bg-card)] rounded-3xl border border-slate-100 dark:border-[var(--border-soft)] shadow-xl overflow-hidden">
            
            <!-- Search & Filters Header Section -->
            <div class="p-4 space-y-3 bg-slate-50/50 dark:bg-[var(--bg-header)]">
                <!-- Search Input -->
                <x-search-input 
                    model="search" 
                    placeholder="Search tenants..." 
                />

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
                                <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                                    <a href="{{ route('admin.tenants.show', $tenant) }}" 
                                       class="tenant-btn-action tenant-btn-view flex-1 min-w-[100px] gap-1.5">
                                        <span>👁</span>
                                        <span>View</span>
                                    </a>
                                    <a href="{{ route('admin.tenants.edit', $tenant) }}" 
                                       class="tenant-btn-action tenant-btn-edit flex-1 min-w-[100px] gap-1.5">
                                        <span>✏️</span>
                                        <span>Edit</span>
                                    </a>
                                    <button type="button" 
                                            @click="confirmDelete({
                                                id: {{ $tenant->id }},
                                                name: '{{ addslashes($tenant->name) }}',
                                                deleteUrl: '{{ route('admin.tenants.destroy', $tenant) }}',
                                                usersCount: {{ $tenant->users_count ?? 0 }},
                                                ticketsCount: {{ $tenant->tickets_count ?? 0 }},
                                                clientsCount: {{ $tenant->clients_count ?? 0 }},
                                                hasLicense: {{ $tenant->license ? 'true' : 'false' }}
                                            })"
                                            class="tenant-btn-action tenant-btn-delete flex-1 min-w-[100px] gap-1.5">
                                        <span>🗑</span>
                                        <span>Delete</span>
                                    </button>
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

    <!-- Delete Tenant Confirmation Modal -->
    <div x-show="deleteModalOpen" 
         x-cloak 
         class="fixed inset-0 z-50 overflow-y-auto" 
         aria-labelledby="modal-title" 
         role="dialog" 
         aria-modal="true">
        <!-- Backdrop -->
        <div x-show="deleteModalOpen" 
             x-transition:enter="ease-out duration-300" 
             x-transition:enter-start="opacity-0" 
             x-transition:enter-end="opacity-100" 
             x-transition:leave="ease-in duration-200" 
             x-transition:leave-start="opacity-100" 
             x-transition:leave-end="opacity-0" 
             class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-xs transition-opacity" 
             @click="deleteModalOpen = false"></div>

        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div x-show="deleteModalOpen" 
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-[var(--bg-card)] text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-100 dark:border-[var(--border-soft)]">
                
                <form :action="deleteFormAction" method="POST">
                    @csrf
                    @method('DELETE')

                    <div class="p-6 space-y-4">
                        <div class="flex items-center gap-3 text-rose-600 dark:text-rose-400">
                            <div class="w-10 h-10 rounded-full bg-rose-100 dark:bg-rose-950/60 flex items-center justify-center flex-shrink-0 text-xl">
                                ⚠️
                            </div>
                            <h3 class="text-xl font-bold text-slate-900 dark:text-white" id="modal-title">Delete Tenant</h3>
                        </div>

                        <div class="text-sm text-slate-600 dark:text-slate-300 space-y-2">
                            <p class="font-medium text-slate-900 dark:text-slate-100" x-text="'Tenant: ' + deleteTenantName"></p>
                            <p>You are about to permanently delete this tenant.</p>
                            <p class="font-semibold text-rose-600 dark:text-rose-400">This action cannot be undone.</p>

                            <template x-if="relatedRecordsWarning">
                                <div class="mt-3 p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-xl text-amber-800 dark:text-amber-300 text-xs">
                                    <p x-text="relatedRecordsWarning"></p>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-slate-50 dark:bg-[var(--bg-header)] border-t border-slate-100 dark:border-[var(--border-soft)] flex items-center justify-end gap-3">
                        <button type="button" 
                                @click="deleteModalOpen = false" 
                                class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition duration-150">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-semibold shadow-xs transition duration-150">
                            Delete Tenant
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
