<div class="mt-auto border-t border-[var(--border-soft)] p-4">
    <div class="flex items-center justify-between gap-3 p-2 rounded-2xl bg-[var(--bg-hover)] border border-[var(--border-soft)]">
        <div class="flex items-center gap-3 min-w-0">
            <!-- Avatar Badge -->
            <div class="h-9 w-9 rounded-xl bg-[var(--primary)] flex-shrink-0 flex items-center justify-center text-white font-bold text-sm shadow-sm">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
            <!-- Details -->
            <div class="min-w-0">
                <p class="text-xs font-bold text-[var(--text-primary)] truncate">{{ auth()->user()->name }}</p>
                <p class="text-[10px] text-[var(--text-secondary)] truncate mt-0.5">{{ auth()->user()->email }}</p>
            </div>
        </div>
        <!-- Logout Button -->
        <form method="POST" action="{{ route('logout') }}" class="flex-shrink-0">
            @csrf
            <button type="submit" title="Log Out" class="p-2 rounded-xl text-[var(--text-secondary)] hover:text-red-500 hover:bg-red-500/10 transition-colors">
                <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                </svg>
            </button>
        </form>
    </div>
</div>
