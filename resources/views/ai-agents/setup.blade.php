{{--
    The AI agents hub: the opt-in switch, the counts, and the catalogue install.

    The only screen in the feature that renders while the opt-in is off — it holds the
    switch, so gating it would make the feature impossible to turn back on.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('AI Agents') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="rounded-md bg-green-50 p-4">
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
            @endif

            @if(session('missing_department_codes'))
                {{-- Named codes and the exact command, or an administrator sees agents with no
                     department and no way to find out why. --}}
                <div class="rounded-md bg-amber-50 p-4">
                    <p class="text-sm font-medium text-amber-800">
                        {{ __('Some agents were installed without a department, because these department codes do not exist in this workspace:') }}
                        {{ implode(', ', session('missing_department_codes')) }}.
                    </p>
                    <p class="mt-2 text-xs text-amber-700">
                        {{ __('Ask your administrator to run') }}
                        <code class="rounded bg-amber-100 px-1.5 py-0.5">php artisan departments:add-missing-defaults --tenant={{ $tenantId }}</code>
                        {{ __('and then install the catalogue again.') }}
                    </p>
                </div>
            @endif

            {{-- The switch --}}
            <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">{{ __('AI agent configuration') }}</h3>
                <p class="mt-1 text-sm text-gray-500">
                    {{ __('Agents are configured per department, each holding a set of skills. Nothing is sent to an AI provider from these screens — they configure the roster only.') }}
                </p>

                <form method="POST" action="{{ route('ai-agents.setup') }}" class="mt-4">
                    @csrf
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="ai_agents_enabled" value="1" {{ $enabled ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700">{{ __('Enable AI agents for this workspace') }}</span>
                    </label>
                    <p class="mt-2 text-xs text-gray-500">{{ __('While this is off, the agents and skills screens return 403 — this page stays available so you can switch it back on.') }}</p>

                    <div class="mt-4 flex justify-end">
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Save') }}</button>
                    </div>
                </form>
            </div>

            {{-- Counts and the two resources --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-gray-500">{{ __('Skills') }}</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $skillCount }}</p>
                    @if($enabled)
                        <a href="{{ route('skills.index') }}" class="mt-2 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-500">{{ __('Manage skills') }}</a>
                    @endif
                </div>
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-gray-500">{{ __('Agents') }}</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $agentCount }}</p>
                    @if($enabled)
                        <a href="{{ route('ai-agents.index') }}" class="mt-2 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-500">{{ __('Manage agents') }}</a>
                    @endif
                </div>
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-gray-500">{{ __('Department assignments') }}</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $assignmentCount }}</p>
                </div>
            </div>

            {{-- The install --}}
            <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">{{ __('Default catalogue') }}</h3>
                <p class="mt-1 text-sm text-gray-500">
                    {{ __('Installs the standard skills, agents and department assignments. It only creates what is missing, so running it again never overwrites your own edits.') }}
                </p>
                {{-- Gated like the two links above: the preview calls ensureAiAgentsEnabled(), so
                     offering it while the opt-in is off is a button that 403s — and off is the
                     state every new workspace is in. --}}
                @if($enabled)
                    <a href="{{ route('ai-agents.catalogue.preview') }}" class="mt-4 inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        {{ __('Review the default catalogue') }}
                    </a>
                @else
                    <p class="mt-4 text-sm text-gray-500">{{ __('Enable AI agents above to install the catalogue.') }}</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
