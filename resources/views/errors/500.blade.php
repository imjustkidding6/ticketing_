<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 - {{ __('Server Error') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="font-sans antialiased bg-gray-50">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="max-w-lg w-full text-center">
            {{-- Illustration --}}
            <div class="relative mx-auto mb-8">
                <div class="text-[10rem] font-bold leading-none text-gray-200 select-none">500</div>
                <div class="absolute inset-0 flex items-center justify-center">
                    <svg class="h-28 w-28 text-orange-400" fill="none" viewBox="0 0 24 24" stroke-width="0.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17l-3.258-3.258a2.25 2.25 0 00-3.182 0l-.861.86a2.25 2.25 0 000 3.183l6.38 6.38a2.25 2.25 0 003.183 0l.861-.86a2.25 2.25 0 000-3.183L11.42 15.17zM21.13 9.13l-2.782-2.782a2.25 2.25 0 00-3.182 0l-.861.86a2.25 2.25 0 000 3.183l2.782 2.782a2.25 2.25 0 003.182 0l.861-.86a2.25 2.25 0 000-3.183z" />
                    </svg>
                </div>
            </div>

            <h1 class="text-2xl font-bold text-gray-900 mb-3">{{ __('Something went wrong') }}</h1>
            <p class="text-gray-500 mb-8 max-w-sm mx-auto">
                {{ __('Our servers encountered an unexpected error. We\'ve been notified and are working on it.') }}
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                <button onclick="location.reload()" class="inline-flex items-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition-colors">
                    <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" />
                    </svg>
                    {{ __('Try Again') }}
                </button>
                <a href="{{ url('/') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition-colors">
                    <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                    </svg>
                    {{ __('Go Home') }}
                </a>
                <button onclick="history.back()" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition-colors">
                    <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                    </svg>
                    {{ __('Go Back') }}
                </button>
            </div>

            <div class="mt-12 flex items-center justify-center gap-6 text-xs text-gray-400">
                <div class="flex items-center gap-1.5">
                    <div class="h-1.5 w-1.5 rounded-full bg-red-400 animate-pulse"></div>
                    {{ __('Investigating issue') }}
                </div>
            </div>
        </div>
    </div>
</body>
</html>
