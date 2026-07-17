<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Admin - {{ config('app.name', 'Laravel') }}</title>

    <link rel="icon" type="image/png" href="{{ asset('cliqueha-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('cliqueha-logo.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Anti-flicker & Reusable Theme Manager -->
    <script>
        function applyTheme(theme) {
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            localStorage.theme = theme;
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: theme }));
        }

        (function() {
            const savedTheme = localStorage.theme;
            const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            const initialTheme = savedTheme || systemTheme || 'light';
            applyTheme(initialTheme);
        })();
    </script>

    <style>
        /* Complete Design System with Reusable variables */
        :root {
            --bg-app: #F8FAFC;
            --bg-sidebar: #FFFFFF;
            --bg-header: #FFFFFF;
            --bg-card: #FFFFFF;
            --bg-hover: #F1F5F9;
            --bg-active: #E0E7FF;

            --text-primary: #0F172A;
            --text-secondary: #64748B;
            --primary: #5B5FF6;

            --border-soft: rgba(15, 23, 42, 0.04);
            --shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
        }

        html.dark {
            --bg-app: #161E2D;
            --bg-sidebar: #1D2636;
            --bg-header: #232C3A;
            --bg-card: #252E3D;
            --bg-hover: #2A3550;
            --bg-active: #2E3A5E;

            --text-primary: #F8FAFC;
            --text-secondary: #9CA3AF;
            --primary: #5B5FF6;

            --border-soft: rgba(255, 255, 255, 0.04);
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        }

        /* Typography Standardization scale */
        body {
            font-family: "Inter", "Poppins", sans-serif !important;
            font-size: 14px !important;
            font-weight: 400 !important;
            background-color: var(--bg-app) !important;
            color: var(--text-primary) !important;
        }

        h1, .page-title {
            font-size: 30px !important;
            font-weight: 700 !important;
            letter-spacing: -0.02em !important;
        }

        h2, .section-title {
            font-size: 20px !important;
            font-weight: 700 !important;
            letter-spacing: -0.01em !important;
        }

        h3, .card-title, .grid > div span[class*="uppercase"] {
            font-size: 13px !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.08em !important;
        }

        .metric-number, .grid > div span[class*="text-3xl"] {
            font-size: 34px !important;
            font-weight: 700 !important;
            letter-spacing: -0.01em !important;
        }

        th, .table-header {
            font-size: 13px !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.08em !important;
        }

        td, .table-content {
            font-size: 14px !important;
            font-weight: 500 !important;
        }

        .sidebar-scroll a, .sidebar-menu {
            font-size: 15px !important;
            font-weight: 500 !important;
        }

        header, .header-navigation {
            font-size: 14px !important;
            font-weight: 500 !important;
        }

        label, .form-label {
            font-size: 13px !important;
            font-weight: 500 !important;
        }

        .secondary-text, p[class*="text-slate-500"], p[class*="text-gray-500"] {
            font-size: 13px !important;
            font-weight: 400 !important;
        }
            
            --success: #22C55E;
            --warning: #F59E0B;
            --danger: #EF4444;
        }

        /* Typography Standardization scale & Global smooth transitions */
        body, 
        div, 
        header, 
        main, 
        section, 
        aside, 
        nav, 
        table, 
        thead, 
        tbody, 
        tr, 
        th, 
        td, 
        a, 
        button, 
        span, 
        h1, 
        h2, 
        h3, 
        h4, 
        h5, 
        h6, 
        p, 
        input, 
        select, 
        textarea, 
        label, 
        svg {
            font-family: "Inter", "Poppins", sans-serif !important;
            transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease !important;
        }

        body {
            font-size: 14px !important;
            font-weight: 400 !important;
            background-color: var(--bg-app) !important;
            color: var(--text-primary) !important;
        }

        h1, .page-title {
            font-size: 30px !important;
            font-weight: 700 !important;
            letter-spacing: -0.02em !important;
        }

        h2, .section-title {
            font-size: 20px !important;
            font-weight: 700 !important;
            letter-spacing: -0.01em !important;
        }

        h3, .card-title, .grid > div span[class*="uppercase"] {
            font-size: 13px !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.08em !important;
        }

        .metric-number, .grid > div span[class*="text-3xl"] {
            font-size: 34px !important;
            font-weight: 700 !important;
            letter-spacing: -0.01em !important;
        }

        th, .table-header {
            font-size: 13px !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.08em !important;
        }

        td, .table-content {
            font-size: 14px !important;
            font-weight: 500 !important;
        }

        .sidebar-menu {
            font-size: 15px !important;
            font-weight: 500 !important;
        }

        header, .header-navigation {
            font-size: 14px !important;
            font-weight: 500 !important;
        }

        label, .form-label {
            font-size: 13px !important;
            font-weight: 500 !important;
        }

        .secondary-text, p[class*="text-slate-500"], p[class*="text-gray-500"] {
            font-size: 13px !important;
            font-weight: 400 !important;
        }

        .small-caption {
            font-size: 12px !important;
            font-weight: 400 !important;
        }

        /* Redesign Cards: No white borders, background color, 20px radius, 24px padding, soft shadow */
        .bg-white, 
        .card, 
        .shadow-sm.bg-white, 
        .shadow.bg-white, 
        .shadow-md.bg-white,
        .rounded-lg.bg-white, 
        .rounded-xl.bg-white, 
        .rounded-2xl.bg-white,
        main [class*="bg-white"]:not(.w-10):not(.h-10):not(.rounded-full):not(input):not(select):not(textarea):not(button):not(td):not(th) {
            background-color: var(--bg-card) !important;
            color: var(--text-primary) !important;
            border: none !important;
            box-shadow: var(--shadow) !important;
            border-radius: 20px !important;
            padding: 24px !important;
        }

        /* Cards Hover lift animation */
        main .bg-white:hover, 
        main .card:hover, 
        main [class*="bg-white"]:hover:not(.w-10):not(.h-10):not(.rounded-full):not(input):not(select):not(textarea):not(button):not(td):not(th) {
            transform: translateY(-2px) !important;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.05) !important;
        }

        html.dark main .bg-white:hover, 
        html.dark main .card:hover, 
        html.dark main [class*="bg-white"]:hover:not(.w-10):not(.h-10):not(.rounded-full):not(input):not(select):not(textarea):not(button):not(td):not(th) {
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.35) !important;
        }

        /* Remove borders globally from main wrappers */
        .border, .border-b, .border-t, .border-l, .border-r, .border-slate-100, .border-gray-200, .border-slate-200 {
            border: none !important;
        }

        /* Sidebar container styling (Clean dark/light surface, no right border) */
        .lg\:fixed.lg\:inset-y-0.lg\:z-40.lg\:flex.lg\:w-64.lg\:flex-col,
        .relative.mr-16.flex.w-full.max-w-xs,
        .sidebar-scroll {
            background-color: var(--bg-sidebar) !important;
            border: none !important;
            box-shadow: var(--shadow) !important;
        }
        .flex.h-16.flex-shrink-0.items-center.px-6.border-b {
            background-color: var(--bg-sidebar) !important;
            border: none !important;
        }

        /* Sidebar menu items */
        .sidebar-scroll a {
            height: 48px !important;
            border-radius: 12px !important;
            display: flex !important;
            align-items: center !important;
            padding-left: 16px !important;
            padding-right: 16px !important;
            border: none !important;
        }
        .sidebar-scroll a:hover {
            background-color: var(--bg-hover) !important;
            color: var(--text-primary) !important;
        }
        .sidebar-scroll a.bg-indigo-50,
        .sidebar-scroll a[class*="bg-[#2E3A5E]"] {
            background-color: var(--bg-active) !important;
            color: var(--primary) !important;
            border-left: 4px solid var(--primary) !important;
            border-radius: 12px !important;
        }
        html.dark .sidebar-scroll a.bg-indigo-50,
        html.dark .sidebar-scroll a[class*="bg-[#2E3A5E]"] {
            color: #FFFFFF !important;
        }

        /* Top Header Styling (no border, centered) */
        header {
            height: 72px !important;
            background-color: var(--bg-header) !important;
            border: none !important;
            box-shadow: var(--shadow) !important;
        }

        /* Responsive Content spacing */
        main {
            padding: 32px !important;
        }

        main .grid, main .flex-col {
            gap: 24px !important;
        }

        /* Tables overrides matching the ticketing dashboard (no borders, rounded floating rows) */
        table, .min-w-full {
            background-color: transparent !important;
            border: none !important;
            border-collapse: separate !important;
            border-spacing: 0 8px !important;
            width: 100% !important;
        }
        thead, th {
            background-color: transparent !important;
            color: var(--text-secondary) !important;
            border: none !important;
        }
        tbody tr {
            background-color: var(--bg-card) !important;
            border-radius: 12px !important;
            box-shadow: var(--shadow) !important;
            border: none !important;
        }
        tbody tr:hover {
            background-color: var(--bg-hover) !important;
        }
        tbody td {
            border: none !important;
            padding: 16px 20px !important;
        }
        tbody td:first-child {
            border-top-left-radius: 12px !important;
            border-bottom-left-radius: 12px !important;
        }
        tbody td:last-child {
            border-top-right-radius: 12px !important;
            border-bottom-right-radius: 12px !important;
        }

        /* Forms, inputs, textareas, selects, search box */
        input[type="text"],
        input[type="email"],
        input[type="password"],
        select,
        textarea,
        .w-full.bg-slate-50\/50 {
            background-color: var(--bg-app) !important;
            color: var(--text-primary) !important;
            border: 1px solid var(--border-soft) !important;
            border-radius: 12px !important;
            padding: 10px 14px !important;
            outline: none !important;
        }
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        select:focus,
        textarea:focus {
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 3px rgba(91, 95, 246, 0.15) !important;
        }

        /* Buttons matching Enterprise UI */
        .bg-indigo-600,
        button[type="submit"].bg-indigo-600,
        a.bg-indigo-600,
        button.bg-indigo-600 {
            background-color: var(--primary) !important;
            color: #FFFFFF !important;
            font-weight: 500 !important;
            border-radius: 12px !important;
            height: 44px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 0 20px !important;
            border: none !important;
            box-shadow: 0 4px 14px rgba(91, 95, 246, 0.2) !important;
        }
        .bg-indigo-600:hover,
        button[type="submit"].bg-indigo-600:hover,
        a.bg-indigo-600:hover,
        button.bg-indigo-600:hover {
            filter: brightness(1.08) !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 6px 20px rgba(91, 95, 246, 0.3) !important;
        }

        /* Secondary Button */
        .border-gray-300,
        .border-slate-300,
        .bg-white.border.border-gray-300,
        .bg-white.border.border-slate-300,
        a.border-gray-300 {
            background-color: var(--bg-card) !important;
            border: 1px solid var(--border-soft) !important;
            color: var(--text-primary) !important;
            border-radius: 12px !important;
            height: 44px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 0 20px !important;
        }
        .border-gray-300:hover,
        .border-slate-300:hover,
        .bg-white.border.border-gray-300:hover,
        a.border-gray-300:hover {
            background-color: var(--bg-hover) !important;
            transform: translateY(-1px) !important;
        }

        /* Danger buttons */
        .bg-red-600, .bg-rose-600, .bg-red-500, .bg-rose-500 {
            background-color: var(--danger) !important;
            color: #FFFFFF !important;
            border-radius: 12px !important;
            height: 44px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 0 20px !important;
            border: none !important;
        }

        /* Text color variables overrides */
        .text-slate-900, .text-gray-900, .text-slate-955, .text-slate-950, .text-gray-955, .text-slate-950, .text-gray-950, h1, h2, h3, h4, h5, h6 {
            color: var(--text-primary) !important;
        }
        .text-slate-500, .text-gray-500, .text-slate-600, .text-gray-600, .text-slate-700, .text-gray-700 {
            color: var(--text-secondary) !important;
        }

        /* License alerts custom styled left border */
        .border-rose-100.bg-rose-50\/50, .bg-rose-50\/50 {
            background-color: rgba(239, 68, 68, 0.05) !important;
            border-left: 4px solid var(--danger) !important;
            border-radius: 12px !important;
        }
        .border-amber-100.bg-amber-50\/50, .bg-amber-50\/50 {
            background-color: rgba(245, 158, 11, 0.05) !important;
            border-left: 4px solid var(--warning) !important;
            border-radius: 12px !important;
        }
        .border-emerald-100.bg-emerald-50\/50, .bg-emerald-50\/50 {
            background-color: rgba(34, 197, 94, 0.05) !important;
            border-left: 4px solid var(--success) !important;
            border-radius: 12px !important;
        }

        /* Plan progress bar and legends */
        .flex.rounded-full.overflow-hidden.h-3.bg-slate-100 {
            background-color: var(--bg-hover) !important;
            height: 12px !important;
            border-radius: 9999px !important;
        }
        .bg-\[\#4F46E5\] {
            background-color: var(--primary) !important;
        }
        .bg-slate-50\/30, .bg-slate-50\/50, .bg-gray-50\/30, .bg-gray-50\/50 {
            background-color: var(--bg-hover) !important;
            border-radius: 12px !important;
        }

        /* Icons standardization */
        .sidebar-scroll svg {
            width: 20px !important;
            height: 20px !important;
        }
        header svg {
            width: 20px !important;
            height: 20px !important;
        }
        .grid .p-2 svg {
            width: 18px !important;
            height: 18px !important;
        }
        table svg {
            width: 16px !important;
            height: 16px !important;
        }
        .grid .p-2 {
            width: 40px !important;
            height: 40px !important;
            border-radius: 12px !important;
            background-color: rgba(91, 95, 246, 0.12) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            border: none !important;
        }

        /* Prevent link clicking lock indicator */
        .loading-disabled {
            pointer-events: none !important;
            opacity: 0.6 !important;
        }

        /* Page Fade-in entry animation */
        @keyframes pageFadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }
        main {
            animation: pageFadeIn 0.2s ease-out forwards !important;
        }

        /* Dropdowns, Modals, Dialogs */
        .absolute.right-0.mt-2\.5.w-52.rounded-2xl,
        .modal-content, 
        [role="dialog"] .bg-white, 
        .fixed .bg-white {
            background-color: var(--bg-card) !important;
            border: none !important;
            box-shadow: var(--shadow) !important;
            border-radius: 16px !important;
        }

        /* Icons: Set colors dynamically */
        svg:not(.lucide-sun):not(.lucide-moon):not(.text-rose-600):not(.text-emerald-600) {
            color: var(--text-secondary) !important;
            stroke: var(--text-secondary) !important;
        }

        /* Custom Scrollbar */
        .sidebar-scroll::-webkit-scrollbar {
            width: 4px;
        }
        .sidebar-scroll::-webkit-scrollbar-thumb {
            background-color: var(--border-soft);
            border-radius: 4px;
        }

        /* Bell swing animation keyframes */
        @keyframes swing {
            0%, 100% { transform: rotate(0deg); }
            20% { transform: rotate(15deg); }
            40% { transform: rotate(-10deg); }
            60% { transform: rotate(5deg); }
            80% { transform: rotate(-5deg); }
        }
        .animate-swing {
            animation: swing 0.6s ease-out !important;
            transform-origin: top center !important;
        }
    </style>
