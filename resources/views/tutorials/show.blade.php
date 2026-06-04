<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <a href="{{ route('tutorials.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
            </a>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __($tutorial['title']) }}</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl px-4 sm:px-4 lg:px-6">
            <div class="flex gap-8">
                {{-- Sidebar nav --}}
                <nav class="hidden w-48 shrink-0 lg:block">
                    <ul class="space-y-1">
                        @foreach($tutorials as $navSlug => $navTutorial)
                            <li>
                                <a href="{{ route('tutorials.show', $navSlug) }}"
                                   class="block rounded-md px-3 py-2 text-sm font-medium {{ $navSlug === $slug ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                                    {{ __($navTutorial['title']) }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>

                {{-- Content --}}
                <div class="min-w-0 flex-1">
                    <div class="rounded-xl bg-white p-6 shadow-sm sm:p-8">
                        @include('tutorials.partials.' . $slug)
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
