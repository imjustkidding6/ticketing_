@extends('layouts.admin')

@section('title', 'New Announcement')

@section('content')
<style>
    .announcement-create-container {
        font-family: 'Inter', sans-serif !important;
    }
    
    .premium-card {
        background-color: var(--bg-card, #ffffff);
        border: 1px solid var(--border-soft, rgba(0,0,0,0.06));
        border-radius: 16px;
        padding: 24px;
        box-shadow: var(--shadow, 0 1px 3px rgba(0,0,0,0.05));
    }
    html.dark .premium-card {
        background-color: #1f2937;
        border-color: #374151;
    }

    .form-input-ann {
        background-color: var(--bg-card, #ffffff);
        color: var(--text-primary, #111827);
        border: 1px solid var(--border-soft, rgba(0,0,0,0.06));
        border-radius: 12px;
        height: 44px;
        font-size: 14px;
        outline: none;
    }
    .dark .form-input-ann {
        background-color: #374151;
        border-color: #4b5563;
        color: #f3f4f6;
    }
    .form-input-ann:focus {
        border-color: var(--primary) !important;
        box-shadow: 0 0 0 3px rgba(91, 95, 246, 0.15) !important;
    }

    .btn-action-ann {
        height: 44px !important;
        padding: 0 20px !important;
        border-radius: 12px !important;
        font-size: 14px !important;
        font-weight: 600 !important;
        transition: all 0.2s ease !important;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
</style>

<div class="announcement-create-container pb-16 space-y-6" x-data="{
    title: '{{ old('title', '') }}',
    body: '{{ old('body', '') }}',
    severity: '{{ old('severity', 'update') }}'
}">
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.announcements.index') }}" class="text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
            <span>Back to Broadcasts</span>
        </a>
    </div>

    <div class="flex flex-col lg:flex-row gap-8">
        
        <!-- Left Side: Form Configuration -->
        <form method="POST" action="{{ route('admin.announcements.store') }}" class="premium-card flex-1 space-y-6">
            @csrf

            <div>
                <h2 class="text-xl font-extrabold text-gray-900 dark:text-gray-100 uppercase tracking-wide">
                    New System Announcement
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    This will be broadcasted to every user in every tenant's notification feed.
                </p>
            </div>

            <!-- Title -->
            <div class="space-y-2">
                <label for="title" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">Title <span class="text-red-500">*</span></label>
                <input type="text" name="title" id="title" required maxlength="255" x-model="title"
                       placeholder="e.g. v2.1 released — bug fixes &amp; improvements"
                       class="form-input-ann block w-full px-4">
                @error('title')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <!-- Body Message -->
            <div class="space-y-2">
                <label for="body" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">Message <span class="text-red-500">*</span></label>
                <textarea name="body" id="body" required rows="6" maxlength="5000" x-model="body"
                          placeholder="Describe the update, bug fixes, or maintenance window..."
                          class="form-input-ann block w-full px-4 py-3 h-auto min-h-[160px]"></textarea>
                @error('body')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <!-- Severity -->
            <div class="space-y-2">
                <label for="severity" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">Severity <span class="text-red-500">*</span></label>
                <select name="severity" id="severity" required x-model="severity"
                        class="form-input-ann block w-full px-4 cursor-pointer">
                    @foreach($severities as $sev)
                        <option value="{{ $sev }}">{{ ucfirst($sev) }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Controls the indicator color in client feeds (Update: Green, Warning: Red, etc.).</p>
            </div>

            <div class="rounded-xl bg-amber-50 border border-amber-200 dark:bg-amber-950/20 dark:border-amber-900/30 p-4 text-sm text-amber-800 dark:text-amber-400">
                <p class="font-bold">Publishing immediately broadcasts to all active user dashboards.</p>
            </div>

            <!-- Footer Actions -->
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-100 dark:border-gray-800">
                <a href="{{ route('admin.announcements.index') }}" class="btn-action-ann bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 justify-center">
                    Cancel
                </a>
                <button type="submit" class="btn-action-ann bg-[#5B5FF6] text-white hover:bg-[#4752C4] shadow-sm justify-center border-none cursor-pointer">
                    Publish &amp; Broadcast
                </button>
            </div>
        </form>

        <!-- Right Side: Live Interactive Mockup Feed Preview -->
        <div class="lg:w-[380px] shrink-0 space-y-6">
            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">Live Client Mock Preview</h3>
            
            <div class="premium-card space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-gray-100 dark:border-gray-800">
                    <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Feed Drawer preview</span>
                    <span class="h-2 w-2 rounded-full bg-green-500 animate-pulse"></span>
                </div>

                <!-- Bell Mock notification view -->
                <div class="border border-[var(--border-soft)] rounded-2xl bg-[var(--bg-app)] p-4 flex gap-3">
                    <div class="h-10 w-10 rounded-xl flex items-center justify-center shrink-0"
                         :class="{
                             'bg-blue-500/10 text-blue-500': severity === 'info',
                             'bg-emerald-500/10 text-emerald-500': severity === 'update',
                             'bg-amber-500/10 text-amber-500': severity === 'maintenance',
                             'bg-rose-500/10 text-rose-500': severity === 'warning'
                         }">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-megaphone"><path d="m3 11 18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-bold text-[var(--text-primary)] truncate" x-text="title || 'Announcement Title placeholder'"></span>
                            <span class="text-[10px] text-[var(--text-secondary)] shrink-0">Just now</span>
                        </div>
                        <p class="text-xs text-[var(--text-secondary)] mt-1 font-normal leading-relaxed break-words" x-text="body || 'Announcement body content description placeholder text...'"></p>
                    </div>
                </div>

                <p class="text-[11px] text-gray-400 dark:text-gray-500 text-center leading-relaxed">
                    This interactive drawer component reflects colors and text fields live based on input parameters.
                </p>
            </div>
        </div>

    </div>
</div>
@endsection
