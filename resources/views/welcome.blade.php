<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Ticketing') }} - {{ __('Support Desk Platform') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-white">

        {{-- Header --}}
        <header class="bg-white border-b border-gray-100">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="h-8 w-8 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z" />
                        </svg>
                        <span class="text-xl font-bold text-gray-900">{{ config('app.name', 'Ticketing') }}</span>
                    </div>

                    <nav class="flex items-center gap-4">
                        @auth
                            @if(Auth::user()->isAdmin())
                                <a href="{{ route('admin.dashboard') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">{{ __('Admin Console') }}</a>
                            @else
                                <a href="{{ url('/dashboard') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">{{ __('Dashboard') }}</a>
                            @endif
                        @else
                            <a href="{{ route('login') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">{{ __('Log In') }}</a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Register') }}</a>
                            @endif
                        @endauth
                    </nav>
                </div>
            </div>
        </header>

        {{-- Hero --}}
        <section class="bg-gradient-to-br from-indigo-700 via-indigo-800 to-indigo-900 py-20 sm:py-28">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-center">
                <h1 class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl lg:text-6xl">
                    {{ __('Streamline Your Support') }}
                </h1>
                <p class="mx-auto mt-6 max-w-2xl text-lg text-indigo-200">
                    {{ __('A powerful, multi-tenant helpdesk platform to manage tickets, track SLAs, and deliver outstanding customer support — all from one place.') }}
                </p>
                <div class="mt-10 flex items-center justify-center gap-4">
                    @auth
                        @if(Auth::user()->isAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="rounded-md bg-white px-6 py-3 text-sm font-semibold text-indigo-700 shadow-sm hover:bg-indigo-50">
                                {{ __('Admin Console') }}
                            </a>
                        @else
                            <a href="{{ url('/dashboard') }}" class="rounded-md bg-white px-6 py-3 text-sm font-semibold text-indigo-700 shadow-sm hover:bg-indigo-50">
                                {{ __('Go to Dashboard') }}
                            </a>
                        @endif
                    @else
                        <a href="{{ route('register') }}" class="rounded-md bg-white px-6 py-3 text-sm font-semibold text-indigo-700 shadow-sm hover:bg-indigo-50">
                            {{ __('Get Started Free') }}
                        </a>
                        <a href="{{ route('login') }}" class="rounded-md border border-indigo-400 px-6 py-3 text-sm font-semibold text-white hover:bg-indigo-600">
                            {{ __('Sign In') }}
                        </a>
                    @endauth
                </div>
            </div>
        </section>

        {{-- Features --}}
        <section class="py-20 bg-gray-50">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <h2 class="text-3xl font-bold text-gray-900">{{ __('Everything You Need') }}</h2>
                    <p class="mt-4 text-lg text-gray-500">{{ __('Built for teams that take customer support seriously.') }}</p>
                </div>

                <div class="mt-16 grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    {{-- Feature 1 --}}
                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-100">
                            <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-gray-900">{{ __('Multi-Tenant') }}</h3>
                        <p class="mt-2 text-sm text-gray-500">{{ __('Isolated workspaces per organization. Each tenant gets their own data, settings, and client portal.') }}</p>
                    </div>

                    {{-- Feature 2 --}}
                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-100">
                            <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-gray-900">{{ __('SLA Management') }}</h3>
                        <p class="mt-2 text-sm text-gray-500">{{ __('Define response and resolution targets. Automatic escalation when SLAs are at risk.') }}</p>
                    </div>

                    {{-- Feature 3 --}}
                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-100">
                            <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-gray-900">{{ __('Departments & Routing') }}</h3>
                        <p class="mt-2 text-sm text-gray-500">{{ __('Organize agents into departments. Route tickets automatically to the right team.') }}</p>
                    </div>

                    {{-- Feature 4 --}}
                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-100">
                            <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-gray-900">{{ __('Reports & Analytics') }}</h3>
                        <p class="mt-2 text-sm text-gray-500">{{ __('Ticket volume, agent performance, SLA compliance — exportable reports to drive decisions.') }}</p>
                    </div>

                    {{-- Feature 5 --}}
                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-100">
                            <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-gray-900">{{ __('Client Portal') }}</h3>
                        <p class="mt-2 text-sm text-gray-500">{{ __('Branded self-service portal for your customers. Submit tickets, track progress — no login required.') }}</p>
                    </div>

                    {{-- Feature 6 --}}
                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-100">
                            <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-gray-900">{{ __('Roles & Permissions') }}</h3>
                        <p class="mt-2 text-sm text-gray-500">{{ __('Fine-grained access control with custom roles. Agent tiers and escalation paths.') }}</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- Pricing --}}
        <section class="py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <h2 class="text-3xl font-bold text-gray-900">{{ __('Plans & Pricing') }}</h2>
                    <p class="mt-4 text-lg text-gray-500">{{ __('Choose the plan that fits your team.') }}</p>
                </div>

                @php
                    $allFeatures = App\Enums\PlanFeature::cases();
                @endphp

                <div class="mt-16 grid grid-cols-1 gap-8 lg:grid-cols-3">
                    @foreach($plans as $plan)
                        @php
                            $isPopular = $plan->slug === 'business';
                        @endphp
                        <div class="relative rounded-2xl border {{ $isPopular ? 'border-indigo-600 shadow-lg' : 'border-gray-200 shadow-sm' }} bg-white p-8 flex flex-col">
                            @if($isPopular)
                                <div class="absolute -top-4 left-1/2 -translate-x-1/2">
                                    <span class="rounded-full bg-indigo-600 px-4 py-1 text-xs font-semibold text-white">{{ __('Most Popular') }}</span>
                                </div>
                            @endif

                            <div class="mb-6">
                                <h3 class="text-xl font-bold text-gray-900">{{ $plan->name }}</h3>
                                @if($plan->description)
                                    <p class="mt-2 text-sm text-gray-500">{{ $plan->description }}</p>
                                @endif
                            </div>

                            <div class="mb-6 space-y-2">
                                <div class="flex items-center gap-2 text-sm">
                                    <svg class="h-4 w-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                    </svg>
                                    <span class="text-gray-700">
                                        <span class="font-semibold">{{ $plan->hasUnlimitedUsers() ? __('Unlimited') : $plan->max_users }}</span> {{ __('users') }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 text-sm">
                                    <svg class="h-4 w-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z" />
                                    </svg>
                                    <span class="text-gray-700">
                                        <span class="font-semibold">{{ $plan->hasUnlimitedTickets() ? __('Unlimited') : number_format($plan->max_tickets_per_month) }}</span> {{ __('tickets/month') }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex-1">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-3">{{ __('Features') }}</p>
                                <ul class="space-y-2">
                                    @foreach($allFeatures as $feature)
                                        <li class="flex items-center gap-2 text-sm">
                                            @if($plan->hasFeature($feature))
                                                <svg class="h-4 w-4 shrink-0 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                </svg>
                                                <span class="text-gray-700">{{ $feature->label() }}</span>
                                            @else
                                                <svg class="h-4 w-4 shrink-0 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 12H6" />
                                                </svg>
                                                <span class="text-gray-400">{{ $feature->label() }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>

                            <div class="mt-8">
                                <a href="{{ route('register') }}" class="block w-full rounded-md {{ $isPopular ? 'bg-indigo-600 text-white hover:bg-indigo-500' : 'bg-gray-50 text-gray-900 hover:bg-gray-100 border border-gray-200' }} px-4 py-2.5 text-center text-sm font-semibold shadow-sm">
                                    {{ __('Get Started') }}
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Footer --}}
        <footer class="border-t border-gray-200 bg-gray-50 py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-center text-sm text-gray-500">
                &copy; {{ date('Y') }} CliqueHA Information Services OPC. {{ __('All rights reserved.') }}
            </div>
        </footer>

    </body>
</html>
