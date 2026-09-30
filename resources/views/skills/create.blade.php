<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('New Skill') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <div class="mb-4">
                <a href="{{ route('skills.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; {{ __('Back to skills') }}</a>
            </div>

            <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('skills.store') }}">
                    @csrf

                    <div class="space-y-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Name') }}</label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <p class="mt-1 text-xs text-gray-500">{{ __('Must be unique within this workspace. The identifier is derived from the name.') }}</p>
                            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            @error('slug') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="skill_category" class="block text-sm font-medium text-gray-700">{{ __('Category') }}</label>
                            <input type="text" name="skill_category" id="skill_category" value="{{ old('skill_category') }}" list="skill-category-list" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <datalist id="skill-category-list">
                                @foreach($categories as $category)
                                    <option value="{{ $category }}">
                                @endforeach
                            </datalist>
                            <p class="mt-1 text-xs text-gray-500">{{ __('Pick one of the suggestions or name your own. Categories are a functional domain, never a department.') }}</p>
                            @error('skill_category') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">{{ __('Description') }}</label>
                            <textarea name="description" id="description" rows="3" placeholder="{{ __('Optional — what this skill covers') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('description') }}</textarea>
                            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm text-gray-700">{{ __('Active') }}</span>
                        </label>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <a href="{{ route('skills.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">{{ __('Cancel') }}</a>
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Create Skill') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
