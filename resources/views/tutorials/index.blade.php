<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Help & Tutorials') }}</h2>
    </x-slot>

    @php
        $slug = 'getting-started';
        $tutorial = $tutorials[$slug];

        $keys = array_keys($tutorials);
        $currentIndex = array_search($slug, $keys);
        $prevSlug = $currentIndex > 0 ? $keys[$currentIndex - 1] : null;
        $nextSlug = $currentIndex < count($keys) - 1 ? $keys[$currentIndex + 1] : null;

        $prevTutorial = $prevSlug ? $tutorials[$prevSlug] : null;
        $nextTutorial = $nextSlug ? $tutorials[$nextSlug] : null;

        // Load config values
        $configContent = config('tutorials.' . $slug) ?? [
            'intro' => 'Welcome to the ' . $tutorial['title'] . ' tutorial guide.',
            'tip' => 'Make sure to follow each step carefully.',
            'screenshot' => $slug . '.png',
            'steps' => [],
            'notes' => ''
        ];

        // Pick 3 related tutorials
        $relatedTutorials = collect($tutorials)
            ->forget($slug)
            ->random(min(3, count($tutorials) - 1));
    @endphp

    <style>
        html {
            scroll-behavior: smooth !important;
        }
        .scroll-mt-24 {
            scroll-margin-top: 96px !important;
        }

        /* CliqueHA SaaS Help Center Variables matching Admin Panel design */
        .help-portal-container {
            font-family: 'Inter', sans-serif !important;
            background-color: var(--bg-app) !important;
            color: var(--text-primary) !important;
            transition: background-color 200ms ease, color 200ms ease !important;
        }

        /* Sidebar link item */
        .help-nav-link {
            color: var(--text-secondary) !important;
            transition: all 150ms ease !important;
            border-radius: 12px !important;
            font-size: 14px !important;
            font-weight: 500 !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            padding: 10px 14px !important;
            border: none !important;
        }
        .help-nav-link:hover {
            background-color: var(--bg-hover) !important;
            color: var(--text-primary) !important;
        }
        .help-nav-link.active {
            background-color: #5B5FF6 !important;
            color: #FFFFFF !important;
            font-weight: 600 !important;
        }
        .help-nav-link.active svg {
            color: #FFFFFF !important;
            stroke: #FFFFFF !important;
        }

        /* Search bar styles */
        .search-bar-input {
            background-color: var(--bg-card) !important;
            color: var(--text-primary) !important;
            border: 1px solid var(--border-soft) !important;
            border-radius: 16px !important;
            height: 52px !important;
            font-size: 15px !important;
            outline: none;
        }
        .search-bar-input:focus {
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 3px rgba(91, 95, 246, 0.15) !important;
        }

        /* Download manual button style */
        .btn-download-pill {
            background-color: #5865F2 !important;
            color: #FFFFFF !important;
            border-radius: 9999px !important;
            height: 44px !important;
            padding: 0 24px !important;
            font-weight: 500 !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            box-shadow: 0 4px 14px rgba(88, 101, 242, 0.3) !important;
            transition: all 0.2s ease !important;
        }
        .btn-download-pill:hover {
            background-color: #4752C4 !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 20px rgba(88, 101, 242, 0.4) !important;
        }

        /* Documentation Card */
        .documentation-card {
            background-color: var(--bg-card) !important;
            border: 1px solid var(--border-soft) !important;
            box-shadow: var(--shadow-soft) !important;
            border-radius: 24px !important;
        }

        /* Tip box */
        .tip-box {
            border-left: 4px solid #A855F7 !important;
            background-color: rgba(168, 85, 247, 0.05) !important;
            border-radius: 12px !important;
        }

        /* Screenshot placeholder */
        .screenshot-placeholder {
            background-color: var(--bg-hover) !important;
            border: 2px dashed var(--border-soft) !important;
            border-radius: 16px !important;
        }

        /* Table of contents link */
        .toc-link {
            font-size: 13px !important;
            font-weight: 500 !important;
            color: var(--text-secondary) !important;
            transition: all 0.2s ease !important;
        }
        .toc-link:hover {
            color: var(--primary) !important;
        }

        /* Related card style */
        .related-card {
            background-color: var(--bg-card) !important;
            border: 1px solid var(--border-soft) !important;
            border-radius: 16px !important;
            transition: all 0.2s ease !important;
        }
        .related-card:hover {
            transform: translateY(-4px) !important;
            box-shadow: var(--shadow-soft) !important;
        }
        .related-icon-box {
            background-color: rgba(91, 95, 246, 0.08) !important;
            color: var(--primary) !important;
            border-radius: 12px !important;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Bottom nav buttons */
        .nav-btn {
            background-color: var(--bg-card) !important;
            border: 1px solid var(--border-soft) !important;
            box-shadow: var(--shadow-soft) !important;
            border-radius: 14px !important;
            padding: 16px 20px !important;
            transition: all 0.2s ease !important;
            width: 100%;
            display: flex;
            flex-direction: column;
        }
        .nav-btn:hover {
            transform: translateY(-2px) !important;
            border-color: var(--primary) !important;
        }
    </style>

    <div class="py-10 help-portal-container min-h-screen">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            
            <!-- Header Area -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-[var(--border-soft)] mb-8">
                <div>
                    <h1 class="text-3xl font-extrabold text-[var(--text-primary)] tracking-tight">Help & Tutorials</h1>
                    <p class="mt-2 text-[var(--text-secondary)] text-sm">
                        Everything you need to get the most out of CliqueHA Admin. Browse guides or search instantly.
                    </p>
                </div>
                <div class="flex-shrink-0">
                    <a href="{{ asset('docs/Admin-User-Manual.pdf') }}" download class="btn-download-pill">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-download text-white"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        <span>Download User Manual</span>
                    </a>
                </div>
            </div>

            <!-- Alpine Search bar -->
            <div x-data="{ query: '' }" @input.debounce.50ms="window.dispatchEvent(new CustomEvent('filter-tutorials', { detail: query }))" class="relative w-full mb-8">
                <div class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 flex items-center justify-center">
                    <svg class="h-[20px] w-[20px] text-[var(--text-secondary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="text" x-model="query" placeholder="Search tutorials... e.g. SLA, Tickets, Users..." class="w-full search-bar-input pl-12 pr-4">
            </div>

            <!-- Responsive Layout Columns Grid -->
            <div class="flex flex-col lg:flex-row gap-8 items-start">
                
                <!-- Mobile Sidebar Accordion -->
                <div x-data="{ sidebarCollapsed: true }" class="lg:hidden w-full mb-6">
                    <button @click="sidebarCollapsed = !sidebarCollapsed" class="w-full flex items-center justify-between p-4 bg-[var(--bg-card)] rounded-xl border border-[var(--border-soft)] shadow-sm">
                        <span class="font-bold text-sm text-[var(--text-primary)]">Guides Menu</span>
                        <svg class="h-5 w-5 transition-transform duration-200" :class="!sidebarCollapsed ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                    </button>
                    <div x-show="!sidebarCollapsed" x-collapse class="mt-2 bg-[var(--bg-card)] p-4 rounded-xl border border-[var(--border-soft)] shadow-md flex flex-col gap-2">
                        @foreach($tutorials as $navSlug => $navTutorial)
                            <a href="{{ route('tutorials.show', $navSlug) }}" class="help-nav-link {{ $navSlug === $slug ? 'active' : '' }}">
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center">
                                    @include('tutorials.partials._icon', ['icon' => $navTutorial['icon']])
                                </span>
                                <span>{{ __($navTutorial['title']) }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- Left Sidebar (Desktop 22% / Tablet 30%) -->
                <aside class="hidden lg:block lg:w-[22%] shrink-0">
                    <div class="bg-[var(--bg-card)] p-5 rounded-2xl border border-[var(--border-soft)] shadow-[var(--shadow-soft)] flex flex-col gap-4">
                        <nav class="flex flex-col gap-1" x-data="{
                            activeQuery: '',
                            init() {
                                window.addEventListener('filter-tutorials', (e) => {
                                    this.activeQuery = e.detail.toLowerCase().trim();
                                });
                            }
                        }">
                            <p class="px-3 pb-1 text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">Guides</p>
                            @foreach($tutorials as $navSlug => $navTutorial)
                                @php
                                    $config = config('tutorials.' . $navSlug) ?? [];
                                    $searchContent = strtolower($navTutorial['title'] . ' ' . $navTutorial['description'] . ' ' . ($config['intro'] ?? '') . ' ' . implode(' ', array_column($config['steps'] ?? [], 'title')) . ' ' . implode(' ', array_column($config['steps'] ?? [], 'desc')));
                                @endphp
                                <a href="{{ route('tutorials.show', $navSlug) }}"
                                   x-show="activeQuery === '' || '{{ addslashes($searchContent) }}'.includes(activeQuery)"
                                   class="help-nav-link {{ $navSlug === $slug ? 'active' : '' }}">
                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center">
                                        @include('tutorials.partials._icon', ['icon' => $navTutorial['icon']])
                                    </span>
                                    <span>{{ __($navTutorial['title']) }}</span>
                                </a>
                            @endforeach
                        </nav>
                    </div>
                </aside>

                <!-- Right Content Area (Desktop 78% / Tablet 70%) -->
                <div class="w-full lg:w-[78%] flex flex-col gap-8">
                    <div class="flex flex-col xl:flex-row gap-8 items-start">
                        
                        <!-- Main Documentation Card -->
                        <div class="documentation-card p-6 sm:p-8 flex-1 flex flex-col gap-6">
                            
                            <!-- Header Meta Title Info -->
                            <div>
                                <h2 class="text-3xl font-extrabold text-[var(--text-primary)] mb-2">{{ __($tutorial['title']) }}</h2>
                                <p class="text-base text-[var(--text-secondary)] leading-relaxed">{{ __($tutorial['description']) }}</p>
                                
                                <div class="flex flex-wrap items-center gap-4 mt-4 text-xs font-medium text-[var(--text-secondary)] pb-6 border-b border-[var(--border-soft)]">
                                    <span class="flex items-center gap-1.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clock"><circle cx="12" cy="12" r="10"/><path d="M12 6v6h4.5"/></svg>
                                        <span>Estimated time: 5 min</span>
                                    </span>
                                    <span>•</span>
                                    <span class="flex items-center gap-1.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-calendar"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                                        <span>Last updated: July 2026</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Introduction Paragraph -->
                            <p id="intro" class="scroll-mt-24 text-base text-[var(--text-primary)] leading-relaxed font-normal">
                                {{ $configContent['intro'] }}
                            </p>

                            <!-- Tip Box -->
                            <div class="tip-box p-5 flex items-start gap-4 mb-2">
                                <div class="h-10 w-10 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-lightbulb"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A5 5 0 0 0 8 8c0 1 .5 2.5 1.5 3.5.7.8 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/></svg>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-[var(--text-primary)] mb-1">Useful Tip</h4>
                                    <p class="text-sm text-[var(--text-secondary)] leading-relaxed font-normal">{{ $configContent['tip'] }}</p>
                                </div>
                            </div>

                            <!-- Screenshot / Fallback placeholder -->
                            <div>
                                @if(file_exists(public_path('images/tutorials/' . $configContent['screenshot'])))
                                    <img src="{{ asset('images/tutorials/' . $configContent['screenshot']) }}" alt="{{ $tutorial['title'] }}" class="rounded-xl border border-[var(--border-soft)] w-full object-cover max-h-[400px]" loading="lazy">
                                @else
                                    <div class="screenshot-placeholder flex flex-col items-center justify-center py-16 px-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-image text-[var(--text-secondary)] mb-3"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                                        <h4 class="text-base font-bold text-[var(--text-primary)] mb-1">Screenshot Coming Soon</h4>
                                        <p class="text-xs text-[var(--text-secondary)] text-center max-w-[280px]">Our documentation team is currently capturing the latest screen updates for this guide.</p>
                                    </div>
                                @endif
                            </div>

                            <!-- Step-by-Step Cards -->
                            <div class="flex flex-col gap-6 mt-4">
                                @foreach($configContent['steps'] as $index => $step)
                                    <div id="step-{{ $index + 1 }}" class="scroll-mt-24 bg-[var(--bg-card)] border border-[var(--border-soft)] p-6 rounded-xl shadow-sm transition-all duration-200 hover:-translate-y-1 hover:shadow-md">
                                        <div class="flex items-center justify-between mb-4">
                                            <span class="text-xs font-bold uppercase tracking-wider text-[var(--primary)] bg-[var(--bg-active)] px-2.5 py-1 rounded-md">Step {{ $index + 1 }}</span>
                                            <span class="text-xs text-[var(--text-secondary)]">Task Guide</span>
                                        </div>
                                        <h3 class="text-lg font-bold text-[var(--text-primary)] mb-2">{{ $step['title'] }}</h3>
                                        <p class="text-sm text-[var(--text-secondary)] leading-relaxed mb-4">{{ $step['desc'] }}</p>
                                        
                                        @if(!empty($step['code']))
                                            <div class="bg-[var(--bg-hover)] border border-[var(--border-soft)] p-4 rounded-lg font-mono text-xs text-[var(--text-primary)] mb-4 overflow-x-auto whitespace-pre-wrap">
                                                <code>{{ $step['code'] }}</code>
                                            </div>
                                        @endif

                                        @if(!empty($step['note']))
                                            <div class="flex items-center gap-2 text-xs text-[var(--text-secondary)]">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-alert-circle text-[var(--primary)]"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12.01" y1="8" y2="8"/><line x1="12" x2="12" y1="12" y2="16"/></svg>
                                                <span><strong class="font-semibold">Note:</strong> {{ $step['note'] }}</span>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            <!-- Notes Section -->
                            @if(!empty($configContent['notes']))
                                <div id="notes" class="scroll-mt-24 border-t border-[var(--border-soft)] pt-6 mt-4">
                                    <h5 class="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)] mb-2">Notes</h5>
                                    <p class="text-xs text-[var(--text-secondary)] leading-relaxed">{{ $configContent['notes'] }}</p>
                                </div>
                            @endif

                        </div>

                        <!-- Right Table of Contents (Sticky head on desktop) -->
                        <aside class="hidden xl:block w-64 shrink-0 xl:sticky xl:top-24">
                            <div class="bg-[var(--bg-card)] p-5 rounded-2xl border border-[var(--border-soft)] shadow-sm">
                                <p class="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)] mb-4">On this page</p>
                                <nav class="flex flex-col gap-3">
                                    <a href="#intro" class="toc-link flex items-start gap-2">
                                        <span class="text-[var(--primary)] font-bold mt-0.5">•</span>
                                        <span>Introduction</span>
                                    </a>
                                    @foreach($configContent['steps'] as $index => $step)
                                        <a href="#step-{{ $index + 1 }}" class="toc-link flex items-start gap-2">
                                            <span class="text-[var(--primary)] font-bold mt-0.5">•</span>
                                            <span>Step {{ $index + 1 }}: {{ $step['title'] }}</span>
                                        </a>
                                    @endforeach
                                    @if(!empty($configContent['notes']))
                                        <a href="#notes" class="toc-link flex items-start gap-2">
                                            <span class="text-[var(--primary)] font-bold mt-0.5">•</span>
                                            <span>Notes</span>
                                        </a>
                                    @endif
                                </nav>
                            </div>
                        </aside>

                    </div>

                    <!-- Bottom Previous / Next Buttons -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-6 border-t border-[var(--border-soft)]">
                        <!-- Previous Button -->
                        @if($prevSlug)
                            <a href="{{ route('tutorials.show', $prevSlug) }}" class="nav-btn items-start">
                                <span class="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)] mb-1">Previous</span>
                                <span class="text-base font-extrabold text-[var(--text-primary)] flex items-center gap-1.5">
                                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                                    <span>{{ __($prevTutorial['title']) }}</span>
                                </span>
                            </a>
                        @else
                            <div></div>
                        @endif

                        <!-- Next Button -->
                        @if($nextSlug)
                            <a href="{{ route('tutorials.show', $nextSlug) }}" class="nav-btn items-end text-right">
                                <span class="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)] mb-1">Next</span>
                                <span class="text-base font-extrabold text-[var(--text-primary)] flex items-center gap-1.5">
                                    <span>{{ __($nextTutorial['title']) }}</span>
                                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                                </span>
                            </a>
                        @endif
                    </div>

                    <!-- Related Tutorials Grid (Bottom section) -->
                    <div class="border-t border-[var(--border-soft)] pt-12 mt-4">
                        <h3 class="text-lg font-bold text-[var(--text-primary)] mb-6">Related Tutorials</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            @foreach($relatedTutorials as $relSlug => $relTutorial)
                                <a href="{{ route('tutorials.show', $relSlug) }}" class="related-card p-5 flex flex-col justify-between h-full hover:-translate-y-1">
                                    <div>
                                        <div class="related-icon-box mb-4">
                                            @include('tutorials.partials._icon', ['icon' => $relTutorial['icon']])
                                        </div>
                                        <h4 class="text-sm font-bold text-[var(--text-primary)] mb-2">{{ __($relTutorial['title']) }}</h4>
                                        <p class="text-xs text-[var(--text-secondary)] leading-relaxed mb-4">{{ __($relTutorial['description']) }}</p>
                                    </div>
                                    <span class="text-xs font-semibold text-[var(--primary)] hover:underline flex items-center gap-1">
                                        <span>Read guide</span>
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </div>
</x-app-layout>
