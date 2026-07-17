@extends('layouts.admin')

@section('title', 'Notification Center')

@section('content')
<style>
    .notify-container {
        font-family: 'Inter', sans-serif !important;
    }
    
    .stat-card {
        background-color: var(--bg-card) !important;
        border: 1px solid var(--border-soft) !important;
        border-radius: 16px !important;
        padding: 20px !important;
        box-shadow: var(--shadow) !important;
        transition: all 0.2s ease !important;
    }
    .stat-card:hover {
        transform: translateY(-2px) !important;
    }

    .filter-chip {
        padding: 6px 14px !important;
        border-radius: 9999px !important;
        font-size: 13px !important;
        font-weight: 600 !important;
        transition: all 0.2s ease !important;
        background-color: var(--bg-card) !important;
        border: 1px solid var(--border-soft) !important;
        color: var(--text-secondary) !important;
    }
    .filter-chip:hover {
        background-color: var(--bg-hover) !important;
        color: var(--text-primary) !important;
    }
    .filter-chip.active {
        background-color: var(--bg-active) !important;
        color: var(--primary) !important;
        border-color: var(--primary) !important;
    }

    .notify-card {
        background-color: var(--bg-card) !important;
        border: 1px solid var(--border-soft) !important;
        border-radius: 16px !important;
        padding: 20px !important;
        box-shadow: var(--shadow) !important;
        transition: all 0.2s ease !important;
        border-left-width: 4px !important;
    }
    .notify-card:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.04) !important;
    }

    .btn-action {
        height: 38px !important;
        padding: 0 16px !important;
        border-radius: 12px !important;
        font-size: 13px !important;
        font-weight: 600 !important;
        transition: all 0.2s ease !important;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .search-input-notify {
        background-color: var(--bg-card) !important;
        color: var(--text-primary) !important;
        border: 1px solid var(--border-soft) !important;
        border-radius: 12px !important;
        height: 44px !important;
        font-size: 14px !important;
        outline: none;
    }
    .search-input-notify:focus {
        border-color: var(--primary) !important;
        box-shadow: 0 0 0 3px rgba(91, 95, 246, 0.15) !important;
    }
</style>

<div class="notify-container flex flex-col gap-8 pb-20" x-data="{
    notifications: [],
    loading: false,
    query: '',
    activeTab: 'All',
    selectedDateFilter: 'All',
    selectedSeverityFilter: 'All',
    selectedSort: 'newest',
    selectedIds: [],
    
    getIcon(n) {
        const action = n.action;
        const type = n.type;
        const isUnread = !n.read_at;
        let color = 'text-blue-500 bg-blue-500/10';
        
        if (!isUnread) {
            color = 'text-slate-400 bg-slate-100 dark:bg-slate-800 dark:text-slate-500';
        } else {
            if (action === 'system_announcement') {
                const severity = n.severity || 'info';
                if (severity === 'info') color = 'text-blue-500 bg-blue-500/10';
                else if (severity === 'success') color = 'text-green-500 bg-green-500/10';
                else if (severity === 'warning') color = 'text-orange-500 bg-orange-500/10';
                else if (severity === 'danger') color = 'text-red-500 bg-red-500/10';
            } else if (action === 'created') {
                color = 'text-blue-500 bg-blue-500/10';
            } else if (action === 'assigned') {
                color = 'text-purple-500 bg-purple-500/10';
            } else if (action === 'status_changed') {
                color = 'text-green-500 bg-green-500/10';
            } else if (action === 'sla_breach_warning') {
                color = 'text-orange-500 bg-orange-500/10';
            } else if (action === 'sla_breach') {
                color = 'text-red-500 bg-red-500/10';
            } else if (action === 'escalated') {
                color = 'text-red-500 bg-red-500/10';
            }
        }

        const ticket = `<svg class='h-5 w-5 ${color}' fill='none' viewBox='0 0 24 24' stroke='currentColor' stroke-width='2.5'><path stroke-linecap='round' stroke-linejoin='round' d='M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-12v.75m0 3v.75m0 3v.75m0 3V18M3 6h18v12H3V6z' /></svg>`;
        const announcement = `<svg class='h-5 w-5 ${color}' fill='none' viewBox='0 0 24 24' stroke='currentColor' stroke-width='2.5'><path stroke-linecap='round' stroke-linejoin='round' d='m3 11 18-5v12L3 13v-2z'/><path stroke-linecap='round' stroke-linejoin='round' d='M11.6 16.8a3 3 0 1 1-5.8-1.6'/></svg>`;
        const clock = `<svg class='h-5 w-5 ${color}' fill='none' viewBox='0 0 24 24' stroke='currentColor' stroke-width='2.5'><circle cx='12' cy='12' r='10'/><polyline points='12 6 12 12 16 14'/></svg>`;
        const shield = `<svg class='h-5 w-5 ${color}' fill='none' viewBox='0 0 24 24' stroke='currentColor' stroke-width='2.5'><path stroke-linecap='round' stroke-linejoin='round' d='M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z' /></svg>`;
        const warning = `<svg class='h-5 w-5 ${color}' fill='none' viewBox='0 0 24 24' stroke='currentColor' stroke-width='2.5'><path stroke-linecap='round' stroke-linejoin='round' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z' /></svg>`;

        if (action === 'system_announcement') return announcement;
        if (action === 'sla_breach_warning' || action === 'sla_breach') return clock;
        if (action === 'assigned') return shield;
        if (action === 'escalated') return warning;
        return ticket;
    },

    fetchNotifications() {
        this.loading = true;
        fetch('{{ route('admin.notifications.recent') }}')
            .then(res => res.json())
            .then(data => {
                this.notifications = data;
                this.loading = false;
            })
            .catch(() => { this.loading = false; });
    },

    markAllRead() {
        fetch('{{ route('admin.notifications.markAllRead') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        })
        .then(res => res.json())
        .then(() => {
            this.notifications.forEach(n => n.read_at = new Date().toISOString());
            window.dispatchEvent(new CustomEvent('notifications-updated'));
            window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'All notifications marked as read', type: 'success' } }));
        });
    },

    markAsRead(n) {
        if (n.read_at) return;
        fetch('/admin/notifications/' + n.id + '/read', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        })
        .then(res => res.json())
        .then(() => {
            n.read_at = new Date().toISOString();
            window.dispatchEvent(new CustomEvent('notifications-updated'));
            window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Notification marked as read', type: 'success' } }));
        });
    },

    markSelectedRead() {
        let promises = this.selectedIds.map(id => {
            return fetch('/admin/notifications/' + id + '/read', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                }
            });
        });
        Promise.all(promises).then(() => {
            this.notifications.forEach(n => {
                if (this.selectedIds.includes(n.id)) n.read_at = new Date().toISOString();
            });
            this.selectedIds = [];
            window.dispatchEvent(new CustomEvent('notifications-updated'));
            window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Selected notifications marked as read', type: 'success' } }));
        });
    },

    deleteSelected() {
        // UI-only delete
        this.notifications = this.notifications.filter(n => !this.selectedIds.includes(n.id));
        this.selectedIds = [];
        window.dispatchEvent(new CustomEvent('notifications-updated'));
        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Selected notifications removed from view', type: 'success' } }));
    },

    toggleSelectAll() {
        if (this.selectedIds.length === this.filteredNotifications.length) {
            this.selectedIds = [];
        } else {
            this.selectedIds = this.filteredNotifications.map(n => n.id);
        }
    },

    // Filter computation
    get filteredNotifications() {
        let list = this.notifications.filter(n => {
            // Category/Tabs Filter
            if (this.activeTab === 'Unread' && n.read_at) return false;
            if (this.activeTab === 'Read' && !n.read_at) return false;
            if (this.activeTab === 'Announcements' && n.action !== 'system_announcement') return false;
            if (this.activeTab === 'Tickets' && n.action === 'system_announcement') return false;
            if (this.activeTab === 'SLA' && n.action !== 'sla_breach_warning' && n.action !== 'sla_breach') return false;
            if (this.activeTab === 'AI Bugs' && n.action !== 'assigned') return false; // Match mapped roles

            // Search Filter
            if (this.query) {
                const q = this.query.toLowerCase();
                const titleMatch = n.title.toLowerCase().includes(q);
                const descMatch = n.subject.toLowerCase().includes(q);
                const numMatch = n.ticket_number && n.ticket_number.toString().includes(q);
                if (!titleMatch && !descMatch && !numMatch) return false;
            }

            // Date Filters
            if (this.selectedDateFilter !== 'All') {
                const ago = n.created_ago.toLowerCase();
                if (this.selectedDateFilter === 'Today') {
                    if (ago.includes('day') || ago.includes('week') || ago.includes('month') || ago.includes('year')) return false;
                } else if (this.selectedDateFilter === 'Yesterday') {
                    if (!ago.includes('1 day ago') && !ago.includes('yesterday')) return false;
                } else if (this.selectedDateFilter === 'Last 7 Days') {
                    // Filter matches days but excludes weeks, months, years
                    if (ago.includes('week') || ago.includes('month') || ago.includes('year')) return false;
                }
            }

            // Severity/Type Color Filter
            if (this.selectedSeverityFilter !== 'All') {
                const action = n.action;
                if (this.selectedSeverityFilter === 'Critical' && action !== 'sla_breach' && action !== 'escalated') return false;
                if (this.selectedSeverityFilter === 'Warning' && action !== 'sla_breach_warning') return false;
                if (this.selectedSeverityFilter === 'Success' && action !== 'status_changed') return false;
                if (this.selectedSeverityFilter === 'Information' && action !== 'created' && action !== 'assigned') return false;
            }

            return true;
        });

        // Sorting
        if (this.selectedSort === 'newest') {
            // Standard order as fetched
        } else if (this.selectedSort === 'oldest') {
            list = [...list].reverse();
        }

        return list;
    },

    // Statistics computation
    get unreadCount() {
        return this.notifications.filter(n => !n.read_at).length;
    },
    get todayCount() {
        return this.notifications.filter(n => !n.created_ago.includes('day') && !n.created_ago.includes('week') && !n.created_ago.includes('month') && !n.created_ago.includes('year')).length;
    },
    get thisWeekCount() {
        return this.notifications.filter(n => !n.created_ago.includes('week') && !n.created_ago.includes('month') && !n.created_ago.includes('year')).length;
    },
    get criticalCount() {
        return this.notifications.filter(n => n.action === 'sla_breach' || n.action === 'escalated').length;
    },

    init() {
        this.fetchNotifications();
        setInterval(() => { this.fetchNotifications(); }, 30000);
        window.addEventListener('notifications-updated', () => {
            this.fetchNotifications();
        });
    }
}">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-[var(--border-soft)]">
        <div>
            <h1 class="page-title text-[var(--text-primary)]">Notifications</h1>
            <p class="mt-2 text-sm text-[var(--text-secondary)]">
                Manage your system communications, ticket assignments, alerts, and broadcasts.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <button @click="fetchNotifications()" class="btn-action bg-[var(--bg-card)] border border-[var(--border-soft)] text-[var(--text-primary)] hover:bg-[var(--bg-hover)] cursor-pointer">
                <svg xmlns="http://www.w3.org/255/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-refresh-cw text-[var(--text-secondary)]"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>
                <span>Refresh</span>
            </button>
            <button @click="markAllRead()" class="btn-action bg-[#5B5FF6] text-white hover:bg-[#4752C4] shadow-sm cursor-pointer border-none">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check-check text-white"><path d="M4 14 10 20 22 8"/><path d="M9 20 20 8"/></svg>
                <span>Mark All Read</span>
            </button>
        </div>
    </div>

    <!-- Statistics Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="stat-card flex flex-col justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">Unread</span>
            <span class="text-3xl font-extrabold text-[var(--text-primary)] mt-2" x-text="unreadCount">0</span>
        </div>
        <div class="stat-card flex flex-col justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">Today</span>
            <span class="text-3xl font-extrabold text-[var(--text-primary)] mt-2" x-text="todayCount">0</span>
        </div>
        <div class="stat-card flex flex-col justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">This Week</span>
            <span class="text-3xl font-extrabold text-[var(--text-primary)] mt-2" x-text="thisWeekCount">0</span>
        </div>
        <div class="stat-card flex flex-col justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">Critical</span>
            <span class="text-3xl font-extrabold text-rose-600 dark:text-rose-450 mt-2" x-text="criticalCount">0</span>
        </div>
    </div>

    <!-- Search, Filter & Bulk Controls layout block -->
    <div class="flex flex-col gap-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <!-- Search bar -->
            <div class="relative w-full max-w-[420px]">
                <div class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 flex items-center justify-center">
                    <svg class="h-[18px] w-[18px] text-[var(--text-secondary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="text" x-model="query" placeholder="Search notifications..." class="w-full search-input-notify pl-11 pr-4">
            </div>

            <!-- Custom Filters and sorting Dropdowns -->
            <div class="flex flex-wrap items-center gap-3">
                <!-- Date Filter -->
                <select x-model="selectedDateFilter" class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-300 px-3 py-2 cursor-pointer focus:outline-none focus:border-indigo-500">
                    <option value="All">All Dates</option>
                    <option value="Today">Today</option>
                    <option value="Yesterday">Yesterday</option>
                    <option value="Last 7 Days">Last 7 Days</option>
                </select>

                <!-- Severity Filter -->
                <select x-model="selectedSeverityFilter" class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-300 px-3 py-2 cursor-pointer focus:outline-none focus:border-indigo-500">
                    <option value="All">All Severities</option>
                    <option value="Critical">Critical</option>
                    <option value="Warning">Warning</option>
                    <option value="Success">Success</option>
                    <option value="Information">Information</option>
                </select>

                <!-- Sorting -->
                <select x-model="selectedSort" class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-300 px-3 py-2 cursor-pointer focus:outline-none focus:border-indigo-500">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                </select>
            </div>
        </div>

        <!-- Horizontal Tabs selectors -->
        <div class="flex items-center gap-2.5 overflow-x-auto pb-2 scrollbar-none border-b border-[var(--border-soft)]">
            @foreach(['All', 'Unread', 'Read', 'Announcements', 'Tickets', 'SLA', 'AI Bugs'] as $tab)
                <button type="button" 
                        @click="activeTab = '{{ $tab }}'"
                        :class="activeTab === '{{ $tab }}' ? 'active' : ''"
                        class="filter-chip shrink-0 cursor-pointer">
                    {{ $tab }}
                </button>
            @endforeach
        </div>

        <!-- Bulk Action Panel -->
        <div class="flex items-center justify-between p-3.5 bg-[var(--bg-card)] border border-[var(--border-soft)] rounded-xl" x-show="filteredNotifications.length > 0">
            <div class="flex items-center gap-3">
                <input type="checkbox" 
                       @click="toggleSelectAll()" 
                       :checked="selectedIds.length === filteredNotifications.length && filteredNotifications.length > 0"
                       class="rounded border-gray-300 dark:border-gray-600 text-indigo-650 focus:ring-indigo-500 cursor-pointer">
                <span class="text-xs font-semibold text-[var(--text-secondary)]" x-text="selectedIds.length + ' selected'"></span>
            </div>
            
            <div class="flex items-center gap-3" x-show="selectedIds.length > 0">
                <button @click="markSelectedRead()" class="btn-action bg-[var(--bg-hover)] text-xs font-bold text-[var(--text-primary)] hover:bg-[var(--bg-active)] border border-[var(--border-soft)] cursor-pointer">
                    Mark Read
                </button>
                <button @click="deleteSelected()" class="btn-action bg-rose-50/50 hover:bg-rose-100/50 dark:bg-rose-950/20 dark:hover:bg-rose-900/30 text-rose-600 dark:text-rose-400 text-xs font-bold border border-rose-200 dark:border-rose-900/30 cursor-pointer">
                    Delete Selected
                </button>
            </div>
        </div>
    </div>

    <!-- Notifications List Section -->
    <div class="flex flex-col gap-4">
        <!-- Skeleton loaders when updating list -->
        <div x-show="loading" class="space-y-4">
            <div class="h-20 bg-[var(--bg-card)] border border-[var(--border-soft)] animate-pulse rounded-2xl"></div>
            <div class="h-20 bg-[var(--bg-card)] border border-[var(--border-soft)] animate-pulse rounded-2xl"></div>
            <div class="h-20 bg-[var(--bg-card)] border border-[var(--border-soft)] animate-pulse rounded-2xl"></div>
        </div>

        <!-- Premium Empty State -->
        <div x-show="!loading && filteredNotifications.length === 0" class="flex flex-col items-center justify-center py-24 px-4 bg-[var(--bg-card)] rounded-[20px] border border-[var(--border-soft)] shadow-sm text-center">
            <div class="h-16 w-16 bg-[var(--bg-hover)] rounded-full flex items-center justify-center text-[var(--text-secondary)] mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bell"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
            </div>
            <h3 class="text-base font-extrabold text-[var(--text-primary)] mb-1">You're all caught up!</h3>
            <p class="text-xs text-[var(--text-secondary)] max-w-[280px]">There are currently no notifications matching this criteria.</p>
        </div>

        <!-- Render Loop of Notification Cards -->
        <template x-show="!loading" x-for="n in filteredNotifications" :key="n.id">
            <div @click="markAsRead(n)" 
                 class="notify-card flex flex-col md:flex-row md:items-center justify-between gap-4 cursor-pointer"
                 :class="!n.read_at ? 'bg-indigo-50/5 dark:bg-indigo-500/5' : ''"
                 :style="!n.read_at ? 'border-left-color: #5B5FF6 !important;' : 'border-left-color: transparent !important;'">
                
                <div class="flex items-start gap-4">
                    <!-- Bulk Checkbox -->
                    <div class="pt-1.5" @click.stop>
                        <input type="checkbox" 
                               :value="n.id" 
                               x-model="selectedIds"
                               class="rounded border-gray-300 dark:border-gray-600 text-indigo-650 focus:ring-indigo-500 cursor-pointer">
                    </div>

                    <!-- Type Icon Indicator Box -->
                    <div class="h-10 w-10 rounded-xl flex items-center justify-center shrink-0" x-html="getIcon(n)"></div>

                    <!-- Details -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2.5">
                            <!-- Unread pulse dot -->
                            <template x-if="!n.read_at">
                                <span class="h-2 w-2 rounded-full bg-indigo-600 shrink-0"></span>
                            </template>
                            <h3 class="text-sm font-extrabold text-[var(--text-primary)]" :class="!n.read_at ? 'font-bold' : 'font-normal text-[var(--text-secondary)]'" x-text="n.title"></h3>
                        </div>
                        <p class="text-xs text-[var(--text-secondary)] mt-1.5 font-normal leading-relaxed" x-text="n.subject"></p>
                    </div>
                </div>

                <!-- Right Side Actions & Time -->
                <div class="flex md:flex-col items-end gap-3 justify-between md:justify-start shrink-0 pl-8 md:pl-0">
                    <span class="text-[11px] text-[var(--text-secondary)] font-semibold" :title="n.created_at || ''" x-text="n.created_ago"></span>
                    
                    <div class="flex items-center gap-2">
                        <!-- Redirect Open Link -->
                        <template x-if="n.url">
                            <a :href="n.url" class="btn-action bg-[var(--bg-hover)] text-[var(--text-primary)] hover:bg-[var(--bg-active)] hover:text-[var(--primary)] border border-[var(--border-soft)]" @click.stop="markAsRead(n)">
                                <span>Open Ticket</span>
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                            </a>
                        </template>

                        <!-- Individual Mark Read -->
                        <template x-if="!n.read_at">
                            <button @click.stop="markAsRead(n)" class="btn-action bg-transparent border border-[var(--border-soft)] text-[var(--text-primary)] hover:bg-[var(--bg-hover)] cursor-pointer">
                                <span>Mark Read</span>
                            </button>
                        </template>
                    </div>
                </div>

            </div>
        </template>
    </div>
</div>
@endsection
