<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Admin - {{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            <nav class="bg-gray-800 border-b border-gray-700">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex">
                            <div class="flex-shrink-0 flex items-center">
                                <a href="{{ route('admin.dashboard') }}" class="text-white font-bold text-xl">
                                    Admin Panel
                                </a>
                            </div>
                            <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                                <a href="{{ route('admin.dashboard') }}" class="@if(request()->routeIs('admin.dashboard')) border-indigo-400 text-white @else border-transparent text-gray-300 hover:border-gray-300 hover:text-white @endif inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                    Dashboard
                                </a>
                                <a href="{{ route('admin.distributors.index') }}" class="@if(request()->routeIs('admin.distributors.*')) border-indigo-400 text-white @else border-transparent text-gray-300 hover:border-gray-300 hover:text-white @endif inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                    Distributors
                                </a>
                                <a href="{{ route('admin.licenses.index') }}" class="@if(request()->routeIs('admin.licenses.*')) border-indigo-400 text-white @else border-transparent text-gray-300 hover:border-gray-300 hover:text-white @endif inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                    Licenses
                                </a>
                                <a href="{{ route('admin.plans.index') }}" class="@if(request()->routeIs('admin.plans.*')) border-indigo-400 text-white @else border-transparent text-gray-300 hover:border-gray-300 hover:text-white @endif inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                    Plans
                                </a>
                                <a href="{{ route('admin.chatbot-settings.edit') }}" class="@if(request()->routeIs('admin.chatbot-settings.*')) border-indigo-400 text-white @else border-transparent text-gray-300 hover:border-gray-300 hover:text-white @endif inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                    Chatbot
                                </a>
                                <a href="{{ route('admin.tenants.index') }}" class="@if(request()->routeIs('admin.tenants.*')) border-indigo-400 text-white @else border-transparent text-gray-300 hover:border-gray-300 hover:text-white @endif inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                    Tenants
                                </a>
                                <a href="{{ route('admin.users.index') }}" class="@if(request()->routeIs('admin.users.*')) border-indigo-400 text-white @else border-transparent text-gray-300 hover:border-gray-300 hover:text-white @endif inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                    Users
                                </a>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <span class="text-gray-300 text-sm mr-4">{{ auth()->user()->name }}</span>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="text-gray-300 hover:text-white text-sm">Logout</button>
                            </form>
                        </div>
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
