<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Create Category') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('categories.store') }}">
                    @csrf

                    <div class="space-y-6">
                        <div>
                            <label for="department_id" class="block text-sm font-medium text-gray-700">{{ __('Department') }}</label>
                            <select name="department_id" data-tour="category-department" id="department_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">{{ __('Select a department') }}</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->id }}" {{ old('department_id') == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                                @endforeach
                            </select>
                            @error('department_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Name') }}</label>
                            <input type="text" name="name" data-tour="category-name" id="name" value="{{ old('name') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">{{ __('Description') }}</label>
                            <textarea name="description" id="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('description') }}</textarea>
                            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="color" class="block text-sm font-medium text-gray-700">{{ __('Color') }}</label>
                            <input type="color" name="color" id="color" value="{{ old('color', '#8b5cf6') }}" class="mt-1 h-10 w-20 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('color') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex items-center gap-2">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <label for="is_active" class="text-sm font-medium text-gray-700">{{ __('Active') }}</label>
                        </div>

                        {{-- Consequence 4 of moving these gates down a level, stated on the
                             form rather than discovered later: the two questions now belong
                             to a category, so a ticket filed WITHOUT one is asked neither —
                             and the priority rubric loses both of its decisive inputs for
                             that ticket. The fix is a category, which is why it is said
                             here. --}}
                        <div data-tour="category-detail-questions" class="rounded-md border border-amber-200 bg-amber-50 p-3">
                            <p class="text-xs text-amber-800">{{ __('These two settings decide what the ticket form asks for. A ticket filed with no category at all is asked neither, so its priority is judged from the wording alone.') }}</p>
                        </div>

                        <div class="flex items-start gap-2">
                            <input type="hidden" name="is_technical" value="0">
                            <input type="checkbox" name="is_technical" id="is_technical" value="1" {{ old('is_technical', false) ? 'checked' : '' }} class="mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <div>
                                <label for="is_technical" class="text-sm font-medium text-gray-700">{{ __('Covers technical work') }}</label>
                                <p class="text-xs text-gray-500">{{ __('Tickets in this category also ask for the environment, steps to reproduce and the exact error message. Leave it off for categories like leave requests, invoices or quotes.') }}</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-2">
                            <input type="hidden" name="asks_impact" value="0">
                            <input type="checkbox" name="asks_impact" id="asks_impact" value="1" {{ old('asks_impact', true) ? 'checked' : '' }} class="mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <div>
                                <label for="asks_impact" class="text-sm font-medium text-gray-700">{{ __('Ask who is affected and whether there is a workaround') }}</label>
                                {{-- The cost is stated, not implied. These two answers are
                                     what separates a critical ticket from a high one, so a
                                     category with the questions switched off cannot reach
                                     critical and its priority is left to how the reporter
                                     happened to word the ticket. Switch it off only where
                                     the questions genuinely do not apply — a leave request
                                     or an expenses query — not for anything that can take a
                                     service down. --}}
                                <p class="text-xs text-gray-500">{{ __('These two answers are what separates a critical ticket from a high one. Switch them off and tickets in this category can no longer be judged critical — the priority falls back to how the ticket happens to be worded. Leave it on for anything that can affect more than one person.') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <a href="{{ route('categories.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">{{ __('Cancel') }}</a>
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Create Category') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
