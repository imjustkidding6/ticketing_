<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $tenant->displayName() }} - {{ __('Support Portal') }}</title>

        <link rel="icon" type="image/png" href="{{ $tenant->logoUrl() ?? asset('cliqueha-logo.png') }}">
        <link rel="apple-touch-icon" href="{{ $tenant->logoUrl() ?? asset('cliqueha-logo.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Tenant Branding -->
        <style>
            :root {
                --portal-primary: {{ $tenant->primary_color ?? '#4f46e5' }};
                --portal-accent: {{ $tenant->accent_color ?? '#4338ca' }};
                /* The tenant's hue, darkened enough to sit on paper as text.
                   One injected colour, two roles: the header band and the
                   interactive accent. Nothing else is tinted. */
                --portal-on-paper: color-mix(in srgb, var(--portal-primary) 78%, #111827);
                --portal-wash: color-mix(in srgb, var(--portal-primary) 8%, #ffffff);
            }
            /* Display type. Figtree is kept on purpose: this page carries the
               TENANT's brand, and a characterful face of our own would compete
               with the one thing that has to come through. The personality
               comes from scale and spacing instead. */
            .portal-display {
                font-weight: 700;
                letter-spacing: -0.022em;
                line-height: 1.08;
            }
            .portal-lede { max-width: 60ch; }
            /* Visible keyboard focus, in the tenant's own colour. */
            .portal-focus:focus-visible {
                outline: 2px solid var(--portal-on-paper);
                outline-offset: 2px;
            }
            @media (prefers-reduced-motion: reduce) {
                * { transition-duration: 0.01ms !important; animation-duration: 0.01ms !important; }
            }
        </style>
    </head>
    <body class="font-sans antialiased bg-gray-50">
        <div class="min-h-screen flex flex-col">
            <!-- Portal Header -->
            <header class="border-b border-gray-200 shadow-sm text-white" style="background-color: var(--portal-primary);">
                <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
                    <div class="flex h-16 items-center justify-between">
                        <div class="flex items-center gap-4">
                            <a href="{{ route('tenant.landing', ['slug' => $tenant->slug]) }}" class="flex items-center gap-2">
                                @if($tenant->logo_path)
                                    <img src="{{ $tenant->logoUrl() }}" alt="{{ $tenant->displayName() }}" class="h-10 w-auto">
                                @else
                                    <svg class="h-8 w-8 text-white/80" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z" />
                                    </svg>
                                @endif
                                {{-- The name ONCE. The tab title already says
                                     "Support Portal" and so did this line and so
                                     did every page heading beneath it. --}}
                                <span class="truncate text-base font-semibold text-white sm:text-lg">{{ $tenant->displayName() }}</span>
                            </a>
                        </div>

                        @if(empty($hideNav))
                        <nav class="flex shrink-0 items-center gap-1 sm:gap-2">
                            <a href="{{ route('tenant.landing', ['slug' => $tenant->slug]) }}" class="portal-focus hidden rounded-md px-2.5 py-2 text-sm font-medium text-white/80 hover:bg-white/10 hover:text-white sm:inline-flex">{{ __('Home') }}</a>
                            <a href="{{ route('tenant.track-ticket', ['slug' => $tenant->slug]) }}" class="portal-focus rounded-md px-2.5 py-2 text-sm font-medium text-white/80 hover:bg-white/10 hover:text-white">{{ __('Track') }}</a>
                            {{-- Paper on colour: the one action we want is the one
                                 thing in the band that is not the band's colour. --}}
                            <a href="{{ route('tenant.submit-ticket', ['slug' => $tenant->slug]) }}" class="portal-focus inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold shadow-sm hover:bg-white/90" style="color: var(--portal-on-paper);">
                                {{ __('Submit a ticket') }}
                            </a>
                        </nav>
                        @endif
                    </div>
                </div>
            </header>

            @if(session('success'))
                <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6 mt-4">
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
                        {{ session('success') }}
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6 mt-4">
                    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
                        {{ session('error') }}
                    </div>
                </div>
            @endif

            <!-- Page Content -->
            <main class="flex-1 py-8">
                {{ $slot }}
            </main>

            <!-- Footer -->
            <footer class="border-t border-gray-200 bg-white py-4">
                <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6 text-center text-sm text-gray-500">
                    &copy; {{ date('Y') }} CliqueHA Information Services OPC. {{ __('All rights reserved.') }}
                </div>
            </footer>
        </div>

        {{-- AI support assistant (shown only when the tenant has the feature and has enabled the portal widget) --}}
        @php
            $aiOn = app(\App\Services\PlanService::class)->tenantHasFeature($tenant, \App\Enums\PlanFeature::AiChatbot)
                && \App\Models\AppSetting::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('key', 'ai_enabled')->value('value') === '1'
                && \App\Models\AppSetting::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('key', 'ai_portal_widget_enabled')->value('value') === '1';
        @endphp
        @if($aiOn)
            @include('client-portal.partials.ai-chat-widget', ['tenant' => $tenant])
        @endif
    </body>
</html>
