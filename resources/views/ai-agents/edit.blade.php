{{--
    Agent detail/edit (spec §33): General Information, Skills, System Prompt — three cards,
    in that order. A later package appends a fourth, Department Assignments, below them.

    Two independent forms cover three cards, so the General Information and System Prompt
    fields carry `form="agent-general"` rather than being wrapped: forms cannot nest, and
    the alternative was either reordering the cards against §33 or duplicating every
    general field as a hidden input in a second form.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $agent->name }}</h2>
            <span class="text-sm text-gray-500">{{ __('Role') }}: {{ ucfirst($agent->role_type) }}</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div>
                <a href="{{ route('ai-agents.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; {{ __('Back to agents') }}</a>
            </div>

            @if(session('success'))
                <div class="rounded-md bg-green-50 p-4">
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
            @endif

            @error('delete')
                <div class="rounded-md bg-red-50 p-4">
                    <p class="text-sm font-medium text-red-800">{{ $message }}</p>
                </div>
            @enderror

            <form id="agent-general" method="POST" action="{{ route('ai-agents.update', $agent) }}">
                @csrf
                @method('PUT')
            </form>

            {{-- 1. General Information --}}
            <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">{{ __('General Information') }}</h3>

                <div class="mt-4 space-y-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Name') }}</label>
                        <input form="agent-general" type="text" name="name" id="name" value="{{ old('name', $agent->name) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        @error('slug') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="role_type" class="block text-sm font-medium text-gray-700">{{ __('Role type') }}</label>
                        <select form="agent-general" name="role_type" id="role_type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @foreach(\App\Models\AiAgent::ROLE_TYPES as $roleType)
                                <option value="{{ $roleType }}" {{ old('role_type', $agent->role_type) === $roleType ? 'selected' : '' }}>{{ ucfirst($roleType) }}</option>
                            @endforeach
                        </select>
                        @error('role_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">{{ __('Description') }}</label>
                        <textarea form="agent-general" name="description" id="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('description', $agent->description) }}</textarea>
                        @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="priority" class="block text-sm font-medium text-gray-700">{{ __('Priority') }}</label>
                        <input form="agent-general" type="number" name="priority" id="priority" value="{{ old('priority', $agent->priority) }}" min="0" max="9999" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <p class="mt-1 text-xs text-gray-500">{{ __('Lower runs first. 0 means unranked, and unranked agents sort after every ranked one.') }}</p>
                        @error('priority') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="ai_profile_id" class="block text-sm font-medium text-gray-700">{{ __('Model profile') }}</label>
                        <select form="agent-general" name="ai_profile_id" id="ai_profile_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">{{ __('Use the workspace default') }}</option>
                            @foreach($profiles as $profile)
                                <option value="{{ $profile->id }}" {{ (string) old('ai_profile_id', $agent->ai_profile_id) === (string) $profile->id ? 'selected' : '' }}>{{ $profile->name }}</option>
                            @endforeach
                        </select>
                        @error('ai_profile_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex flex-wrap items-center gap-6">
                        <label class="flex items-center gap-2">
                            <input form="agent-general" type="checkbox" name="is_active" value="1" {{ old('is_active', $agent->is_active) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm text-gray-700">{{ __('Active') }}</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input form="agent-general" type="checkbox" name="is_default" value="1" {{ old('is_default', $agent->is_default) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm text-gray-700">{{ __('Default agent') }}</span>
                        </label>
                    </div>
                </div>

                @if($departmentNames !== [])
                    <p class="mt-6 rounded-md bg-gray-50 px-4 py-3 text-xs text-gray-600">
                        {{ __('Assigned to') }} {{ implode(', ', $departmentNames) }}. {{ __('It cannot be deleted while any department uses it — deactivate it instead.') }}
                    </p>
                @endif
            </div>

            {{-- 2. Skills --}}
            <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">{{ __('Skills') }}</h3>
                <p class="mt-1 text-xs text-gray-500">{{ __('Saving this table applies every change at once, including removals.') }}</p>

                @error('skills') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                @foreach($errors->get('skills.*') as $messages)
                    <p class="mt-2 text-sm text-red-600">{{ $messages[0] }}</p>
                @endforeach

                <form method="POST" action="{{ route('ai-agents.skills.sync', $agent) }}" class="mt-4">
                    @csrf
                    @method('PUT')

                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th scope="col" class="py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Skill') }}</th>
                                <th scope="col" class="py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Proficiency') }}</th>
                                <th scope="col" class="py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Weight') }}</th>
                                <th scope="col" class="py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Required') }}</th>
                                <th scope="col" class="py-2 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($agent->skills as $i => $skill)
                                <tr>
                                    <td class="py-3 pr-3 text-sm text-gray-900">
                                        <input type="hidden" name="skills[{{ $i }}][skill_id]" value="{{ $skill->id }}">
                                        {{ $skill->name }}
                                        @if(! $skill->is_active)
                                            <span class="ml-2 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">{{ __('Inactive') }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3 pr-3">
                                        <select name="skills[{{ $i }}][proficiency]" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            @foreach(\App\Models\AiAgentSkill::PROFICIENCIES as $level)
                                                <option value="{{ $level }}" {{ $skill->pivot->proficiency === $level ? 'selected' : '' }}>{{ ucfirst($level) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="py-3 pr-3">
                                        <input type="number" name="skills[{{ $i }}][weight]" value="{{ $skill->pivot->weight }}" min="0" max="100" class="w-20 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </td>
                                    <td class="py-3 pr-3">
                                        <input type="hidden" name="skills[{{ $i }}][is_required]" value="0">
                                        <input type="checkbox" name="skills[{{ $i }}][is_required]" value="1" {{ $skill->pivot->is_required ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    </td>
                                    <td class="py-3 text-right text-sm">
                                        {{-- Posts the per-row detach form declared at the foot of the page. --}}
                                        <button type="submit" form="detach-skill-{{ $skill->id }}" onclick="return confirm('{{ __('Remove this skill from the agent?') }}')" class="text-red-600 hover:text-red-900">{{ __('Remove') }}</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-sm text-gray-500">{{ __('No skills yet. Add one below.') }}</td>
                                </tr>
                            @endforelse

                            {{-- Add one at a time, grouped by category. Never a multi-select over the whole catalogue. --}}
                            <tr>
                                <td class="py-3 pr-3">
                                    <select name="skills[new][skill_id]" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">{{ __('Add a skill…') }}</option>
                                        @foreach($availableSkills->groupBy(fn ($skill) => $skill->skill_category ?: __('Uncategorised')) as $category => $group)
                                            <optgroup label="{{ $category }}">
                                                @foreach($group as $option)
                                                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-3 pr-3">
                                    <select name="skills[new][proficiency]" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        @foreach(\App\Models\AiAgentSkill::PROFICIENCIES as $level)
                                            <option value="{{ $level }}" {{ $level === 'intermediate' ? 'selected' : '' }}>{{ ucfirst($level) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-3 pr-3">
                                    <input type="number" name="skills[new][weight]" min="0" max="100" class="w-20 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </td>
                                <td class="py-3 pr-3">
                                    <input type="hidden" name="skills[new][is_required]" value="0">
                                    <input type="checkbox" name="skills[new][is_required]" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                </td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="mt-4 flex justify-end">
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Save Skills') }}</button>
                    </div>
                </form>
            </div>

            {{-- 3. System Prompt --}}
            <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">{{ __('System Prompt') }}</h3>
                <textarea form="agent-general" name="system_prompt" id="system_prompt" rows="8" class="mt-4 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('system_prompt', $agent->system_prompt) }}</textarea>
                @error('system_prompt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                <p class="mt-3 text-xs font-medium text-gray-600">{{ __('Leave empty to use the standard instructions:') }}</p>
                <p class="mt-1 rounded-md bg-gray-50 px-4 py-3 text-xs text-gray-500">{{ \App\Models\AiAgent::DEFAULT_SYSTEM_PROMPT }}</p>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <a href="{{ route('ai-agents.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">{{ __('Cancel') }}</a>
                    <button type="submit" form="agent-general" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Save Agent') }}</button>
                </div>
            </div>

            {{-- Per-row detach forms: outside the sync form, because forms cannot nest. --}}
            @foreach($agent->skills as $skill)
                <form id="detach-skill-{{ $skill->id }}" method="POST" action="{{ route('ai-agents.skills.destroy', [$agent, $skill]) }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endforeach
        </div>
    </div>
</x-app-layout>
