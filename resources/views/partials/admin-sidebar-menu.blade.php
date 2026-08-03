@php
$sidebarGroups = [
    [
        'header' => 'Dashboard',
        'items' => [
            [
                'title' => 'Dashboard',
                'route' => route('admin.dashboard'),
                'active' => request()->routeIs('admin.dashboard'),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-dashboard flex-shrink-0"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="10" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>',
            ],
        ],
    ],
    [
        'header' => 'Workspace',
        'items' => [
            [
                'title' => 'Tenants',
                'route' => route('admin.tenants.index'),
                'active' => request()->routeIs('admin.tenants.*'),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building-2 flex-shrink-0"><path d="M2 22h20"/><path d="M20 22V8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v14"/><path d="M12 22v-4"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 14h.01"/><path d="M12 10h.01"/><path d="M16 10h.01"/><path d="M8 10h.01"/></svg>',
            ],
            [
                'title' => 'Users',
                'route' => route('admin.users.index'),
                'active' => request()->routeIs('admin.users.*') && !request()->query('system'),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users flex-shrink-0"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            ],
        ],
    ],
    [
        'header' => 'Business',
        'items' => [
            [
                'title' => 'Licenses',
                'route' => route('admin.licenses.index'),
                'active' => request()->routeIs('admin.licenses.*'),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-key flex-shrink-0"><path d="m21 2-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0 3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>',
            ],
            [
                'title' => 'Plans',
                'route' => route('admin.plans.index'),
                'active' => request()->routeIs('admin.plans.*'),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layers flex-shrink-0"><path d="m12 3-10 9 10 9 10-9-10-9Z"/><path d="m2 17 10 9 10-9"/></svg>',
            ],
            [
                'title' => 'Distributors',
                'route' => route('admin.distributors.index'),
                'active' => request()->routeIs('admin.distributors.*'),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-truck flex-shrink-0"><path d="M14 18H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v10"/><path d="M14 22a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/><path d="M6 22a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/><path d="M20 18h2a2 2 0 0 0 2-2v-3.5L20 9h-3v9"/></svg>',
            ],
        ],
    ],
    [
        'header' => 'Operations',
        'items' => [
            [
                'title' => 'SLA Policies',
                'route' => route('admin.sla.index'),
                'active' => request()->routeIs('admin.sla.*') || request()->routeIs('sla.*') || request()->is('*/sla*'),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shield-check flex-shrink-0"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>',
            ],
            [
                'title' => 'Announcements',
                'route' => route('admin.announcements.index'),
                'active' => request()->routeIs('admin.announcements.*'),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-megaphone flex-shrink-0"><path d="m3 11 18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>',
            ],
            [
                'title' => 'Notifications',
                'route' => route('admin.notifications.index'),
                'active' => request()->routeIs('admin.notifications.*'),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bell flex-shrink-0"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>',
            ],
        ],
    ],
    [
        'header' => 'Analytics',
        'items' => [
            [
                'title' => 'Reports',
                'route' => route('admin.reports.index'),
                'active' => request()->routeIs('admin.reports.*'),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bar-chart-3 flex-shrink-0"><path d="M3 3v18h18"/><path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"/></svg>',
            ],
            [
                'title' => 'Audit Logs',
                'route' => route('admin.ai.conversations'),
                'active' => request()->routeIs('admin.ai.conversations'),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text flex-shrink-0"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>',
            ],
        ],
    ],
    [
        'header' => 'AI',
        'items' => [
            [
                'title' => 'AI Assistant',
                'route' => route('admin.ai.dashboard'),
                'active' => request()->routeIs('admin.ai.dashboard'),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles flex-shrink-0"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/><path d="M5 3v4"/><path d="M19 17v4"/><path d="M3 5h4"/><path d="M17 19h4"/></svg>',
            ],
            [
                'title' => 'Chat',
                'route' => route('admin.ai.chat-page'),
                'active' => request()->routeIs('admin.ai.chat-page'),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-message-square flex-shrink-0"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
            ],
            [
                'title' => 'Conversation History',
                'route' => route('admin.ai.conversations'),
                'active' => request()->routeIs('admin.ai.conversations'),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-history flex-shrink-0"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>',
            ],
            [
                'title' => 'Prompt Templates',
                'route' => route('admin.ai.prompts'),
                'active' => request()->routeIs('admin.ai.prompts'),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-code flex-shrink-0"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="m10 13-2 2 2 2"/><path d="m14 13 2 2-2 2"/></svg>',
            ],
            [
                'title' => 'AI Settings',
                'route' => route('admin.ai.settings'),
                'active' => request()->routeIs('admin.ai.settings'),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sliders flex-shrink-0"><line x1="4" x2="4" y1="21" y2="14"/><line x1="4" x2="4" y1="10" y2="3"/><line x1="12" x2="12" y1="21" y2="12"/><line x1="12" x2="12" y1="8" y2="3"/><line x1="20" x2="20" y1="21" y2="16"/><line x1="20" x2="20" y1="12" y2="3"/><line x1="2" x2="6" y1="14" y2="14"/><line x1="10" x2="14" y1="8" y2="8"/><line x1="18" x2="22" y1="16" y2="16"/></svg>',
            ],
            [
                'title' => 'AI Diagnostics',
                'route' => route('admin.bugs.index'),
                'active' => request()->routeIs('admin.bugs.*'),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bug flex-shrink-0"><rect width="8" height="14" x="8" y="6" rx="4"/><path d="m19 7-3 2M5 7l3 2M19 19l-3-2M5 19l3-2M20 13h-4M4 13h4M10 4l2-2 2 2"/></svg>',
            ],
            [
                'title' => 'AI Usage',
                'route' => route('admin.ai.analytics'),
                'active' => request()->routeIs('admin.ai.analytics'),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-cpu flex-shrink-0"><rect width="16" height="16" x="4" y="4" rx="2"/><rect width="6" height="6" x="9" y="9" rx="1"/><path d="M15 2v2"/><path d="M15 20v2"/><path d="M2 15h2"/><path d="M2 9h2"/><path d="M20 15h2"/><path d="M20 9h2"/><path d="M9 2v2"/><path d="M9 20v2"/></svg>',
            ],
        ],
    ],
    [
        'header' => 'Administration',
        'items' => [
            [
                'title' => 'Settings',
                'route' => route('admin.settings.index'),
                'active' => request()->routeIs('admin.settings.*'),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-settings flex-shrink-0"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
            ],
            [
                'title' => 'Administrators',
                'route' => route('admin.users.index').'?system=1',
                'active' => request()->routeIs('admin.users.*') && request()->query('system'),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-cog flex-shrink-0"><circle cx="18" cy="15" r="3"/><circle cx="9" cy="7" r="4"/><path d="M10 15H6a4 4 0 0 0-4 4v2"/><path d="m21.7 16.4-.9-.3M20.6 13.6l-.9-.3M16.2 16.4l-.9-.3M15.1 13.6l-.9-.3"/></svg>',
            ],
        ],
    ],
    [
        'header' => 'Help',
        'items' => [
            [
                'title' => 'User Feedback',
                'route' => route('admin.feedback.index'),
                'active' => request()->routeIs('admin.feedback.*'),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-message-square flex-shrink-0"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
            ],
            [
                'title' => 'Help Center',
                'route' => route('admin.help.index'),
                'active' => request()->routeIs('admin.help.*'),
                'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-help-circle flex-shrink-0"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            ],
        ],
    ],
];
@endphp

<nav class="space-y-6">
    @foreach ($sidebarGroups as $group)
        <div>
            <div class="px-3 mb-2 text-[13px] font-semibold text-[var(--text-secondary)] uppercase tracking-wider">
                {{ $group['header'] }}
            </div>
            <div class="space-y-1">
                @foreach ($group['items'] as $item)
                    <a href="{{ $item['route'] }}" 
                       class="flex h-12 items-center gap-3 px-4 text-xs font-semibold rounded-xl transition-all duration-200 relative {{ $item['active'] ? 'active-nav pl-3' : 'normal-nav' }}">
                        {!! $item['svg'] !!}
                        <span>{{ $item['title'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</nav>