</head>

<body class="h-full font-sans antialiased text-[#F8FAFC]" x-data="{ mobileSidebarOpen: false, searchOpen: false }" @keydown.window.prevent.ctrl.k="searchOpen = !searchOpen" @keydown.window.prevent.meta.k="searchOpen = !searchOpen">

    <!-- Stripe-style top loading progress bar -->
    <div id="top-loading-bar" class="fixed top-0 left-0 h-1 bg-[#5B5FF6] transition-all duration-300 z-50" style="width: 0%; display: none;"></div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const bar = document.getElementById('top-loading-bar');
            const links = document.querySelectorAll('a:not([href^="#"]):not([onclick]):not([target="_blank"])');
            links.forEach(link => {
                link.addEventListener('click', (e) => {
                    if (link.classList.contains('loading-disabled')) {
                        e.preventDefault();
                        return;
                    }
                    link.classList.add('loading-disabled');
                    bar.style.display = 'block';
                    bar.style.width = '30%';
                    setTimeout(() => { bar.style.width = '70%'; }, 150);
                    setTimeout(() => { bar.style.width = '95%'; }, 400);
                });
            });
        });
    </script>

    <!-- Mobile Sidebar Drawer (Off-canvas) -->
    <div x-show="mobileSidebarOpen" class="relative z-50 lg:hidden" role="dialog" aria-modal="true" x-cloak>
        <!-- Backdrop -->
        <div x-show="mobileSidebarOpen" 
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm"
             @click="mobileSidebarOpen = false"></div>

        <div class="fixed inset-0 flex">
            <!-- Sidebar Content Drawer -->
            <div x-show="mobileSidebarOpen" 
                 x-transition:enter="transition ease-in-out duration-300 transform"
                 x-transition:enter-start="-translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in-out duration-300 transform"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="-translate-x-full"
                 class="relative mr-16 flex w-full max-w-xs flex-1 flex-col bg-white border-r border-slate-100 pt-5 pb-4">
                
                <!-- Close Button -->
                <div class="absolute top-0 right-0 -mr-12 pt-2">
                    <button type="button" @click="mobileSidebarOpen = false" class="ml-1 flex h-10 w-10 items-center justify-center rounded-full focus:outline-none focus:ring-2 focus:ring-white">
                        <span class="sr-only">Close sidebar</span>
                        <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Logo -->
                <div class="flex flex-shrink-0 items-center px-6">
                    <div class="flex items-center gap-2.5">
                        <div class="h-9 w-9 rounded-xl bg-[#5B5FF6] flex items-center justify-center text-white font-bold text-xl shadow-sm">C</div>
                        <div>
                            <span class="text-sm font-bold text-[var(--text-primary)] tracking-tight">CliqueHA</span>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <span style="color: #A6571B; background-color: #D6CBAE; border-radius: 9999px; padding: 4px 14px; font-family: 'Inter', sans-serif; font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; display: inline-flex; align-items: center; justify-content: center; height: 22px; line-height: 1;" class="tracking-wider uppercase">Admin</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation List -->
                <div class="mt-8 h-0 flex-1 overflow-y-auto px-4 sidebar-scroll">
                    @include('partials.admin-sidebar-menu')
                </div>

                <!-- User Profile at bottom -->
                @include('partials.admin-sidebar-profile')
            </div>
        </div>
    </div>

    <!-- Static Desktop Sidebar -->
    <div class="hidden lg:fixed lg:inset-y-0 lg:z-40 lg:flex lg:w-64 lg:flex-col bg-white border-r border-slate-100 shadow-sm">
        <!-- Logo Header -->
        <div class="flex h-[72px] flex-shrink-0 items-center px-6 bg-[var(--bg-sidebar)]">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5">
                <div class="h-9 w-9 rounded-xl bg-[#5B5FF6] flex items-center justify-center text-white font-bold text-xl shadow-sm">C</div>
                <div>
                    <span class="text-sm font-bold text-[var(--text-primary)] tracking-tight">CliqueHA</span>
                    <div class="flex items-center gap-1.5 mt-0.5">
                        <span style="color: #A6571B; background-color: #D6CBAE; border-radius: 9999px; padding: 4px 14px; font-family: 'Inter', sans-serif; font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; display: inline-flex; align-items: center; justify-content: center; height: 22px; line-height: 1;" class="tracking-wider uppercase">Admin</span>
                    </div>
                </div>
            </a>
        </div>

        <!-- Sidebar Navigation -->
        <div class="flex flex-1 flex-col overflow-y-auto px-4 py-6 sidebar-scroll">
            @include('partials.admin-sidebar-menu')
        </div>

        <!-- User Profile at bottom -->
        @include('partials.admin-sidebar-profile')
    </div>

    <!-- Main Container -->
    <div class="flex flex-col min-h-screen lg:pl-64">
        <!-- Slim Top Header -->
        <header class="sticky top-0 z-30 flex h-[72px] flex-shrink-0 bg-[var(--bg-header)] px-4 sm:px-6 lg:px-8">
            <div class="flex flex-1 items-center justify-between">
                
                <!-- Page Title / Left Area -->
                <div class="flex items-center gap-4">
                    <button type="button" @click="mobileSidebarOpen = true" class="text-slate-500 hover:text-slate-700 lg:hidden p-1.5 rounded-lg hover:bg-slate-50">
                        <span class="sr-only">Open sidebar</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                    
                    <h1 class="text-sm font-bold text-[var(--text-primary)] tracking-tight">
                        @yield('title', 'Admin')
                    </h1>
                </div>

                <!-- Right Header Area (Search, Theme, Notification, Avatar) -->
                <div class="flex items-center gap-4">
                    
                    <!-- Search Bar -->
                    <div class="relative hidden sm:block w-64">
                        <div class="pointer-events-none absolute left-[16px] top-1/2 -translate-y-1/2 flex items-center justify-center">
                            <svg class="h-[20px] w-[20px] text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input type="text" @click="searchOpen = true" readonly placeholder="Search (Ctrl + K)" style="height: 44px; border-radius: 14px; padding-left: 44px; padding-right: 16px; font-size: 14px; font-weight: 400;" class="w-full bg-[var(--bg-input)] border border-[var(--border-soft)] text-[var(--text-primary)] placeholder-[var(--text-secondary)] focus:outline-none focus:border-[var(--primary)] focus:ring-1 focus:ring-[var(--primary)] transition-all cursor-pointer">
                    </div>

                    <!-- Theme Toggle -->
                    <div x-data="{
                        theme: localStorage.theme || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'),
                        init() {
                            window.addEventListener('theme-changed', (e) => {
                                this.theme = e.detail;
                            });
                        },
                        toggleTheme() {
                            const nextTheme = this.theme === 'dark' ? 'light' : 'dark';
                            applyTheme(nextTheme);
                        }
                    }">
                        <button @click="toggleTheme()" 
                                class="w-10 h-10 rounded-full bg-transparent hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors duration-200 flex items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer" 
                                aria-label="Toggle Theme">
                            <template x-if="theme === 'dark'">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sun text-amber-500"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
                            </template>
                            <template x-if="theme === 'light'">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-moon text-slate-500"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                            </template>
                        </button>
                    </div>

                    <!-- Notification Bell with Dropdown -->
                    <div class="relative" x-data="{
                        open: false,
                        notifications: [],
                        unreadCount: 0,
                        loading: false,
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

                            // SVG Icons
                            const ticket = `<svg class='h-4.5 w-4.5 ${color}' fill='none' viewBox='0 0 24 24' stroke='currentColor' stroke-width='2.5'><path stroke-linecap='round' stroke-linejoin='round' d='M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-12v.75m0 3v.75m0 3v.75m0 3V18M3 6h18v12H3V6z' /></svg>`;
                            const announcement = `<svg class='h-4.5 w-4.5 ${color}' fill='none' viewBox='0 0 24 24' stroke='currentColor' stroke-width='2.5'><path stroke-linecap='round' stroke-linejoin='round' d='m3 11 18-5v12L3 13v-2z'/><path stroke-linecap='round' stroke-linejoin='round' d='M11.6 16.8a3 3 0 1 1-5.8-1.6'/></svg>`;
                            const clock = `<svg class='h-4.5 w-4.5 ${color}' fill='none' viewBox='0 0 24 24' stroke='currentColor' stroke-width='2.5'><circle cx='12' cy='12' r='10'/><polyline points='12 6 12 12 16 14'/></svg>`;
                            const shield = `<svg class='h-4.5 w-4.5 ${color}' fill='none' viewBox='0 0 24 24' stroke='currentColor' stroke-width='2.5'><path stroke-linecap='round' stroke-linejoin='round' d='M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z' /></svg>`;
                            const warning = `<svg class='h-4.5 w-4.5 ${color}' fill='none' viewBox='0 0 24 24' stroke='currentColor' stroke-width='2.5'><path stroke-linecap='round' stroke-linejoin='round' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z' /></svg>`;
                            const info = `<svg class='h-4.5 w-4.5 ${color}' fill='none' viewBox='0 0 24 24' stroke='currentColor' stroke-width='2.5'><circle cx='12' cy='12' r='10'/><line x1='12' y1='16' x2='12' y2='12'/><line x1='12' y1='8' x2='12.01' y2='8'/></svg>`;

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
                        fetchCount() {
                            fetch('{{ route('admin.notifications.unreadCount') }}')
                                .then(res => res.json())
                                .then(data => {
                                    this.unreadCount = data.count;
                                });
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
                                this.unreadCount = 0;
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
                                if (this.unreadCount > 0) this.unreadCount--;
                                window.dispatchEvent(new CustomEvent('notifications-updated'));
                                window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Notification marked as read', type: 'success' } }));
                            });
                        },
                        init() {
                            this.fetchCount();
                            this.fetchNotifications();
                            setInterval(() => { 
                                this.fetchCount(); 
                                if (this.open) this.fetchNotifications();
                            }, 30000);
                            window.addEventListener('focus', () => { this.fetchCount(); this.fetchNotifications(); });
                            window.addEventListener('notifications-updated', () => {
                                this.fetchCount();
                                this.fetchNotifications();
                            });
                        }
                    }">
                        <!-- Bell trigger button -->
                        <button @click="open = !open; if(open) fetchNotifications()" 
                                class="relative p-1.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-[var(--bg-hover)] transition-colors focus:outline-none cursor-pointer"
                                aria-label="Notifications">
                            <svg class="h-5 w-5" :class="unreadCount > 0 ? 'animate-swing' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                            </svg>
                            <!-- Animated Pulse Unread Count Badge -->
                            <template x-if="unreadCount > 0">
                                <span class="absolute top-1 right-1 flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                                </span>
                            </template>
                        </button>

                        <!-- Float Dropdown Panel (Desktop) / Bottom Sheet (Mobile) -->
                        <div x-show="open" 
                             @click.away="open = false"
                             @keydown.escape.window="open = false"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95 translate-y-2 sm:translate-y-0"
                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                             x-transition:leave-end="opacity-0 scale-95 translate-y-2 sm:translate-y-0"
                             class="fixed inset-x-0 bottom-0 sm:absolute sm:inset-auto sm:right-0 sm:top-full mt-3 w-full sm:w-[420px] rounded-t-2xl sm:rounded-2xl bg-[var(--bg-card)] border border-[var(--border-soft)] shadow-2xl z-50 overflow-hidden max-h-[85vh] sm:max-h-[520px] flex flex-col" 
                             x-cloak>
                            
                            <!-- Dropdown Header -->
                            <div class="px-4 py-3.5 border-b border-[var(--border-soft)] flex items-center justify-between bg-[var(--bg-hover)]/30">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-extrabold text-[var(--text-primary)]">Notifications</span>
                                    <template x-if="unreadCount > 0">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#5B5FF6] text-white" x-text="unreadCount"></span>
                                    </template>
                                </div>
                                <div class="flex items-center gap-3">
                                    <template x-if="unreadCount > 0">
                                        <button @click="markAllRead()" class="text-[11px] font-bold text-[var(--primary)] hover:underline flex items-center gap-1">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4" /></svg>
                                            <span>Mark all read</span>
                                        </button>
                                    </template>
                                    
                                    <!-- Settings shortcut -->
                                    <a href="{{ route('admin.settings.index') }}" @click="open = false" class="text-slate-400 hover:text-slate-650" title="Settings">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.59 4.59A2 2 0 1111 8H2m10.59 11.41A2 2 0 1113 16H2m15.59-7.41A2 2 0 1119 12H2" /></svg>
                                    </a>
                                    
                                    <!-- Close button for bottom sheet on Mobile -->
                                    <button @click="open = false" class="sm:hidden text-slate-400 hover:text-slate-650">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Notifications Scrollable Area -->
                            <div class="overflow-y-auto divide-y divide-[var(--border-soft)] flex-1">
                                <template x-if="loading">
                                    <div class="p-6 space-y-4">
                                        <div class="h-12 bg-[var(--bg-hover)] animate-pulse rounded-xl"></div>
                                        <div class="h-12 bg-[var(--bg-hover)] animate-pulse rounded-xl"></div>
                                        <div class="h-12 bg-[var(--bg-hover)] animate-pulse rounded-xl"></div>
                                    </div>
                                </template>
                                <template x-if="!loading && notifications.length === 0">
                                    <div class="p-12 text-center flex flex-col items-center justify-center">
                                        <svg class="h-12 w-12 text-slate-300 dark:text-slate-700 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>
                                        <h4 class="text-xs font-bold text-[var(--text-primary)]">You're all caught up!</h4>
                                        <p class="text-[10px] text-[var(--text-secondary)] mt-1">There are currently no notifications.</p>
                                    </div>
                                </template>
                                <template x-if="!loading && notifications.length > 0">
                                    <template x-for="n in notifications.slice(0, 5)" :key="n.id">
                                        <div @click="markAsRead(n)" 
                                             class="p-4 flex gap-3 transition-all hover:bg-[var(--bg-hover)] cursor-pointer relative"
                                             :class="!n.read_at ? 'bg-indigo-50/5 dark:bg-indigo-500/5' : ''">
                                            
                                            <!-- Color left border indicator -->
                                            <div class="absolute left-0 top-0 bottom-0 w-1 rounded-r-md" :class="!n.read_at ? 'bg-[#5B5FF6]' : 'bg-transparent'"></div>
                                            
                                            <!-- Type Icon Box -->
                                            <div class="h-8 w-8 rounded-lg flex items-center justify-center shrink-0" x-html="getIcon(n)"></div>
                                            
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-start justify-between gap-2">
                                                    <p class="text-xs font-extrabold text-[var(--text-primary)] leading-tight" :class="!n.read_at ? 'font-bold' : 'font-normal text-[var(--text-secondary)]'" x-text="n.title"></p>
                                                    <span class="text-[9px] text-[var(--text-secondary)] shrink-0 font-semibold" :title="n.created_at || ''" x-text="n.created_ago"></span>
                                                </div>
                                                <p class="text-xs text-[var(--text-secondary)] mt-1 leading-relaxed break-words line-clamp-2" x-text="n.subject"></p>
                                                
                                                <!-- Category Badge -->
                                                <div class="mt-2.5 flex items-center justify-between">
                                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-[var(--bg-hover)] text-[var(--text-secondary)] border border-[var(--border-soft)]" x-text="n.type"></span>
                                                    
                                                    <!-- Action button if url exists -->
                                                    <template x-if="n.url">
                                                        <a :href="n.url" class="inline-flex items-center gap-1 text-[10px] font-bold text-[var(--primary)] hover:underline" @click.stop="markAsRead(n)">
                                                            <span>Open Ticket</span>
                                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                                                        </a>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </template>
                            </div>

                            <!-- Dropdown Footer -->
                            <div class="p-3 border-t border-[var(--border-soft)] text-center bg-[var(--bg-hover)]/30 shrink-0">
                                <a href="{{ route('admin.notifications.index') }}" @click="open = false" class="text-xs font-extrabold text-[var(--primary)] hover:underline block">
                                    View All Notifications
                                </a>
                            </div>

                        </div>
                    </div>

                    <div class="h-6 w-px bg-slate-100 dark:bg-slate-800"></div>

                    <!-- User Avatar & Dropdown -->
                    <div class="relative" x-data="{ userMenuOpen: false }">
                        <button @click="userMenuOpen = !userMenuOpen" @click.away="userMenuOpen = false" class="flex items-center gap-2 p-1 rounded-xl hover:bg-[var(--bg-hover)] transition-colors">
                            <div class="h-8 w-8 rounded-xl bg-[var(--primary)] flex items-center justify-center text-white font-bold text-sm shadow-sm">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </div>
                            <span class="hidden md:block text-xs font-semibold text-[var(--text-primary)] pr-1">{{ auth()->user()->name }}</span>
                        </button>
                        
                        <div x-show="userMenuOpen" 
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="transform opacity-0 scale-95"
                             x-transition:enter-end="transform opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="transform opacity-100 scale-100"
                             x-transition:leave-end="transform opacity-0 scale-95"
                             class="absolute right-0 mt-2.5 w-52 rounded-2xl bg-white border border-slate-100 shadow-lg p-2 z-50" x-cloak>
                            <div class="px-3 py-2 border-b border-slate-50 text-left">
                                <p class="text-xs font-semibold text-slate-900">Administrator</p>
                                <p class="text-[10px] text-slate-400 truncate mt-0.5">{{ auth()->user()->email }}</p>
                            </div>
                            <form method="POST" action="{{ route('logout') }}" class="mt-1">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-medium text-red-600 rounded-xl hover:bg-red-50/50 transition-colors">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                                    </svg>
                                    Log Out
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </header>

        <!-- Flash alerts / Errors display -->
        <div class="px-4 sm:px-6 lg:px-8 mt-6">
            @if(session('success'))
                <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4 text-sm text-emerald-800 flex items-start gap-3 shadow-sm shadow-emerald-50">
                    <svg class="h-5 w-5 text-emerald-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="rounded-2xl border border-rose-100 bg-rose-50/50 p-4 text-sm text-rose-800 flex items-start gap-3 shadow-sm shadow-rose-50">
                    <svg class="h-5 w-5 text-rose-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-2xl border border-rose-100 bg-rose-50/50 p-4 text-sm text-rose-800 flex items-start gap-3 shadow-sm shadow-rose-50">
                    <svg class="h-5 w-5 text-rose-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                    <div>
                        <span class="font-semibold">Please correct the following errors:</span>
                        <ul class="list-disc ml-5 mt-1.5 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
        </div>

        <!-- Main Content Area -->
        <main class="flex-1 py-8 px-4 sm:px-6 lg:px-8">
            @yield('content')
        </main>
    </div>

    <!-- Global Command Palette Search Modal -->
    <div x-show="searchOpen" 
         class="fixed inset-0 z-50 flex items-start justify-center p-4 sm:p-10 pt-20 bg-slate-950/60 backdrop-blur-sm" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-cloak>
        
        <!-- Palette container -->
        <div @click.away="searchOpen = false"
             @keydown.escape.window="searchOpen = false"
             class="bg-[var(--bg-card)] border border-[var(--border-soft)] w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[500px]"
             x-data="{
                searchQuery: '',
                selectedIndex: 0,
                registry: [
                    { title: 'Users Profile Settings', category: 'Settings', url: '{{ route('admin.users.index') }}' },
                    { title: 'System announcements list', category: 'Announcements', url: '{{ route('admin.announcements.index') }}' },
                    { title: 'Create new announcement', category: 'Announcements', url: '{{ route('admin.announcements.create') }}' },
                    { title: 'License keys registry', category: 'Licenses', url: '{{ route('admin.licenses.index') }}' },
                    { title: 'Plans and tiers list', category: 'Plans', url: '{{ route('admin.plans.index') }}' },
                    { title: 'Tenants management details', category: 'Tenants', url: '{{ route('admin.tenants.index') }}' },
                    { title: 'Executive reports analytics', category: 'Reports', url: '{{ route('admin.reports.index') }}' },
                    { title: 'Help articles & tutorials', category: 'Help', url: '{{ route('admin.help.index') }}' }
                ],
                get results() {
                    if (!this.searchQuery) return this.registry.slice(0, 5);
                    const q = this.searchQuery.toLowerCase();
                    return this.registry.filter(item => 
                        item.title.toLowerCase().includes(q) || 
                        item.category.toLowerCase().includes(q)
                    );
                },
                navigateSelected() {
                    const selected = this.results[this.selectedIndex];
                    if (selected) {
                        window.location.href = selected.url;
                    }
                }
             }">
             
             <!-- Search header input -->
             <div class="relative flex items-center border-b border-[var(--border-soft)]">
                 <div class="absolute left-4 top-1/2 -translate-y-1/2 flex items-center justify-center">
                     <svg class="h-[20px] w-[20px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                 </div>
                 <input type="text" 
                        x-model="searchQuery" 
                        @keydown.arrow-down.prevent="selectedIndex = (selectedIndex + 1) % results.length"
                        @keydown.arrow-up.prevent="selectedIndex = (selectedIndex - 1 + results.length) % results.length"
                        @keydown.enter.prevent="navigateSelected()"
                        placeholder="Search users, tenants, announcements, help guides..." 
                        class="w-full bg-transparent border-0 text-[var(--text-primary)] placeholder-[var(--text-secondary)] text-sm focus:outline-none py-4 pl-12 pr-4"
                        x-init="$watch('searchOpen', value => { if(value) { setTimeout(() => $el.focus(), 50); } })"
                        id="global-search-input">
             </div>

             <!-- Search body results -->
             <div class="overflow-y-auto p-2 divide-y divide-[var(--border-soft)]">
                 <!-- No results empty state -->
                 <template x-if="results.length === 0">
                     <div class="p-8 text-center text-xs text-[var(--text-secondary)]">
                         No matching results found for "<span class="font-semibold" x-text="searchQuery"></span>"
                     </div>
                 </template>

                 <!-- Results list loop -->
                 <template x-for="(item, index) in results" :key="index">
                     <div @click="window.location.href = item.url"
                          @mouseenter="selectedIndex = index"
                          class="p-3 flex items-center justify-between rounded-xl cursor-pointer transition-colors"
                          :class="selectedIndex === index ? 'bg-[var(--bg-hover)]' : ''">
                         <div class="flex items-center gap-3">
                             <div class="h-7 w-7 rounded-lg bg-[var(--bg-active)] flex items-center justify-center text-[var(--primary)] font-bold text-[10px]" x-text="item.category.substring(0, 2).toUpperCase()"></div>
                             <span class="text-xs font-bold text-[var(--text-primary)]" x-text="item.title"></span>
                         </div>
                         <span class="text-[10px] uppercase font-bold text-[var(--text-secondary)] tracking-wide bg-[var(--bg-hover)] px-2 py-0.5 rounded-md" x-text="item.category"></span>
                     </div>
                 </template>
             </div>

             <!-- Search footer helper guides -->
             <div class="p-3 border-t border-[var(--border-soft)] flex items-center justify-between bg-[var(--bg-hover)]/30 text-[10px] text-[var(--text-secondary)] font-semibold">
                 <div class="flex items-center gap-3">
                     <span><kbd class="bg-[var(--bg-hover)] border border-[var(--border-soft)] rounded px-1.5 py-0.5">↑↓</kbd> Navigate</span>
                     <span><kbd class="bg-[var(--bg-hover)] border border-[var(--border-soft)] rounded px-1.5 py-0.5">Enter</kbd> Open Link</span>
                 </div>
                 <span><kbd class="bg-[var(--bg-hover)] border border-[var(--border-soft)] rounded px-1.5 py-0.5">Esc</kbd> Close</span>
             </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div x-data="{
            toasts: [],
            addToast(message, type = 'success') {
                const id = Date.now();
                this.toasts.push({ id, message, type });
                setTimeout(() => {
                    this.toasts = this.toasts.filter(t => t.id !== id);
                }, 3000);
            }
         }"
         @toast.window="addToast($event.detail.message, $event.detail.type)"
         class="fixed top-6 right-6 z-[100] flex flex-col gap-3 pointer-events-none">
        <template x-for="t in toasts" :key="t.id">
            <div x-transition:enter="transition ease-out duration-300 transform translate-y-[-10px] opacity-0"
                 x-transition:enter-end="translate-y-0 opacity-100"
                 x-transition:leave="transition ease-in duration-200 transform translate-x-[20px] opacity-0"
                 class="px-4 py-3 rounded-xl shadow-lg flex items-center gap-3 border text-xs font-bold pointer-events-auto"
                 :class="{
                     'bg-green-50 border-green-200 text-green-800 dark:bg-green-950/20 dark:border-green-800/30 dark:text-green-400': t.type === 'success',
                     'bg-red-50 border-red-200 text-red-800 dark:bg-red-950/20 dark:border-red-800/30 dark:text-red-400': t.type === 'danger',
                     'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-950/20 dark:border-blue-800/30 dark:text-blue-400': t.type === 'info'
                 }">
                 <template x-if="t.type === 'success'">
                     <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4" /></svg>
                 </template>
                 <template x-if="t.type === 'danger'">
                     <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                 </template>
                 <template x-if="t.type === 'info'">
                     <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                 </template>
                 <span x-text="t.message"></span>
            </div>
        </template>
    </div>

</body>
</html>