<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('New AI Agent') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="mb-4">
                <a href="{{ route('ai-agents.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; {{ __('Back to agents') }}</a>
            </div>

            <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('ai-agents.store') }}">
                    @csrf

                    <div class="space-y-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Name') }}</label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <p class="mt-1 text-xs text-gray-500">{{ __('The identifier is derived from the name and must be unique within this workspace.') }}</p>
                            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            @error('slug') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="role_type" class="block text-sm font-medium text-gray-700">{{ __('Role type') }}</label>
                            <select name="role_type" id="role_type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @foreach(\App\Models\AiAgent::ROLE_TYPES as $roleType)
                                    <option value="{{ $roleType }}" {{ old('role_type', 'specialist') === $roleType ? 'selected' : '' }}>{{ ucfirst($roleType) }}</option>
                                @endforeach
                            </select>
                            @error('role_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">{{ __('Description') }}</label>
                            <textarea name="description" id="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('description') }}</textarea>
                            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="priority" class="block text-sm font-medium text-gray-700">{{ __('Priority') }}</label>
                            <input type="number" name="priority" id="priority" value="{{ old('priority', 0) }}" min="0" max="9999" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <p class="mt-1 text-xs text-gray-500">{{ __('Lower runs first. Leave 0 for unranked — unranked agents sort after every ranked one.') }}</p>
                            @error('priority') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="ai_profile_id" class="block text-sm font-medium text-gray-700">{{ __('Model profile') }}</label>
                            <select name="ai_profile_id" id="ai_profile_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">{{ __('Use the workspace default') }}</option>
                                @foreach($profiles as $profile)
                                    <option value="{{ $profile->id }}" {{ (string) old('ai_profile_id') === (string) $profile->id ? 'selected' : '' }}>{{ $profile->name }}</option>
                                @endforeach
                            </select>
                            @error('ai_profile_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex flex-wrap items-center gap-6">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">{{ __('Active') }}</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="is_default" value="1" {{ old('is_default') ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">{{ __('Default agent') }}</span>
                            </label>
                        </div>
                    </div>

                    <p class="mt-6 text-xs text-gray-500">{{ __('Skills and the system prompt are configured after the agent is created.') }}</p>

                    <div class="mt-4 flex items-center justify-end gap-3">
                        <a href="{{ route('ai-agents.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">{{ __('Cancel') }}</a>
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Create Agent') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
