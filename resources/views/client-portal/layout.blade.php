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
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Tenant Branding -->
        <style>
            :root {
                --portal-primary: {{ $tenant->primary_color ?? '#4f46e5' }};
                --portal-accent: {{ $tenant->accent_color ?? '#4338ca' }};
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
                                <div>
                                    <span class="text-lg font-semibold text-white">{{ $tenant->displayName() }}</span>
                                    <span class="ml-2 text-sm text-white/70">{{ __('Support Portal') }}</span>
                                </div>
                            </a>
                        </div>

                        @if(empty($hideNav))
                        <nav class="flex items-center gap-4">
                            <a href="{{ route('tenant.landing', ['slug' => $tenant->slug]) }}" class="text-sm font-medium text-white/80 hover:text-white">{{ __('Home') }}</a>
                            <a href="{{ route('tenant.track-ticket', ['slug' => $tenant->slug]) }}" class="text-sm font-medium text-white/80 hover:text-white">{{ __('Track Ticket') }}</a>
                            <a href="{{ route('tenant.submit-ticket', ['slug' => $tenant->slug]) }}" class="inline-flex items-center rounded-md px-3 py-2 text-sm font-semibold text-white shadow-sm" style="background-color: var(--portal-accent);">
                                {{ __('New Ticket') }}
                            </a>
                        </nav>
                        @endif
                    </div>
                </div>
            </header>

            @if(session('success'))
                <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6 mt-4">
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                        {{ session('success') }}
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6 mt-4">
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
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
    </body>
</html>
