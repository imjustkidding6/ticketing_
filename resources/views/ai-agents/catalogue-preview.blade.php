{{-- A dry run before the install: a button that writes a few thousand rows should say how many first. --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Default Catalogue') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div>
                <a href="{{ route('ai-agents.setup') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; {{ __('Back to AI agents') }}</a>
            </div>

            <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                @if($summary->createdNothing())
                    <p class="text-sm text-gray-700">{{ __('Everything in the default catalogue is already installed — nothing would be changed.') }}</p>
                @else
                    <h3 class="text-base font-semibold text-gray-900">{{ __('This install would create') }}</h3>
                    <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div>
                            <dt class="text-sm text-gray-500">{{ __('Skills') }}</dt>
                            <dd class="text-xl font-semibold text-gray-900">{{ $summary->skillsCreated }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">{{ __('Agents') }}</dt>
                            <dd class="text-xl font-semibold text-gray-900">{{ $summary->agentsCreated }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">{{ __('Agent-skill links') }}</dt>
                            <dd class="text-xl font-semibold text-gray-900">{{ $summary->skillPivotsCreated }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">{{ __('Department assignments') }}</dt>
                            <dd class="text-xl font-semibold text-gray-900">{{ $summary->assignmentsCreated }}</dd>
                        </div>
                    </dl>
                    <p class="mt-4 text-xs text-gray-500">{{ __('Nothing you have already edited is touched — the install only creates what is missing.') }}</p>
                @endif

                @if($summary->missingDepartmentCodes !== [])
                    <div class="mt-6 rounded-md bg-amber-50 p-4">
                        <p class="text-sm font-medium text-amber-800">
                            {{ __('These department codes do not exist in this workspace, so their agents will be installed unassigned:') }}
                            {{ implode(', ', $summary->missingDepartmentCodes) }}.
                        </p>
                        <p class="mt-2 text-xs text-amber-700">
                            {{ __('Ask your administrator to run') }}
                            <code class="rounded bg-amber-100 px-1.5 py-0.5">php artisan departments:add-missing-defaults --tenant={{ $tenantId }}</code>
                            {{ __('first if you want those assignments created.') }}
                        </p>
                    </div>
                @endif

                <form method="POST" action="{{ route('ai-agents.catalogue.install') }}" class="mt-6 flex items-center justify-end gap-3">
                    @csrf
                    <a href="{{ route('ai-agents.setup') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">{{ __('Cancel') }}</a>
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Install the catalogue') }}</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
