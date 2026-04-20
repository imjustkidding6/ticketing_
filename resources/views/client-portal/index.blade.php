<x-client-portal-layout :tenant="$tenant">
    {{-- Hero Section --}}
    <div class="-mt-8 pb-12 pt-16" style="background-color: var(--portal-primary);">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6 text-center">
            <h1 class="text-3xl font-bold text-white sm:text-4xl">{{ $tenant->name }} {{ __('Support') }}</h1>
            <p class="mt-3 text-lg text-white/70">{{ __('Need help? Submit a ticket or track an existing one.') }}</p>
            @auth
                <a href="{{ route('portal.dashboard', ['tenant' => $tenant->slug]) }}" class="mt-4 inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold shadow-sm hover:bg-gray-100" style="color: var(--portal-primary);">
                    {{ __('Go to Dashboard') }}
                </a>
            @endauth
        </div>
    </div>

    {{-- Main Content --}}
    <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6 -mt-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- Submit a Ticket (2/3 width) --}}
            <div class="lg:col-span-2">
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
                                <input type="text" name="name" id="name" value="{{ old('name', Auth::user()?->name) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">{{ __('Email Address') }}</label>
                                <input type="email" name="email" id="email" value="{{ old('email', Auth::user()?->email) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
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

                        <div class="flex justify-end">
                            <button type="submit" class="rounded-md px-6 py-2.5 text-sm font-semibold text-white shadow-sm" style="background-color: var(--portal-primary);">
                                {{ __('Submit Ticket') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Track a Ticket (1/3 width) --}}
            <div>
                <div class="rounded-xl bg-white p-8 shadow-sm">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-100">
                            <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900">{{ __('Track a Ticket') }}</h2>
                            <p class="text-sm text-gray-500">{{ __('Check the status of your ticket.') }}</p>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('tenant.track-ticket', ['slug' => $tenant->slug]) }}" class="space-y-4">
                        <div>
                            <label for="ticket_number" class="block text-sm font-medium text-gray-700">{{ __('Ticket Number') }}</label>
                            <input type="text" name="ticket_number" id="ticket_number" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="TKT-XXXXXXX">
                        </div>
                        <div>
                            <label for="track_email" class="block text-sm font-medium text-gray-700">{{ __('Your Email') }}</label>
                            <input type="email" name="email" id="track_email" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="you@example.com">
                        </div>
                        <button type="submit" class="w-full rounded-md bg-green-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-green-500">
                            {{ __('Track Ticket') }}
                        </button>
                    </form>
                </div>

                {{-- Quick Links --}}
                <div class="mt-6 rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">{{ __('Have an account?') }}</h3>
                    <div class="space-y-2">
                        <a href="{{ route('portal.login', ['tenant' => $tenant->slug]) }}" class="flex items-center gap-2 text-sm" style="color: var(--portal-primary);">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                            </svg>
                            {{ __('Login to your account') }}
                        </a>
                        <a href="{{ route('portal.register', ['tenant' => $tenant->slug]) }}" class="flex items-center gap-2 text-sm" style="color: var(--portal-primary);">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" />
                            </svg>
                            {{ __('Create an account') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-client-portal-layout>
