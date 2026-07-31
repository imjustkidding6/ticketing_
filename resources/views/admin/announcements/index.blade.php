@extends('layouts.admin')

@section('title', 'System Announcements')

@section('content')
<style>
    .announcement-dashboard {
        font-family: 'Inter', sans-serif !important;
    }
    
    .premium-card {
        background-color: var(--bg-card, #ffffff);
        border: 1px solid var(--border-soft, rgba(0,0,0,0.06));
        border-radius: 16px;
        padding: 24px;
        box-shadow: var(--shadow, 0 1px 3px rgba(0,0,0,0.05));
        transition: all 0.2s ease;
    }
    .premium-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
    }
    html.dark .premium-card {
        background-color: #1f2937;
        border-color: #374151;
    }
    html.dark .premium-card:hover {
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
    }

    .announcement-chip {
        padding: 4px 10px;
        border-radius: 9999px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .chip-info { background-color: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    .chip-update { background-color: rgba(16, 185, 129, 0.1); color: #10b981; }
    .chip-maintenance { background-color: rgba(245, 158, 11, 0.1); color: #f59e0b; }
    .chip-warning { background-color: rgba(239, 68, 68, 0.1); color: #ef4444; }

    .search-input-ann {
        background-color: var(--bg-card, #ffffff);
        border: 1px solid var(--border-soft, rgba(0,0,0,0.06));
        border-radius: 12px;
        height: 44px;
        font-size: 14px;
        padding-left: 44px;
        color: var(--text-primary, #111827);
    }
    .dark .search-input-ann {
        background-color: #1f2937;
        border-color: #374151;
        color: #f9fafb;
    }

    .btn-action-ann {
        height: 36px !important;
        padding: 0 16px !important;
        border-radius: 10px !important;
        font-size: 13px !important;
        font-weight: 600 !important;
        transition: all 0.2s ease !important;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
</style>

<div class="announcement-dashboard flex flex-col gap-8 pb-16" x-data="{
    searchQuery: '',
    selectedSeverity: 'All',
    confirmDeleteId: null,
    previewItem: null
}">
    <!-- Success Flash Alert -->
    @if(session('success'))
        <div class="flex items-center gap-3 p-4 rounded-xl border border-green-200 bg-green-50 dark:bg-green-950/20 dark:border-green-800/30 text-green-800 dark:text-green-400 text-sm shadow-sm transition">
            <svg class="h-5 w-5 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span class="font-semibold">{{ session('success') }}</span>
        </div>
    @endif

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-gray-200 dark:border-gray-800">
        <div>
            <h1 class="page-title text-[var(--text-primary)]">System Announcements</h1>
            <p class="mt-2 text-sm text-[var(--text-secondary)]">
                Broadcast updates, bug fixes, or maintenance notices to every user in every tenant's notification feed.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.announcements.create') }}" class="btn-action-ann bg-[#5B5FF6] text-white hover:bg-[#4752C4] shadow-sm cursor-pointer border-none flex items-center justify-center">
                <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                <span>New Announcement</span>
            </a>
        </div>
    </div>

    <!-- Statistics Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="premium-card flex flex-col justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Broadcasts</span>
            <span class="text-3xl font-extrabold text-gray-900 dark:text-gray-100 mt-2">{{ $announcements->total() }}</span>
        </div>
        <div class="premium-card flex flex-col justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Active Warnings</span>
            <span class="text-3xl font-extrabold text-gray-900 dark:text-gray-100 mt-2">
                {{ \App\Models\SystemAnnouncement::where('severity', 'warning')->count() }}
            </span>
        </div>
        <div class="premium-card flex flex-col justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Maintenance Events</span>
            <span class="text-3xl font-extrabold text-gray-900 dark:text-gray-100 mt-2">
                {{ \App\Models\SystemAnnouncement::where('severity', 'maintenance')->count() }}
            </span>
        </div>
        <div class="premium-card flex flex-col justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Recipient Reach</span>
            <span class="text-3xl font-extrabold text-gray-900 dark:text-gray-100 mt-2">
                {{ number_format(\App\Models\SystemAnnouncement::sum('recipient_count')) }}
            </span>
        </div>
    </div>

    <!-- Filters & Search block -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <!-- Search input -->
        <x-search-input 
            model="searchQuery" 
            placeholder="Search announcements..." 
            wrapperClass="w-full max-w-[500px]" 
        />

        <!-- Severity filter -->
        <select x-model="selectedSeverity" class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-700 dark:text-gray-300 px-3 py-2 cursor-pointer focus:outline-none focus:border-indigo-500">
            <option value="All">All Severities</option>
            @foreach(\App\Models\SystemAnnouncement::SEVERITIES as $sev)
                <option value="{{ $sev }}">{{ ucfirst($sev) }}</option>
            @endforeach
        </select>
    </div>

    <!-- Announcement list table -->
    <div class="premium-card overflow-hidden">
        <div class="overflow-x-auto rounded-xl border border-gray-100 dark:border-gray-800">
            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                <thead class="bg-gray-50/50 dark:bg-gray-800/40 text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-6 py-3 text-left">Announcement Details</th>
                        <th class="px-6 py-3 text-left">Severity</th>
                        <th class="px-6 py-3 text-left">Published</th>
                        <th class="px-6 py-3 text-left">By</th>
                        <th class="px-6 py-3 text-left">Recipients</th>
                        <th class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-sm">
                    @forelse($announcements as $a)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/20 transition-colors"
                            x-show="(selectedSeverity === 'All' || '{{ $a->severity }}' === selectedSeverity) && (searchQuery === '' || '{{ addslashes(strtolower($a->title)) }}'.includes(searchQuery.toLowerCase()) || '{{ addslashes(strtolower($a->body)) }}'.includes(searchQuery.toLowerCase()))">
                            <td class="px-6 py-4">
                                <div class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ $a->title }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2 max-w-md">{{ $a->body }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="announcement-chip {{ 'chip-'.$a->severity }}">
                                    {{ ucfirst($a->severity) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                {{ $a->published_at ? \App\Support\TenantTime::format($a->published_at, 'M d, Y g:i A') : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                {{ $a->publisher?->name ?? 'System' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-750 dark:text-gray-300">
                                {{ number_format($a->recipient_count) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-xs space-x-2">
                                <button type="button" 
                                        @click="previewItem = { title: '{{ addslashes($a->title) }}', body: '{{ addslashes(str_replace(["\r", "\n"], ' ', $a->body)) }}', severity: '{{ $a->severity }}', published_at: '{{ $a->published_at ? $a->published_at->diffForHumans() : 'Just now' }}' }"
                                        class="text-indigo-600 dark:text-indigo-400 hover:underline font-bold bg-transparent border-none cursor-pointer">
                                    Preview
                                </button>
                                <button type="button" @click="confirmDeleteId = '{{ $a->id }}'" class="text-rose-600 dark:text-rose-400 hover:underline font-bold bg-transparent border-none cursor-pointer">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center text-gray-500 dark:text-gray-450">
                                <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 3.94c.09-.542.56-.94 1.11-.94h1.1c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.107-1.204l-.527-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z" />
                                </svg>
                                <p class="mt-4 text-sm">{{ __('No announcements yet.') }}</p>
                                <a href="{{ route('admin.announcements.create') }}" class="mt-4 inline-flex items-center text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                                    Create announcement &rarr;
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($announcements->hasPages())
        <div class="mt-6">{{ $announcements->links() }}</div>
    @endif

    <!-- Delete Confirmation Modal -->
    <template x-if="confirmDeleteId !== null">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-xl space-y-6">
                <div class="flex items-start gap-4">
                    <div class="p-3 bg-red-100 dark:bg-red-950/20 text-red-600 dark:text-red-400 rounded-xl shrink-0">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Delete Announcement?</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                            Are you sure you want to delete this system broadcast? It will be immediately removed from every user's notification list.
                        </p>
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <button type="button" @click="confirmDeleteId = null" class="px-4 py-2 text-sm font-semibold rounded-xl bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 transition cursor-pointer">
                        Cancel
                    </button>
                    <form :action="'/admin/announcements/' + confirmDeleteId" method="POST" class="inline">
                        @csrf @method('DELETE')
                        <button type="submit" class="px-4 py-2 text-sm font-semibold rounded-xl bg-rose-600 hover:bg-rose-700 text-white shadow-sm transition cursor-pointer border-none">
                            Confirm Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </template>

    <!-- Preview Modal -->
    <template x-if="previewItem !== null">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
            <div class="bg-[var(--bg-app)] text-[var(--text-primary)] rounded-2xl max-w-lg w-full p-6 shadow-xl space-y-6 border border-[var(--border-soft)]">
                <div>
                    <h3 class="text-lg font-extrabold text-[var(--text-primary)]">Mock Notification Preview</h3>
                    <p class="text-xs text-[var(--text-secondary)] mt-1">This shows how users will view this notification in their bell dropdown feed.</p>
                </div>

                <!-- Bell Feed Mock Box -->
                <div class="border border-[var(--border-soft)] rounded-xl bg-[var(--bg-card)] p-4 flex gap-3">
                    <div class="h-10 w-10 rounded-xl flex items-center justify-center shrink-0"
                         :class="{
                             'bg-blue-500/10 text-blue-500': previewItem.severity === 'info',
                             'bg-emerald-500/10 text-emerald-500': previewItem.severity === 'update',
                             'bg-amber-500/10 text-amber-500': previewItem.severity === 'maintenance',
                             'bg-rose-500/10 text-rose-500': previewItem.severity === 'warning'
                         }">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-megaphone"><path d="m3 11 18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-bold text-[var(--text-primary)]" x-text="previewItem.title"></span>
                            <span class="text-[10px] text-[var(--text-secondary)] shrink-0" x-text="previewItem.published_at"></span>
                        </div>
                        <p class="text-xs text-[var(--text-secondary)] mt-1 font-normal leading-relaxed" x-text="previewItem.body"></p>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-[var(--border-soft)]">
                    <button type="button" @click="previewItem = null" class="px-5 py-2.5 text-sm font-semibold rounded-xl bg-[var(--bg-hover)] hover:bg-[var(--bg-active)] text-[var(--text-primary)] transition cursor-pointer border border-[var(--border-soft)]">
                        Close Preview
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection
