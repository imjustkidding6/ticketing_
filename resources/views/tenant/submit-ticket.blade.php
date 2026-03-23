<x-client-portal-layout :tenant="$tenant">
    {{-- Hero Section --}}
    <div class="-mt-8 pb-12 pt-16" style="background-color: var(--portal-primary);">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6 text-center">
            <h1 class="text-3xl font-bold text-white sm:text-4xl">{{ $tenant->name }} {{ __('Support') }}</h1>
            <p class="mt-3 text-lg text-white/70">{{ __('Submit a ticket and we\'ll get back to you as soon as possible.') }}</p>
        </div>
    </div>

    {{-- Main Content --}}
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 -mt-6">
        <div class="rounded-xl bg-white p-8 shadow-sm">
            <div class="flex items-center gap-3 mb-6">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg" style="background-color: color-mix(in srgb, var(--portal-primary) 15%, white);">
                    <svg class="h-6 w-6" style="color: var(--portal-primary);" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">{{ __('Submit a Ticket') }}</h2>
                    <p class="text-sm text-gray-500">{{ __('Describe your issue and we\'ll get back to you.') }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('tenant.submit-ticket.store', ['slug' => $tenant->slug]) }}" class="space-y-5">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Your Name') }}</label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">{{ __('Email Address') }}</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="subject" class="block text-sm font-medium text-gray-700">{{ __('Subject') }}</label>
                    <input type="text" name="subject" id="subject" value="{{ old('subject') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="{{ __('Brief summary of your issue') }}">
                    @error('subject') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="department_id" class="block text-sm font-medium text-gray-700">{{ __('Department') }}</label>
                        <select name="department_id" id="department_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">{{ __('Select') }}</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" {{ old('department_id') == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                            @endforeach
                        </select>
                        @error('department_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="category_id" class="block text-sm font-medium text-gray-700">{{ __('Category') }}</label>
                        <select name="category_id" id="category_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">{{ __('Select') }}</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="priority" class="block text-sm font-medium text-gray-700">{{ __('Priority') }}</label>
                        <select name="priority" id="priority" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="low" {{ old('priority', 'medium') === 'low' ? 'selected' : '' }}>{{ __('Low') }}</option>
                            <option value="medium" {{ old('priority', 'medium') === 'medium' ? 'selected' : '' }}>{{ __('Medium') }}</option>
                            <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>{{ __('High') }}</option>
                            <option value="critical" {{ old('priority') === 'critical' ? 'selected' : '' }}>{{ __('Critical') }}</option>
                        </select>
                        @error('priority') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">{{ __('Description') }}</label>
                    <textarea name="description" id="description" rows="5" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="{{ __('Please provide as much detail as possible...') }}">{{ old('description') }}</textarea>
                    @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center justify-between">
                    <a href="{{ route('tenant.track-ticket', ['slug' => $tenant->slug]) }}" class="text-sm" style="color: var(--portal-primary);">
                        {{ __('Track an existing ticket') }}
                    </a>
                    <button type="submit" class="rounded-md px-6 py-2.5 text-sm font-semibold text-white shadow-sm" style="background-color: var(--portal-primary);">
                        {{ __('Submit Ticket') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-client-portal-layout>
