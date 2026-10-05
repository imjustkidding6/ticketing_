{{--
    The department's AI Agents tab (spec §8).

    Each assignment is its own <form> in a stacked list rather than a row in one table:
    forms cannot nest inside a <tr>, and the alternative — one form per row declared outside
    the table and wired back with `form="…"` attributes — is how a dead control ships.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $department->name }} — {{ __('AI Agents') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            @include('departments.partials._tabs', ['department' => $department, 'canManageAiAgents' => $canManage])

            @if(session('success'))
                <div class="mb-6 rounded-md bg-green-50 p-4">
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
            @endif

            @if($errors->any())
                <div class="mb-6 rounded-md bg-red-50 p-4">
                    <ul class="list-inside list-disc space-y-1 text-sm font-medium text-red-800">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @unless($aiAgentsEnabled)
                {{-- The opt-in lives on the setup hub, which is deliberately exempt from it;
                     with the sidebar entry gone this notice is the way back to the switch. --}}
                <div class="mb-6 rounded-md bg-amber-50 p-4">
                    <p class="text-sm font-medium text-amber-800">{{ __('AI agents are switched off for this workspace.') }}</p>
                    <p class="mt-1 text-sm text-amber-700">
                        {{ __('Existing assignments are shown below but cannot be changed until the feature is enabled.') }}
                        <a href="{{ route('ai-agents.setup') }}" class="font-medium underline">{{ __('Go to AI agent setup') }}</a>
                    </p>
                </div>
            @endunless

            <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">{{ __('Assigned AI Agents') }}</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ __('Lower priority runs first. 0 means unranked, and unranked agents sort after every ranked one.') }}
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">
                        {{ trans_choice(':count agent|:count agents', $assignments->count(), ['count' => $assignments->count()]) }}
                    </span>
                </div>

                <div class="mt-6 divide-y divide-gray-200 border-t border-gray-200">
                    @forelse($assignments as $agent)
                        <div class="py-4">
                            <form method="POST" action="{{ route('departments.ai-agents.update', [$department, $agent]) }}" class="flex flex-wrap items-end gap-4">
                                @csrf
                                @method('PUT')

                                <div class="min-w-48 grow">
                                    <p class="text-sm font-semibold text-gray-900">{{ $agent->name }}</p>
                                    <p class="text-xs text-gray-500">{{ __('Agent role type') }}: {{ ucfirst($agent->role_type) }}</p>
                                </div>

                                <div>
                                    <label for="role-{{ $agent->id }}" class="block text-xs font-medium text-gray-700">{{ __('Department role') }}</label>
                                    <select name="role" id="role-{{ $agent->id }}" @disabled(! $canManage || ! $aiAgentsEnabled) class="mt-1 block rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100">
                                        @foreach(\App\Models\DepartmentAiAgent::ROLES as $role)
                                            <option value="{{ $role }}" {{ $agent->pivot->role === $role ? 'selected' : '' }}>{{ ucfirst($role) }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="priority-{{ $agent->id }}" class="block text-xs font-medium text-gray-700">{{ __('Priority') }}</label>
                                    <input type="number" name="priority" id="priority-{{ $agent->id }}" value="{{ $agent->pivot->priority }}" min="0" max="9999" @disabled(! $canManage || ! $aiAgentsEnabled) class="mt-1 block w-24 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100">
                                </div>

                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" id="is_active-{{ $agent->id }}" value="1" {{ $agent->pivot->is_active ? 'checked' : '' }} @disabled(! $canManage || ! $aiAgentsEnabled) class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <label for="is_active-{{ $agent->id }}" class="text-xs font-medium text-gray-700">{{ __('Active') }}</label>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input type="hidden" name="auto_invoke" value="0">
                                        <input type="checkbox" name="auto_invoke" id="auto_invoke-{{ $agent->id }}" value="1" {{ $agent->pivot->auto_invoke ? 'checked' : '' }} @disabled(! $canManage || ! $aiAgentsEnabled) class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <label for="auto_invoke-{{ $agent->id }}" class="text-xs font-medium text-gray-700">{{ __('Auto invoke') }}</label>
                                    </div>
                                </div>

                                @if($canManage && $aiAgentsEnabled)
                                    <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Save') }}</button>
                                @endif
                            </form>

                            @if($canManage && $aiAgentsEnabled)
                                <form method="POST" action="{{ route('departments.ai-agents.destroy', [$department, $agent]) }}" class="mt-2" onsubmit="return confirm('{{ __('Remove this agent from the department?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-500">{{ __('Remove') }}</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="py-6 text-sm text-gray-500">
                            {{ __('No AI agents are assigned to this department yet. Assign one below to decide which agents handle its tickets.') }}
                        </p>
                    @endforelse
                </div>
            </div>

            @if($canManage && $aiAgentsEnabled)
                <div class="mt-6 overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('Assign an agent') }}</h3>

                    @if($assignableAgents->isEmpty())
                        <p class="mt-2 text-sm text-gray-500">
                            {{ __('Every agent in this workspace is already assigned to this department.') }}
                            <a href="{{ route('ai-agents.index') }}" class="font-medium text-indigo-600 hover:text-indigo-500">{{ __('Manage agents') }}</a>
                        </p>
                    @else
                        <form method="POST" action="{{ route('departments.ai-agents.store', $department) }}" class="mt-4 flex flex-wrap items-end gap-4">
                            @csrf

                            <div class="min-w-48 grow">
                                <label for="ai_agent_id" class="block text-xs font-medium text-gray-700">{{ __('Agent') }}</label>
                                <select name="ai_agent_id" id="ai_agent_id" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">{{ __('Choose an agent…') }}</option>
                                    @foreach($assignableAgents->groupBy('role_type') as $roleType => $group)
                                        <optgroup label="{{ ucfirst($roleType) }}">
                                            @foreach($group as $option)
                                                <option value="{{ $option->id }}" {{ (int) old('ai_agent_id') === $option->id ? 'selected' : '' }}>{{ $option->name }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="new_role" class="block text-xs font-medium text-gray-700">{{ __('Department role') }}</label>
                                <select name="role" id="new_role" class="mt-1 block rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach(\App\Models\DepartmentAiAgent::ROLES as $role)
                                        <option value="{{ $role }}" {{ old('role', 'support') === $role ? 'selected' : '' }}>{{ ucfirst($role) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="new_priority" class="block text-xs font-medium text-gray-700">{{ __('Priority') }}</label>
                                <input type="number" name="priority" id="new_priority" value="{{ old('priority', 0) }}" min="0" max="9999" class="mt-1 block w-24 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>

                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" id="new_is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <label for="new_is_active" class="text-xs font-medium text-gray-700">{{ __('Active') }}</label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="hidden" name="auto_invoke" value="0">
                                    <input type="checkbox" name="auto_invoke" id="new_auto_invoke" value="1" {{ old('auto_invoke') ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <label for="new_auto_invoke" class="text-xs font-medium text-gray-700">{{ __('Auto invoke') }}</label>
                                </div>
                            </div>

                            <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Assign') }}</button>
                        </form>
                    @endif

                    <p class="mt-4 border-t border-gray-200 pt-4 text-sm text-gray-500">
                        {{ __('Agents and the skills they hold are defined once and reused across departments.') }}
                        <a href="{{ route('ai-agents.index') }}" class="font-medium text-indigo-600 hover:text-indigo-500">{{ __('Manage agents') }}</a>
                        <span class="text-gray-400">·</span>
                        <a href="{{ route('skills.index') }}" class="font-medium text-indigo-600 hover:text-indigo-500">{{ __('Manage skills') }}</a>
                        <span class="text-gray-400">·</span>
                        <a href="{{ route('ai-agents.setup') }}" class="font-medium text-indigo-600 hover:text-indigo-500">{{ __('AI agent setup') }}</a>
                    </p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
