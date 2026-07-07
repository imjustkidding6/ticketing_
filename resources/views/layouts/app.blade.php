@php
    $brandTenant = session('current_tenant_id') ? \App\Models\Tenant::find(session('current_tenant_id')) : null;
    $brandPrimary = $brandTenant?->primary_color ?? '#4f46e5';
    $brandAccent = $brandTenant?->accent_color ?? '#4338ca';
    $brandDarkPrimary = $brandTenant?->dark_primary_color ?? '#818cf8';
    $brandDarkAccent = $brandTenant?->dark_accent_color ?? '#6366f1';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" :class="darkMode ? 'dark' : ''">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/png" href="{{ asset('cliqueha-logo.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('cliqueha-logo.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Branding & Theme -->
        <style>
            :root {
                --brand-primary: {{ $brandPrimary }};
                --brand-accent: {{ $brandAccent }};
            }

            /* Light mode brand overrides */
            .bg-indigo-600 { background-color: var(--brand-primary) !important; }
            .hover\:bg-indigo-500:hover { background-color: var(--brand-accent) !important; }
            .text-indigo-600 { color: var(--brand-primary) !important; }
            .text-indigo-700 { color: var(--brand-primary) !important; }
            .hover\:text-indigo-900:hover { color: var(--brand-accent) !important; }
            .hover\:text-indigo-800:hover { color: var(--brand-accent) !important; }
            .bg-indigo-50 { background-color: color-mix(in srgb, var(--brand-primary) 10%, white) !important; }
            .border-indigo-500 { border-color: var(--brand-primary) !important; }
            .border-indigo-400 { border-color: var(--brand-primary) !important; }
            .focus\:border-indigo-500:focus { border-color: var(--brand-primary) !important; }
            .focus\:ring-indigo-500:focus { --tw-ring-color: var(--brand-primary) !important; }
            .text-indigo-500 { color: var(--brand-primary) !important; }

            /* Dark mode overrides */
            .dark { --brand-primary: {{ $brandDarkPrimary }}; --brand-accent: {{ $brandDarkAccent }}; }
            .dark body { background-color: #111827; color: #f3f4f6; }
            .dark .bg-gray-100 { background-color: #111827; }
            .dark .bg-white { background-color: #1f2937; }
            .dark .bg-gray-50 { background-color: #1f2937; }
            .dark .text-gray-900 { color: #f9fafb; }
            .dark .text-gray-800 { color: #f3f4f6; }
            .dark .text-gray-700 { color: #d1d5db; }
            .dark .text-gray-600 { color: #9ca3af; }
            .dark .text-gray-500 { color: #9ca3af; }
            .dark .text-gray-400 { color: #6b7280; }
            .dark .border-gray-200 { border-color: #374151; }
            .dark .border-gray-300 { border-color: #4b5563; }
            .dark .divide-gray-200 > :not([hidden]) ~ :not([hidden]) { border-color: #374151; }
            .dark .shadow-sm { box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.3); }
            .dark .hover\:bg-gray-50:hover { background-color: #374151; }
            .dark input, .dark select, .dark textarea {
                background-color: #374151;
                border-color: #4b5563;
                color: #f3f4f6;
            }
            .dark thead.bg-gray-50 { background-color: #1f2937; }

            /* Add your new fixes here */
            .dark .bg-indigo-50 {
                background-color: color-mix(in srgb, var(--brand-primary) 16%, #111827) !important;
            }

            .dark .text-indigo-700,
            .dark .text-indigo-600,
            .dark .text-indigo-500 {
                color: var(--brand-primary) !important;
            }

            .dark .bg-indigo-100 {
                background-color: color-mix(in srgb, var(--brand-primary) 20%, #1f2937) !important;
            }

            .dark .text-indigo-800 {
                color: #c7d2fe !important;
            }

            .dark .bg-orange-50 {
                background-color: #431407 !important;
            }

            .dark .text-orange-800,
            .dark .dark\:text-orange-300 {
                color: #fdba74 !important;
            }

            .dark .border-orange-400 {
                border-color: #fb923c !important;
            }

            .dark ::-webkit-scrollbar {
                width: 10px;
                height: 10px;
            }

            .dark ::-webkit-scrollbar-track {
                background: #111827;
            }

            .dark ::-webkit-scrollbar-thumb {
                background: #475569;
                border-radius: 999px;
                border: 2px solid #111827;
            }

            .dark ::-webkit-scrollbar-thumb:hover {
                background: #64748b;
            }
        </style>
        <script>
            // Init dark mode before render to prevent flash
            (function() {
                var dm = localStorage.getItem('darkMode') === 'true';
                if (dm) document.documentElement.classList.add('dark');
            })();
        </script>
    </head>
    <body class="font-sans antialiased">
        @if(session('admin_impersonating'))
            <div class="bg-yellow-500 text-yellow-900 text-center py-2 px-4 text-sm font-medium relative z-100">
                You are viewing as tenant: <strong>{{ \App\Models\Tenant::find(session('current_tenant_id'))?->name }}</strong>
                <form action="{{ route('admin.stop-impersonation') }}" method="POST" class="inline ml-3">
                    @csrf
                    <button type="submit" class="underline font-semibold hover:text-yellow-800">Return to Admin</button>
                </form>
            </div>
        @endif
        <div x-data="{ sidebarOpen: false, sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === 'true', darkMode: localStorage.getItem('darkMode') === 'true' }" x-init="$watch('sidebarCollapsed', val => { localStorage.setItem('sidebarCollapsed', val); setTimeout(() => window.dispatchEvent(new Event('resize')), 350); }); $watch('darkMode', val => { localStorage.setItem('darkMode', val); val ? document.documentElement.classList.add('dark') : document.documentElement.classList.remove('dark'); window.dispatchEvent(new CustomEvent('theme-changed')); })" class="min-h-screen bg-gray-100">

            <!-- Mobile sidebar overlay -->
            <div x-show="sidebarOpen" x-transition:enter="transition-opacity ease-linear duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-linear duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-40 bg-gray-600/75 sm:hidden" @click="sidebarOpen = false" x-cloak></div>

            <!-- Sidebar -->
            <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full sm:translate-x-0'" class="fixed inset-y-0 left-0 z-50 bg-white border-r border-gray-200 transition-all duration-300 ease-in-out flex flex-col" :style="sidebarCollapsed ? 'width: 0; overflow: hidden; border: none;' : 'width: 16rem;'">

                <!-- Logo & Tenant -->
                <div class="flex flex-col border-b border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        @php
                            $sidebarLogo = null;
                            $sidebarCompanyName = config('app.name', 'CliqueHA Nexus');
                            $sidebarTenant = null;
                            $sidebarHasMultipleTenants = false;
                            $isAdminOrOwner = false;
                            $sidebarRole = null;
                            if (session('current_tenant_id') && Auth::check() && Auth::user()->currentTenant()) {
                                $sidebarTenant = Auth::user()->currentTenant();
                                $sidebarTenant->load('license.plan');
                                $sidebarHasMultipleTenants = Auth::user()->tenants()->count() > 1;
                                $sidebarRole = Auth::user()->roleInTenant($sidebarTenant);
                                $isAdminOrOwner = in_array($sidebarRole, ['owner', 'admin']);
                                if ($sidebarTenant->logo_path) {
                                    $sidebarLogo = Storage::disk('public')->url($sidebarTenant->logo_path);
                                }
                                $settingName = \App\Models\AppSetting::get('company_name');
                                $sidebarCompanyName = $settingName ?: $sidebarTenant->name;
                            }
                        @endphp
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 min-w-0">
                            <div class="flex h-8 w-8 items-center justify-center rounded-md overflow-hidden shrink-0">
                                <img src="{{ $sidebarLogo ?? '/cliqueha-logo.png' }}" alt="{{ $sidebarCompanyName }}" class="h-full w-full object-cover">
                            </div>
                            <div class="min-w-0">
                                <span class="block text-sm font-semibold text-gray-900 truncate">{{ $sidebarCompanyName }}</span>
                                @if($sidebarTenant?->license?->plan)
                                    @php
                                        $planName = strtolower($sidebarTenant->license->plan->name);
                                        $planBadge = match(true) {
                                            str_contains($planName, 'enterprise') => 'bg-amber-100 text-amber-700',
                                            str_contains($planName, 'business') => 'bg-blue-100 text-blue-700',
                                            default => 'bg-gray-100 text-gray-700',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-bold uppercase tracking-wide {{ $planBadge }}">
                                        {{ $sidebarTenant->license->plan->name }}
                                    </span>
                                @elseif($sidebarTenant)
                                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-red-100 text-red-700">
                                        {{ __('No Subscription') }}
                                    </span>
                                @endif
                            </div>
                        </a>
                        <div class="flex items-center gap-1 shrink-0">
                            @if($sidebarHasMultipleTenants)
                                <a href="{{ route('tenant.select') }}" class="rounded-md p-1 text-indigo-500 hover:bg-indigo-50" title="{{ __('Switch Tenant') }}">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                    </svg>
                                </a>
                            @endif
                            <button @click="sidebarOpen = false" class="sm:hidden rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                </div>

                <!-- Navigation Links -->
                <nav class="flex-1 overflow-y-auto p-4">
                    @php
                        $planService = app(\App\Services\PlanService::class);
                        $sidebarRole = $sidebarRole ?? (Auth::check() && Auth::user()->currentTenant() ? Auth::user()->roleInTenant(Auth::user()->currentTenant()) : null);
                        // Owners bypass every permission check; otherwise defer to Spatie.
                        $sidebarCan = fn (string $perm) => $sidebarRole === 'owner' || (Auth::user()?->can($perm) ?? false);
                    @endphp

                    {{-- ========== MAIN ========== --}}
                    <div class="space-y-1">
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('dashboard') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                            <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('dashboard') ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                            </svg>
                            {{ __('Dashboard') }}
                        </a>
                        @if($sidebarCan('view tickets'))
                        <a href="{{ route('tickets.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('tickets.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                            <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('tickets.*') ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z" />
                            </svg>
                            {{ __('Tickets') }}
                        </a>
                        @endif
                        @if($sidebarCan('manage clients'))
                        <a href="{{ route('clients.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('clients.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                            <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('clients.*') ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                            </svg>
                            {{ __('Clients') }}
                        </a>
                        @endif
                    </div>

                    {{-- ========== MANAGEMENT ========== --}}
                    @php $hasMgmt = $sidebarCan('manage users') || $sidebarCan('manage categories') || $sidebarCan('manage products') || ($planService->currentTenantHasFeature(\App\Enums\PlanFeature::DepartmentManagement) && $sidebarCan('manage departments')); @endphp
                    @if($hasMgmt)
                    <div class="mt-6">
                        <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Management') }}</p>
                        <div class="mt-2 space-y-1">
                            @if($sidebarCan('manage users'))
                            <a href="{{ route('members.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('members.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('members.*') ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                                </svg>
                                {{ __('User Management') }}
                            </a>
                            @endif
                            @if($sidebarCan('manage categories'))
                            <a href="{{ route('categories.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('categories.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('categories.*') ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z" />
                                </svg>
                                {{ __('Categories') }}
                            </a>
                            @endif
                            @if($sidebarCan('manage products'))
                            <a href="{{ route('products.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('products.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('products.*') ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                </svg>
                                {{ __('Products & Services') }}
                            </a>
                            @endif
                            @if($planService->currentTenantHasFeature(\App\Enums\PlanFeature::DepartmentManagement) && $sidebarCan('manage departments'))
                            <a href="{{ route('departments.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('departments.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('departments.*') ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                </svg>
                                {{ __('Departments') }}
                            </a>
                            @endif
                        </div>
                    </div>
                    @endif

                    {{-- ========== SLA (Business+) ========== --}}
                    @if($sidebarCan('manage sla') && $planService->currentTenantHasFeature(\App\Enums\PlanFeature::SlaManagement))
                    <div class="mt-6">
                        <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('SLA') }}</p>
                        <div class="mt-2 space-y-1">
                            <a href="{{ route('sla.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('sla.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('sla.*') ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                                </svg>
                                {{ __('SLA Policies') }}
                            </a>
                        </div>
                    </div>
                    @endif

                    {{-- ========== REPORTS ========== --}}
                    @if($sidebarCan('view reports'))
                    <div class="mt-6">
                        <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Reports') }}</p>
                        <div class="mt-2 space-y-1">
                            <a href="{{ route('reports.overview') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('reports.overview') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('reports.overview') ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                                </svg>
                                {{ __('Overview') }}
                            </a>
                            <a href="{{ route('reports.tickets') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('reports.tickets') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('reports.tickets') ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z" />
                                </svg>
                                {{ __('Tickets') }}
                            </a>
                            <a href="{{ route('reports.departments') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('reports.departments') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('reports.departments') ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
                                </svg>
                                {{ __('Departments') }}
                            </a>
                            <a href="{{ route('reports.categories') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('reports.categories') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('reports.categories') ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z" />
                                </svg>
                                {{ __('Categories') }}
                            </a>
                            <a href="{{ route('reports.clients') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('reports.clients') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('reports.clients') ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                </svg>
                                {{ __('Clients') }}
                            </a>
                            <a href="{{ route('reports.agents') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('reports.agents') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('reports.agents') ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                </svg>
                                {{ __('Agents') }}
                            </a>
                            <a href="{{ route('reports.products') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('reports.products') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('reports.products') ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                </svg>
                                {{ __('Products') }}
                            </a>
                            @if($planService->currentTenantHasFeature(\App\Enums\PlanFeature::Billing))
                            <a href="{{ route('reports.billing') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('reports.billing') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('reports.billing') ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                                </svg>
                                {{ __('Billing') }}
                            </a>
                            @endif
                            @if($planService->currentTenantHasFeature(\App\Enums\PlanFeature::SlaReport))
                            <a href="{{ route('reports.sla-compliance') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('reports.sla-compliance') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('reports.sla-compliance') ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                                </svg>
                                {{ __('SLA Compliance') }}
                            </a>
                            @endif
                            @if($planService->currentTenantHasFeature(\App\Enums\PlanFeature::ServiceReports))
                            <a href="{{ route('service-reports.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('service-reports.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('service-reports.*') ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                </svg>
                                {{ __('Service Reports') }}
                            </a>
                            @endif
                            @if($planService->currentTenantHasFeature(\App\Enums\PlanFeature::TicketReopening))
                            <a href="{{ route('reports.reopens') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('reports.reopens') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('reports.reopens') ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                                {{ __('Reopen Report') }}
                            </a>
                            @endif
                        </div>
                    </div>
                    @endif

                    {{-- ========== ACTIVITY LOGS (Business+) ========== --}}
                    @if($planService->currentTenantHasFeature(\App\Enums\PlanFeature::AuditLogs) && $sidebarCan('view activity logs'))
                    <div class="mt-6">
                        <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Audit') }}</p>
                        <div class="mt-2 space-y-1">
                            <a href="{{ route('activity-logs.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('activity-logs.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('activity-logs.*') ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ __('Activity Logs') }}
                            </a>
                        </div>
                    </div>
                    @endif

                    {{-- ========== SCHEDULE (Business+) ========== --}}
                    @if($planService->currentTenantHasFeature(\App\Enums\PlanFeature::AgentSchedule))
                    <div class="mt-6">
                        <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Schedule') }}</p>
                        <div class="mt-2 space-y-1">
                            <a href="{{ route('schedules.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('schedules.index') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('schedules.index') ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                </svg>
                                {{ __('My Schedule') }}
                            </a>
                            @if($sidebarCan('manage schedules'))
                            <a href="{{ route('schedules.team') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('schedules.team') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('schedules.team') ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                                </svg>
                                {{ __('Team Schedule') }}
                            </a>
                            @endif
                        </div>
                    </div>
                    @endif

                    {{-- ========== SETTINGS ========== --}}
                    @if($sidebarCan('manage settings') || $sidebarCan('manage roles'))
                    <div class="mt-6">
                        <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Settings') }}</p>
                        <div class="mt-2 space-y-1">
                            {{-- Starter --}}
                            <a href="{{ route('settings.general') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('settings.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('settings.*') ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                {{ __('App Settings') }}
                            </a>
                            {{-- Enterprise only --}}
                            @if($planService->currentTenantHasFeature(\App\Enums\PlanFeature::CustomRoles))
                            <a href="{{ route('roles.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('roles.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('roles.*') ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                                </svg>
                                {{ __('Roles & Permissions') }}
                            </a>
                            @endif
                        </div>
                    </div>
                    @endif

                    {{-- ========== HELP ========== --}}
                    <div class="mt-6">
                        <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Help') }}</p>
                        <div class="mt-2 space-y-1">
                            <a href="{{ route('tutorials.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('tutorials.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('tutorials.*') ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                                </svg>
                                {{ __('Help & Tutorials') }}
                            </a>
                        </div>
                    </div>

                    {{-- ========== ADMIN PANEL (global isAdmin only) ========== --}}
                    @if(Auth::user()?->isAdmin())
                    <div class="mt-6">
                        <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Admin') }}</p>
                        <div class="mt-2 space-y-1">
                            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('admin.*') ? 'text-indigo-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
                                </svg>
                                {{ __('Admin Panel') }}
                            </a>
                        </div>
                    </div>
                    @endif
                </nav>

                <!-- User Menu (bottom) -->
                @auth
                    <div x-data="{ userMenuOpen: false }" class="relative border-t border-gray-200 p-3">
                        <button @click="userMenuOpen = !userMenuOpen" class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left hover:bg-gray-50 transition-colors">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700 shrink-0">
                                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ Auth::user()->name }}</p>
                                <p class="text-xs text-gray-500 truncate">{{ Auth::user()->email }}</p>
                            </div>
                            <svg class="h-4 w-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </button>

                        <!-- Dropdown Menu -->
                        <div x-show="userMenuOpen" x-cloak @click.outside="userMenuOpen = false"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute bottom-full left-3 right-3 mb-1 rounded-lg border border-gray-200 bg-white py-1 shadow-lg z-50">
                            <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                </svg>
                                {{ __('Profile') }}
                            </a>
                            <div class="my-1 border-t border-gray-100"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                                    </svg>
                                    {{ __('Log Out') }}
                                </button>
                            </form>
                        </div>
                    </div>
                @endauth
            </aside>

            <!-- Main Content -->
            <div class="flex flex-col min-h-screen transition-all duration-300" :class="sidebarCollapsed ? '' : 'sm:ml-64'">
                <!-- Top bar -->
                <header class="sticky top-0 z-30 bg-white shadow">
                    <div class="flex items-center justify-between gap-4 px-4 py-3">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            {{-- Hamburger: mobile opens overlay, desktop toggles collapse --}}
                            <button @click="window.innerWidth < 640 ? (sidebarOpen = !sidebarOpen) : (sidebarCollapsed = !sidebarCollapsed)" class="shrink-0 text-gray-500 hover:text-gray-700 rounded-md p-1 hover:bg-gray-100">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                                </svg>
                            </button>
                            @isset($header)
                                <div class="flex-1 min-w-0">
                                    {{ $header }}
                                </div>
                            @endisset
                        </div>

                        <div class="flex items-center gap-1">
                        {{-- Theme Toggle --}}
                        <button @click="darkMode = !darkMode" class="rounded-full p-2 text-gray-400 hover:text-gray-600 focus:outline-none" title="Toggle theme">
                            <template x-if="!darkMode">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                                </svg>
                            </template>
                            <template x-if="darkMode">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                                </svg>
                            </template>
                        </button>

                        <!-- Notification Bell -->
                        @auth
                        <div x-data="notificationBell()" class="relative">
                            <button @click="toggle()" class="relative rounded-full p-2 text-gray-400 hover:text-gray-600 focus:outline-none">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                                </svg>
                                <span x-show="unreadCount > 0" x-cloak x-text="unreadCount > 99 ? '99+' : unreadCount" class="absolute -top-0.5 -right-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white"></span>
                            </button>

                            <div x-show="open" x-cloak @click.outside="open = false" x-transition class="absolute right-0 mt-2 rounded-xl border border-gray-200 bg-white shadow-xl z-50"
                                style="width: min(48rem, calc(100vw - 2rem)); min-width: 28rem;">
                                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                                    <span class="text-base font-semibold text-gray-900">{{ __('Notifications') }}</span>
                                    <button x-show="unreadCount > 0" @click="markAllRead()" class="text-sm text-indigo-600 hover:text-indigo-800">{{ __('Mark all read') }}</button>
                                </div>
                                <div class="max-h-128 overflow-y-auto">
                                    <template x-if="notifications.length === 0 && loaded">
                                        <p class="px-6 py-10 text-center text-sm text-gray-500">{{ __('No notifications.') }}</p>
                                    </template>
                                    <template x-for="n in notifications" :key="n.id">
                                        <div @click="openNotification(n)" class="cursor-pointer border-b border-gray-100 px-6 py-4 hover:bg-gray-50"
                                             :class="{
                                                'bg-blue-50': !n.read_at && n.action !== 'system_announcement',
                                                'bg-indigo-50/60 border-l-4 border-l-indigo-500': n.action === 'system_announcement' && !n.read_at,
                                                'border-l-4 border-l-indigo-200': n.action === 'system_announcement' && n.read_at,
                                             }">
                                            <div class="flex items-start gap-4">
                                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                                                    :class="{
                                                        'bg-indigo-100 text-indigo-600': n.action === 'assigned',
                                                        'bg-emerald-100 text-emerald-600': n.action === 'created',
                                                        'bg-blue-100 text-blue-600': n.action === 'status_changed',
                                                        'bg-amber-100 text-amber-600': n.action === 'sla_breach_warning',
                                                        'bg-purple-100 text-purple-600': n.action === 'escalated',
                                                        'bg-blue-100 text-blue-700': n.action === 'system_announcement' && n.severity === 'info',
                                                        'bg-emerald-100 text-emerald-700': n.action === 'system_announcement' && n.severity === 'update',
                                                        'bg-amber-100 text-amber-700': n.action === 'system_announcement' && n.severity === 'maintenance',
                                                        'bg-red-100 text-red-700': n.action === 'system_announcement' && n.severity === 'warning',
                                                        'bg-gray-100 text-gray-500': !['assigned','created','status_changed','sla_breach_warning','escalated','system_announcement'].includes(n.action),
                                                    }">
                                                    <template x-if="n.action === 'system_announcement'">
                                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 011.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 001.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 010 3.46" />
                                                        </svg>
                                                    </template>
                                                    <template x-if="n.action !== 'system_announcement'">
                                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                                                        </svg>
                                                    </template>
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div class="min-w-0">
                                                            <template x-if="n.action === 'system_announcement'">
                                                                <span class="inline-flex items-center rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-700 mr-1.5">System</span>
                                                            </template>
                                                            <p class="inline text-sm font-medium text-gray-900 leading-snug" x-text="n.title"></p>
                                                        </div>
                                                        <span x-show="!n.read_at" class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full bg-blue-500"></span>
                                                    </div>
                                                    <p x-show="n.subject"
                                                       class="mt-1 text-sm text-gray-600"
                                                       :class="n.action === 'system_announcement' ? 'whitespace-pre-line' : 'truncate'"
                                                       x-text="n.subject"></p>
                                                    <p class="mt-1.5 text-xs text-gray-400">
                                                        <span x-text="n.created_ago"></span>
                                                        <template x-if="n.action === 'system_announcement' && n.published_at_local">
                                                            <span class="ml-1 text-gray-300">&middot; <span x-text="n.published_at_local"></span></span>
                                                        </template>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                        @endauth
                        </div>
                    </div>
                </header>

                @if(session('success'))
                    <div class="px-4 mt-4">
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                            {{ session('success') }}
                        </div>
                    </div>
                @endif

                @if(session('error'))
                    <div class="px-4 mt-4">
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                            {{ session('error') }}
                        </div>
                    </div>
                @endif

                {{-- License expiration banner --}}
                @if($sidebarTenant?->license)
                    @php $lic = $sidebarTenant->license; @endphp
                    @if($lic->isInGracePeriod())
                        <div class="px-4 mt-4">
                            <div class="rounded-md border-l-4 border-orange-400 bg-orange-50 px-4 py-3 text-sm text-orange-800">
                                <strong>Subscription expired.</strong> You are in a grace period — service will be suspended in {{ $lic->daysUntilFullExpiry() }} day{{ $lic->daysUntilFullExpiry() === 1 ? '' : 's' }}. Please contact your administrator to renew.
                            </div>
                        </div>
                    @elseif($lic->status === \App\Models\License::STATUS_ACTIVE && $lic->daysUntilExpiry() <= 7 && ! $lic->isExpired())
                        <div class="px-4 mt-4">
                            <div class="rounded-md border-l-4 border-amber-400 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                <strong>Subscription expiring soon.</strong> Your subscription expires on @localdt($lic->expires_at, 'M j, Y') ({{ $lic->daysUntilExpiry() }} day{{ $lic->daysUntilExpiry() === 1 ? '' : 's' }} remaining). Please contact your administrator to renew.
                            </div>
                        </div>
                    @endif
                @endif

                <!-- Page Content -->
                <main class="flex-1">
                    {{ $slot }}
                </main>
            </div>

        </div>
        @stack('scripts')
        @auth
        <script>
            function notificationBell() {
                return {
                    open: false,
                    loaded: false,
                    notifications: [],
                    unreadCount: 0,
                    pollInterval: null,

                    init() {
                        this.fetchUnreadCount();
                        this.pollInterval = setInterval(() => this.fetchUnreadCount(), 30000);
                    },

                    async toggle() {
                        this.open = !this.open;
                        if (this.open && !this.loaded) {
                            await this.fetchNotifications();
                            this.loaded = true;
                        }
                    },

                    async fetchNotifications() {
                        try {
                            const res = await fetch('{{ route("notifications.recent") }}');
                            if (res.ok) this.notifications = await res.json();
                        } catch (e) {}
                    },

                    async fetchUnreadCount() {
                        try {
                            const res = await fetch('{{ route("notifications.unreadCount") }}');
                            if (res.ok) {
                                const data = await res.json();
                                this.unreadCount = data.count;
                            }
                        } catch (e) {}
                    },

                    async markRead(id) {
                        const n = this.notifications.find(n => n.id === id);
                        if (n && !n.read_at) {
                            n.read_at = new Date().toISOString();
                            this.unreadCount = Math.max(0, this.unreadCount - 1);
                            try {
                                await fetch('/notifications/' + id + '/read', {
                                    method: 'POST',
                                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
                                });
                            } catch (e) {}
                        }
                    },

                    async openNotification(n) {
                        await this.markRead(n.id);
                        if (n.url) {
                            window.location.href = n.url;
                        }
                    },

                    async markAllRead() {
                        this.notifications.forEach(n => n.read_at = n.read_at || new Date().toISOString());
                        this.unreadCount = 0;
                        try {
                            await fetch('{{ route("notifications.markAllRead") }}', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
                            });
                        } catch (e) {}
                    },
                };
            }
        </script>
        @endauth

        @auth
        @if(session('current_tenant_id'))
        @php $feedbackUrl = route('feedback.store'); @endphp
        {{-- Floating Feedback Button --}}
        <div x-data="{ feedbackOpen: false }" class="fixed z-50" style="bottom: 1.5rem; right: 1.5rem;">

            <div x-show="feedbackOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 translate-y-2"
                 x-cloak
                 class="mb-3 w-80 rounded-xl bg-white shadow-xl ring-1 ring-gray-200 overflow-hidden">

                <div class="flex items-center justify-between px-4 py-3 bg-indigo-600">
                    <span class="text-sm font-semibold text-white">Send Feedback</span>
                    <button @click="feedbackOpen = false" class="text-indigo-200 hover:text-white transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ $feedbackUrl }}" class="p-4 space-y-3">
                    @csrf
                    <select name="type" required
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="bug">Bug Report</option>
                        <option value="suggestion">Suggestion</option>
                        <option value="compliment">Compliment</option>
                        <option value="other">Other</option>
                    </select>
                    <input type="text" name="subject" maxlength="255"
                           placeholder="Subject (optional)"
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <textarea name="body" rows="4" required maxlength="3000"
                              placeholder="Describe your feedback..."
                              class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm resize-none"></textarea>
                    <button type="submit"
                            class="w-full rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500 transition-colors">
                        Submit
                    </button>
                </form>
            </div>

            <button @click="feedbackOpen = !feedbackOpen"
                    class="flex items-center gap-2 rounded-full bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-lg hover:bg-indigo-500 transition-colors">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                </svg>
                Feedback
            </button>
        </div>
        @endif

        {{-- In-app AI assistant (per-user memory) --}}
        @if(auth()->user()?->currentTenant() && app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::AiChatbot) && (bool) \App\Models\AppSetting::get('ai_enabled', false))
            @include('partials.app-ai-assistant')
        @endif
        @endauth
    </body>
</html>
