<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Admin - {{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/png" href="{{ asset('cliqueha-logo.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('cliqueha-logo.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            <nav x-data="{ mobileOpen: false }" class="bg-gray-800 border-b border-gray-700">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex">
                            <div class="shrink-0 flex items-center">
                                <a href="{{ route('admin.dashboard') }}" class="text-white font-bold text-xl">
                                    Admin Panel
                                </a>
                            </div>
                            <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                                <a href="{{ route('admin.dashboard') }}"
                                   @class([
                                       'inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium',
                                       'border-indigo-400 text-white' => request()->routeIs('admin.dashboard'),
                                       'border-transparent text-gray-300 hover:border-gray-300 hover:text-white' => ! request()->routeIs('admin.dashboard'),
                                   ])>
                                    Dashboard
                                </a>
                                <a href="{{ route('admin.distributors.index') }}"
                                   @class([
                                       'inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium',
                                       'border-indigo-400 text-white' => request()->routeIs('admin.distributors.*'),
                                       'border-transparent text-gray-300 hover:border-gray-300 hover:text-white' => ! request()->routeIs('admin.distributors.*'),
                                   ])>
                                    Distributors
                                </a>
                                <a href="{{ route('admin.licenses.index') }}"
                                   @class([
                                       'inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium',
                                       'border-indigo-400 text-white' => request()->routeIs('admin.licenses.*'),
                                       'border-transparent text-gray-300 hover:border-gray-300 hover:text-white' => ! request()->routeIs('admin.licenses.*'),
                                   ])>
                                    Licenses
                                </a>
                                <a href="{{ route('admin.plans.index') }}"
                                   @class([
                                       'inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium',
                                       'border-indigo-400 text-white' => request()->routeIs('admin.plans.*'),
                                       'border-transparent text-gray-300 hover:border-gray-300 hover:text-white' => ! request()->routeIs('admin.plans.*'),
                                   ])>
                                    Plans
                                </a>
                                <a href="{{ route('admin.tenants.index') }}"
                                   @class([
                                       'inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium',
                                       'border-indigo-400 text-white' => request()->routeIs('admin.tenants.*'),
                                       'border-transparent text-gray-300 hover:border-gray-300 hover:text-white' => ! request()->routeIs('admin.tenants.*'),
                                   ])>
                                    Tenants
                                </a>
                                <a href="{{ route('admin.users.index') }}"
                                   @class([
                                       'inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium',
                                       'border-indigo-400 text-white' => request()->routeIs('admin.users.*'),
                                       'border-transparent text-gray-300 hover:border-gray-300 hover:text-white' => ! request()->routeIs('admin.users.*'),
                                   ])>
                                    Users
                                </a>
                                <a href="{{ route('admin.announcements.index') }}"
                                   @class([
                                       'inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium',
                                       'border-indigo-400 text-white' => request()->routeIs('admin.announcements.*'),
                                       'border-transparent text-gray-300 hover:border-gray-300 hover:text-white' => ! request()->routeIs('admin.announcements.*'),
                                   ])>
                                    Announcements
                                </a>
                                <a href="{{ route('admin.feedback.index') }}"
                                   @class([
                                       'inline-flex items-center gap-1.5 px-1 pt-1 border-b-2 text-sm font-medium',
                                       'border-indigo-400 text-white' => request()->routeIs('admin.feedback.*'),
                                       'border-transparent text-gray-300 hover:border-gray-300 hover:text-white' => ! request()->routeIs('admin.feedback.*'),
                                   ])>
                                    Feedback
                                </a>
                                <a href="{{ route('admin.bugs.index') }}"
                                   @class([
                                       'inline-flex items-center gap-1.5 px-1 pt-1 border-b-2 text-sm font-medium',
                                       'border-indigo-400 text-white' => request()->routeIs('admin.bugs.*'),
                                       'border-transparent text-gray-300 hover:border-gray-300 hover:text-white' => ! request()->routeIs('admin.bugs.*'),
                                   ])>
                                    AI Bugs
                                </a>
                                <a href="{{ route('admin.settings.index') }}"
                                   @class([
                                       'inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium',
                                       'border-indigo-400 text-white' => request()->routeIs('admin.settings.*'),
                                       'border-transparent text-gray-300 hover:border-gray-300 hover:text-white' => ! request()->routeIs('admin.settings.*'),
                                   ])>
                                    Settings
                                </a>
                            </div>
                        </div>
                        <div class="hidden sm:flex items-center">
                            <span class="text-gray-300 text-sm mr-4">{{ auth()->user()->name }}</span>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="text-gray-300 hover:text-white text-sm">Logout</button>
                            </form>
                        </div>
                        <div class="flex items-center sm:hidden">
                            <button type="button" @click="mobileOpen = !mobileOpen" :aria-expanded="mobileOpen.toString()" aria-controls="admin-mobile-menu" class="inline-flex items-center justify-center rounded-md p-2 text-gray-300 hover:bg-gray-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-400">
                                <span class="sr-only">Open main menu</span>
                                <svg x-show="!mobileOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                                </svg>
                                <svg x-show="mobileOpen" x-cloak class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div id="admin-mobile-menu" x-show="mobileOpen" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="sm:hidden border-t border-gray-700 bg-gray-800">
                    <div class="space-y-1 px-2 pt-2 pb-3">
                        <a href="{{ route('admin.dashboard') }}"
                           @class([
                               'block rounded-md px-3 py-2 text-base font-medium',
                               'bg-gray-900 text-white' => request()->routeIs('admin.dashboard'),
                               'text-gray-300 hover:bg-gray-700 hover:text-white' => ! request()->routeIs('admin.dashboard'),
                           ])>Dashboard</a>
                        <a href="{{ route('admin.distributors.index') }}"
                           @class([
                               'block rounded-md px-3 py-2 text-base font-medium',
                               'bg-gray-900 text-white' => request()->routeIs('admin.distributors.*'),
                               'text-gray-300 hover:bg-gray-700 hover:text-white' => ! request()->routeIs('admin.distributors.*'),
                           ])>Distributors</a>
                        <a href="{{ route('admin.licenses.index') }}"
                           @class([
                               'block rounded-md px-3 py-2 text-base font-medium',
                               'bg-gray-900 text-white' => request()->routeIs('admin.licenses.*'),
                               'text-gray-300 hover:bg-gray-700 hover:text-white' => ! request()->routeIs('admin.licenses.*'),
                           ])>Licenses</a>
                        <a href="{{ route('admin.plans.index') }}"
                           @class([
                               'block rounded-md px-3 py-2 text-base font-medium',
                               'bg-gray-900 text-white' => request()->routeIs('admin.plans.*'),
                               'text-gray-300 hover:bg-gray-700 hover:text-white' => ! request()->routeIs('admin.plans.*'),
                           ])>Plans</a>
                        <a href="{{ route('admin.tenants.index') }}"
                           @class([
                               'block rounded-md px-3 py-2 text-base font-medium',
                               'bg-gray-900 text-white' => request()->routeIs('admin.tenants.*'),
                               'text-gray-300 hover:bg-gray-700 hover:text-white' => ! request()->routeIs('admin.tenants.*'),
                           ])>Tenants</a>
                        <a href="{{ route('admin.users.index') }}"
                           @class([
                               'block rounded-md px-3 py-2 text-base font-medium',
                               'bg-gray-900 text-white' => request()->routeIs('admin.users.*'),
                               'text-gray-300 hover:bg-gray-700 hover:text-white' => ! request()->routeIs('admin.users.*'),
                           ])>Users</a>
                        <a href="{{ route('admin.announcements.index') }}"
                           @class([
                               'block rounded-md px-3 py-2 text-base font-medium',
                               'bg-gray-900 text-white' => request()->routeIs('admin.announcements.*'),
                               'text-gray-300 hover:bg-gray-700 hover:text-white' => ! request()->routeIs('admin.announcements.*'),
                           ])>Announcements</a>
                        <a href="{{ route('admin.feedback.index') }}"
                           @class([
                               'block rounded-md px-3 py-2 text-base font-medium',
                               'bg-gray-900 text-white' => request()->routeIs('admin.feedback.*'),
                               'text-gray-300 hover:bg-gray-700 hover:text-white' => ! request()->routeIs('admin.feedback.*'),
                           ])>Feedback</a>
                        <a href="{{ route('admin.bugs.index') }}"
                           @class([
                               'block rounded-md px-3 py-2 text-base font-medium',
                               'bg-gray-900 text-white' => request()->routeIs('admin.bugs.*'),
                               'text-gray-300 hover:bg-gray-700 hover:text-white' => ! request()->routeIs('admin.bugs.*'),
                           ])>AI Bugs</a>
                        <a href="{{ route('admin.settings.index') }}"
                           @class([
                               'block rounded-md px-3 py-2 text-base font-medium',
                               'bg-gray-900 text-white' => request()->routeIs('admin.settings.*'),
                               'text-gray-300 hover:bg-gray-700 hover:text-white' => ! request()->routeIs('admin.settings.*'),
                           ])>Settings</a>
                    </div>
                    <div class="border-t border-gray-700 px-4 py-3">
                        <div class="text-sm font-medium text-white">{{ auth()->user()->name }}</div>
                        <div class="text-xs text-gray-400">{{ auth()->user()->email }}</div>
                        <form method="POST" action="{{ route('logout') }}" class="mt-3">
                            @csrf
                            <button type="submit" class="block w-full rounded-md bg-gray-700 px-3 py-2 text-left text-sm font-medium text-gray-200 hover:bg-gray-600 hover:text-white">Logout</button>
                        </form>
                    </div>
                </div>
            </nav>

            @if(session('success'))
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                        {{ session('success') }}
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                        {{ session('error') }}
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