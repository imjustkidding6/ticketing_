<nav class="space-y-6">
    <!-- Licensing Section -->
    <div>
        <div class="px-3 mb-2 text-[13px] font-semibold text-[var(--text-secondary)] uppercase tracking-wider">Licensing</div>
        <div class="space-y-1">
            <!-- Dashboard Link -->
            <a href="{{ route('admin.dashboard') }}" 
               class="flex h-12 items-center gap-3 px-4 text-xs font-semibold rounded-xl transition-all duration-200 relative {{ request()->routeIs('admin.dashboard') ? 'active-nav pl-3' : 'normal-nav' }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-dashboard flex-shrink-0"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="10" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                <span>Dashboard</span>
            </a>

            <!-- Licenses Link -->
            <a href="{{ route('admin.licenses.index') }}" 
               class="flex h-12 items-center gap-3 px-4 text-xs font-semibold rounded-xl transition-all duration-200 relative {{ request()->routeIs('admin.licenses.*') ? 'active-nav pl-3' : 'normal-nav' }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-key flex-shrink-0"><path d="m21 2-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0 3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                <span>Licenses</span>
            </a>

            <!-- Distributors Link -->
            <a href="{{ route('admin.distributors.index') }}" 
               class="flex h-12 items-center gap-3 px-4 text-xs font-semibold rounded-xl transition-all duration-200 relative {{ request()->routeIs('admin.distributors.*') ? 'active-nav pl-3' : 'normal-nav' }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-truck flex-shrink-0"><path d="M14 18H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v10"/><path d="M14 22a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/><path d="M6 22a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/><path d="M20 18h2a2 2 0 0 0 2-2v-3.5L20 9h-3v9"/></svg>
                <span>Distributors</span>
            </a>

            <!-- Plans Link -->
            <a href="{{ route('admin.plans.index') }}" 
               class="flex h-12 items-center gap-3 px-4 text-xs font-semibold rounded-xl transition-all duration-200 relative {{ request()->routeIs('admin.plans.*') ? 'active-nav pl-3' : 'normal-nav' }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layers flex-shrink-0"><path d="m12 3-10 9 10 9 10-9-10-9Z"/><path d="m2 17 10 9 10-9"/></svg>
                <span>Plans</span>
            </a>
        </div>
    </div>

    <!-- Management Section -->
    <div>
        <div class="px-3 mb-2 text-[13px] font-semibold text-[var(--text-secondary)] uppercase tracking-wider">Management</div>
        <div class="space-y-1">
            <!-- Tenants Link -->
            <a href="{{ route('admin.tenants.index') }}" 
               class="flex h-12 items-center gap-3 px-4 text-xs font-semibold rounded-xl transition-all duration-200 relative {{ request()->routeIs('admin.tenants.*') ? 'active-nav pl-3' : 'normal-nav' }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building-2 flex-shrink-0"><path d="M2 22h20"/><path d="M20 22V8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v14"/><path d="M12 22v-4"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 14h.01"/><path d="M12 10h.01"/><path d="M16 10h.01"/><path d="M8 10h.01"/></svg>
                <span>Tenants</span>
            </a>

            <!-- Users Link -->
            <a href="{{ route('admin.users.index') }}" 
               class="flex h-12 items-center gap-3 px-4 text-xs font-semibold rounded-xl transition-all duration-200 relative {{ request()->routeIs('admin.users.*') && !request()->query('system') ? 'active-nav pl-3' : 'normal-nav' }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users flex-shrink-0"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span>Users</span>
            </a>
        </div>
    </div>

    <!-- Reports Section -->
    <div>
        <div class="px-3 mb-2 text-[13px] font-semibold text-[var(--text-secondary)] uppercase tracking-wider">Reports</div>
        <div class="space-y-1">
            <!-- Reports Link -->
            <a href="{{ route('admin.reports.index') }}" 
               class="flex h-12 items-center gap-3 px-4 text-xs font-semibold rounded-xl transition-all duration-200 relative {{ request()->routeIs('admin.reports.*') ? 'active-nav pl-3' : 'normal-nav' }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bar-chart-3 flex-shrink-0"><path d="M3 3v18h18"/><path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"/></svg>
                <span>Reports</span>
            </a>
        </div>
    </div>

    <!-- Audit Section -->
    <div>
        <div class="px-3 mb-2 text-[13px] font-semibold text-[var(--text-secondary)] uppercase tracking-wider">Audit</div>
        <div class="space-y-1">
            <!-- Audit Logs Placeholder -->
            <a href="#" onclick="return false;" 
               class="placeholder-nav flex h-12 items-center gap-3 px-4 text-xs font-semibold rounded-xl transition-all duration-200">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text flex-shrink-0"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
                <span>Audit Logs <span class="soon-badge ml-1">Soon</span></span>
            </a>
        </div>
    </div>

    <!-- System Section -->
    <div>
        <div class="px-3 mb-2 text-[13px] font-semibold text-[var(--text-secondary)] uppercase tracking-wider">System</div>
        <div class="space-y-1">
            <!-- Settings Link -->
            <a href="{{ route('admin.settings.index') }}" 
               class="flex h-12 items-center gap-3 px-4 text-xs font-semibold rounded-xl transition-all duration-200 relative {{ request()->routeIs('admin.settings.*') ? 'active-nav pl-3' : 'normal-nav' }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-settings flex-shrink-0"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                <span>Settings</span>
            </a>

            <!-- Admin Users Link -->
            <a href="{{ route('admin.users.index') }}" 
               class="flex h-12 items-center gap-3 px-4 text-xs font-semibold rounded-xl transition-all duration-200 relative {{ request()->routeIs('admin.users.*') && request()->query('system') ? 'active-nav pl-3' : 'normal-nav' }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-cog flex-shrink-0"><circle cx="18" cy="15" r="3"/><circle cx="9" cy="7" r="4"/><path d="M10 15H6a4 4 0 0 0-4 4v2"/><path d="m21.7 16.4-.9-.3M20.6 13.6l-.9-.3M16.2 16.4l-.9-.3M15.1 13.6l-.9-.3"/></svg>
                <span>Admin Users</span>
            </a>

            <!-- Announcements Link -->
            <a href="{{ route('admin.announcements.index') }}" 
               class="flex h-12 items-center gap-3 px-4 text-xs font-semibold rounded-xl transition-all duration-200 relative {{ request()->routeIs('admin.announcements.*') ? 'active-nav pl-3' : 'normal-nav' }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-megaphone flex-shrink-0"><path d="m3 11 18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
                <span>Announcements</span>
            </a>

            <!-- Feedback Link -->
            <a href="{{ route('admin.feedback.index') }}" 
               class="flex h-12 items-center gap-3 px-4 text-xs font-semibold rounded-xl transition-all duration-200 relative {{ request()->routeIs('admin.feedback.*') ? 'active-nav pl-3' : 'normal-nav' }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-message-square flex-shrink-0"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <span>Feedback</span>
            </a>

            <!-- AI Bugs Link -->
            <a href="{{ route('admin.bugs.index') }}" 
               class="flex h-12 items-center gap-3 px-4 text-xs font-semibold rounded-xl transition-all duration-200 relative {{ request()->routeIs('admin.bugs.*') ? 'active-nav pl-3' : 'normal-nav' }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bug flex-shrink-0"><rect width="8" height="14" x="8" y="6" rx="4"/><path d="m19 7-3 2M5 7l3 2M19 19l-3-2M5 19l3-2M20 13h-4M4 13h4M10 4l2-2 2 2"/></svg>
                <span>AI Bugs</span>
            </a>

            <!-- Help & Tutorials Link -->
            <a href="{{ route('admin.help.index') }}" 
               class="flex h-12 items-center gap-3 px-4 text-xs font-semibold rounded-xl transition-all duration-200 relative {{ request()->routeIs('admin.help.*') ? 'active-nav pl-3' : 'normal-nav' }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-help-circle flex-shrink-0"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span>Help & Tutorials</span>
            </a>

            <!-- Notifications Link -->
            <a href="{{ route('admin.notifications.index') }}" 
               class="flex h-12 items-center gap-3 px-4 text-xs font-semibold rounded-xl transition-all duration-200 relative {{ request()->routeIs('admin.notifications.*') ? 'active-nav pl-3' : 'normal-nav' }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bell flex-shrink-0"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                <span>Notifications</span>
            </a>
        </div>
    </div>
</nav>
