<x-client-portal-layout :tenant="$tenant">
    <div class="mx-auto max-w-md px-4 sm:px-6 lg:px-8">
        <div class="rounded-xl bg-white p-8 shadow-sm">
            <h2 class="text-2xl font-semibold text-gray-900">{{ __('Create your account') }}</h2>
            <p class="mt-2 text-sm text-gray-500">{{ __('Register to submit and track support tickets.') }}</p>

            <form method="POST" action="{{ route('portal.register', ['tenant' => $tenant->slug]) }}" class="mt-8 space-y-6">
                @csrf

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Full Name') }}</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required autofocus class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">{{ __('Email') }}</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700">{{ __('Phone') }} <span class="text-gray-400">({{ __('optional') }})</span></label>
                    <input type="text" name="phone" id="phone" value="{{ old('phone') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="company" class="block text-sm font-medium text-gray-700">{{ __('Company') }} <span class="text-gray-400">({{ __('optional') }})</span></label>
                    <input type="text" name="company" id="company" value="{{ old('company') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('company') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">{{ __('Password') }}</label>
                    <input type="password" name="password" id="password" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700">{{ __('Confirm Password') }}</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>

                <button type="submit" class="w-full rounded-md px-4 py-2.5 text-sm font-semibold text-white shadow-sm" style="background-color: var(--portal-primary);">
                    {{ __('Create Account') }}
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-gray-500">
                {{ __('Already have an account?') }}
                <a href="{{ route('portal.login', ['tenant' => $tenant->slug]) }}" class="font-medium" style="color: var(--portal-primary);">{{ __('Sign in') }}</a>
            </p>
        </div>
    </div>
</x-client-portal-layout>
