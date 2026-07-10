<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Admin - {{ config('app.name', 'Laravel') }}</title>

    <link rel="icon" type="image/png" href="{{ asset('cliqueha-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('cliqueha-logo.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased">

<div class="min-h-screen bg-gray-100">

<nav
    x-data="{
        mobileOpen:false,
        licensing:false,
        system:false,
        account:false
    }"
    class="bg-gray-800 border-b border-gray-700">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="flex justify-between h-16">

            <div class="flex items-center">

                <a href="{{ route('admin.dashboard') }}"
                   class="text-white font-bold text-xl mr-10">
                    Admin Panel
                </a>

                <div class="hidden lg:flex items-center space-x-8">

                    {{-- Dashboard --}}
                    <a href="{{ route('admin.dashboard') }}"
                       class="{{ request()->routeIs('admin.dashboard')
                                ? 'text-white border-b-2 border-indigo-500'
                                : 'text-gray-300 hover:text-white' }} text-sm font-medium">
                        Dashboard
                    </a>

                    {{-- Licensing --}}
                    <div class="relative">

                        <button
                            @click="licensing=!licensing"
                            class="flex items-center text-sm text-gray-300 hover:text-white">

                            Licensing

                            <svg class="ml-1 h-4 w-4"
                                 fill="none"
                                 stroke="currentColor"
                                 viewBox="0 0 24 24">

                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M19 9l-7 7-7-7"/>

                            </svg>

                        </button>

                        <div
                            x-show="licensing"
                            @click.away="licensing=false"
                            x-transition
                            class="absolute left-0 mt-2 w-52 rounded-lg bg-white shadow-xl z-50">

                            <a href="{{ route('admin.distributors.index') }}"
                               class="block px-4 py-2 hover:bg-gray-100">
                                Distributors
                            </a>

                            <a href="{{ route('admin.licenses.index') }}"
                               class="block px-4 py-2 hover:bg-gray-100">
                                Licenses
                            </a>

                            <a href="{{ route('admin.plans.index') }}"
                               class="block px-4 py-2 hover:bg-gray-100">
                                Plans
                            </a>

                        </div>

                    </div>

                    {{-- Tenants --}}
                    <a href="{{ route('admin.tenants.index') }}"
                       class="{{ request()->routeIs('admin.tenants.*')
                                ? 'text-white border-b-2 border-indigo-500'
                                : 'text-gray-300 hover:text-white' }} text-sm font-medium">
                        Tenants
                    </a>

                    {{-- Users --}}
                    <a href="{{ route('admin.users.index') }}"
                       class="{{ request()->routeIs('admin.users.*')
                                ? 'text-white border-b-2 border-indigo-500'
                                : 'text-gray-300 hover:text-white' }} text-sm font-medium">
                        Users
                    </a>

                    {{-- Reports --}}
                    <a href="{{ route('admin.reports.index') }}"
                        class="{{ request()->routeIs('admin.reports.*')
                            ? 'text-white border-b-2 border-indigo-500'
                            : 'text-gray-300 hover:text-white' }} text-sm font-medium">
                        Reports
                    </a>

                    {{-- System --}}
                    <div class="relative">

                        <button
                            @click="system=!system"
                            class="flex items-center text-sm text-gray-300 hover:text-white">

                            System

                            <svg class="ml-1 h-4 w-4"
                                 fill="none"
                                 stroke="currentColor"
                                 viewBox="0 0 24 24">

                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M19 9l-7 7-7-7"/>

                            </svg>

                        </button>

                        <div
                            x-show="system"
                            @click.away="system=false"
                            x-transition
                            class="absolute left-0 mt-2 w-56 rounded-lg bg-white shadow-xl z-50">

                            <a href="{{ route('admin.announcements.index') }}"
                               class="block px-4 py-2 hover:bg-gray-100">
                                Announcements
                            </a>

                            <a href="{{ route('admin.feedback.index') }}"
                               class="block px-4 py-2 hover:bg-gray-100">
                                Feedback
                            </a>

                            <a href="{{ route('admin.bugs.index') }}"
                               class="block px-4 py-2 hover:bg-gray-100">
                                AI Bugs
                            </a>

                            <a href="{{ route('admin.settings.index') }}"
                               class="block px-4 py-2 hover:bg-gray-100">
                                Settings
                            </a>

                        </div>

                    </div>

                </div>

            </div>

            {{-- Account --}}
            <div class="hidden lg:flex items-center">

                <div class="relative">

                    <button
                        @click="account=!account"
                        class="flex items-center text-gray-300 hover:text-white">

                        {{ auth()->user()->name }}

                        <svg class="ml-1 h-4 w-4"
                             fill="none"
                             stroke="currentColor"
                             viewBox="0 0 24 24">

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 9l-7 7-7-7"/>

                        </svg>

                    </button>

                    <div
                        x-show="account"
                        @click.away="account=false"
                        x-transition
                        class="absolute right-0 mt-2 w-56 rounded-lg bg-white shadow-xl z-50">

                        <div class="border-b px-4 py-3">

                            <div class="font-semibold">
                                Admin User
                            </div>

                            <div class="text-sm text-gray-500">
                                {{ auth()->user()->email }}
                            </div>

                        </div>

                        <form method="POST"
                              action="{{ route('logout') }}">

                            @csrf

                            <button
                                class="block w-full px-4 py-2 text-left hover:bg-gray-100">

                                Log Out

                            </button>

                        </form>

                    </div>

                </div>

            </div>
                        {{-- Mobile Menu Button --}}
            <div class="flex items-center lg:hidden">

                <button
                    @click="mobileOpen=!mobileOpen"
                    class="text-gray-300 hover:text-white">

                    <svg
                        x-show="!mobileOpen"
                        class="w-6 h-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24">

                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"/>

                    </svg>

                    <svg
                        x-show="mobileOpen"
                        x-cloak
                        class="w-6 h-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24">

                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"/>

                    </svg>

                </button>

            </div>

        </div>

    </div>

    {{-- Mobile Navigation --}}
    <div
        x-show="mobileOpen"
        x-cloak
        class="lg:hidden bg-gray-800 border-t border-gray-700">

        <a href="{{ route('admin.dashboard') }}"
           class="block px-4 py-3 text-gray-300 hover:bg-gray-700">
            Dashboard
        </a>

        <div class="px-4 py-2 text-xs uppercase text-gray-500">
            Licensing
        </div>

        <a href="{{ route('admin.distributors.index') }}"
           class="block px-8 py-2 text-gray-300 hover:bg-gray-700">
            Distributors
        </a>

        <a href="{{ route('admin.licenses.index') }}"
           class="block px-8 py-2 text-gray-300 hover:bg-gray-700">
            Licenses
        </a>

        <a href="{{ route('admin.plans.index') }}"
           class="block px-8 py-2 text-gray-300 hover:bg-gray-700">
            Plans
        </a>

        <a href="{{ route('admin.tenants.index') }}"
           class="block px-4 py-3 text-gray-300 hover:bg-gray-700">
            Tenants
        </a>

        <a href="{{ route('admin.users.index') }}"
           class="block px-4 py-3 text-gray-300 hover:bg-gray-700">
            Users
        </a>

        <a href="{{ route('admin.reports.index') }}"
            class="{{ request()->routeIs('admin.reports.*')
                ? 'bg-gray-900 text-white'
                : 'text-gray-300 hover:bg-gray-700 hover:text-white' }} block px-4 py-3">
            Reports
        </a>

        <div class="px-4 py-2 text-xs uppercase text-gray-500">
            System
        </div>

        <a href="{{ route('admin.announcements.index') }}"
           class="block px-8 py-2 text-gray-300 hover:bg-gray-700">
            Announcements
        </a>

        <a href="{{ route('admin.feedback.index') }}"
           class="block px-8 py-2 text-gray-300 hover:bg-gray-700">
            Feedback
        </a>

        <a href="{{ route('admin.bugs.index') }}"
           class="block px-8 py-2 text-gray-300 hover:bg-gray-700">
            AI Bugs
        </a>

        <a href="{{ route('admin.settings.index') }}"
           class="block px-8 py-2 text-gray-300 hover:bg-gray-700">
            Settings
        </a>

        <div class="border-t border-gray-700 mt-2 pt-2">

            <div class="px-4 py-2 text-white font-semibold">
                Admin User
            </div>

            <div class="px-4 text-sm text-gray-400">
                {{ auth()->user()->email }}
            </div>

            <form
                method="POST"
                action="{{ route('logout') }}"
                class="px-4 py-3">

                @csrf

                <button
                    class="w-full rounded bg-gray-700 px-3 py-2 text-left text-white hover:bg-gray-600">

                    Log Out

                </button>

            </form>

        </div>

    </div>

</nav>

@if(session('success'))
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
        <div class="rounded border border-green-400 bg-green-100 px-4 py-3 text-green-700">
            {{ session('success') }}
        </div>
    </div>
@endif

@if(session('error'))
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
        <div class="rounded border border-red-400 bg-red-100 px-4 py-3 text-red-700">
            {{ session('error') }}
        </div>
    </div>
@endif

@if ($errors->any())
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
        <div class="rounded border border-red-400 bg-red-100 px-4 py-3 text-red-700">
            <ul class="list-disc ml-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

<header class="bg-white shadow">

    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">

        <h1 class="text-2xl font-bold text-gray-900">

            @yield('title', 'Admin')

        </h1>

    </div>

</header>

<main>

    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">

        @yield('content')

    </div>

</main>
</div>

</body>
</html>