<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 - {{ __('Access Denied') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="font-sans antialiased bg-gray-50">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="max-w-lg w-full text-center">
            {{-- Illustration --}}
            <div class="relative mx-auto mb-8">
                <div class="text-[10rem] font-bold leading-none text-gray-200 select-none">403</div>
                <div class="absolute inset-0 flex items-center justify-center">
                    <svg class="h-28 w-28 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="0.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                </div>
            </div>

            <h1 class="text-2xl font-bold text-gray-900 mb-3">{{ __('Access Denied') }}</h1>
            <p class="text-gray-500 mb-4 max-w-sm mx-auto">
                {{ __('You don\'t have permission to access this page. This might be a plan restriction or a permissions issue.') }}
            </p>

            <div class="mb-8 rounded-lg bg-amber-50 border border-amber-200 p-4 text-left max-w-sm mx-auto">
                <div class="flex items-start gap-3">
                    <svg class="h-5 w-5 text-amber-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                    <div class="text-sm text-amber-800">
                        <p class="font-medium">{{ __('Need access?') }}</p>
                        <p class="mt-1">{{ __('Contact your administrator or upgrade your subscription plan.') }}</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                <button onclick="history.back()" class="inline-flex items-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition-colors">
                    <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                    </svg>
                    {{ __('Go Back') }}
                </button>
                <a href="{{ url('/') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition-colors">
                    <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                    </svg>
                    {{ __('Go Home') }}
                </a>
            </div>
        </div>
    </div>
</body>
</html>
