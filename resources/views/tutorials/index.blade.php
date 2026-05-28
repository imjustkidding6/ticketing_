<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Tutorials') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
            <p class="mb-6 text-sm text-gray-600">{{ __('Learn how to use TechDesk effectively. Browse the guides below to get the most out of your workspace.') }}</p>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($tutorials as $slug => $tutorial)
                    <a href="{{ route('tutorials.show', $slug) }}" class="group rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition hover:border-indigo-300 hover:shadow-md">
                        <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 group-hover:bg-indigo-100">
                            @include('tutorials.partials._icon', ['icon' => $tutorial['icon']])
                        </div>
                        <h3 class="text-base font-semibold text-gray-900 group-hover:text-indigo-600">{{ __($tutorial['title']) }}</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ __($tutorial['description']) }}</p>
                    </a>
                @endforeach
            </div>

            @if($onboardingDismissed)
                <div class="mt-8 rounded-lg border border-gray-200 bg-gray-50 p-4" x-data="{ resetting: false }">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-700">{{ __('Onboarding Checklist') }}</p>
                            <p class="text-xs text-gray-500">{{ __('Show the setup checklist on your dashboard again.') }}</p>
                        </div>
                        <button type="button"
                                @click="resetting = true; fetch('{{ route('onboarding.reset') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } }).then(() => window.location.href = '{{ route('dashboard') }}')"
                                :disabled="resetting"
                                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                            <span x-show="!resetting">{{ __('Reset & Show') }}</span>
                            <span x-show="resetting" x-cloak>{{ __('Redirecting...') }}</span>
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
