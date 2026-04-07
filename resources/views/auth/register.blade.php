<x-guest-layout>
    <form method="POST" action="{{ route('register') }}" x-data="registerForm()">
        @csrf

        <!-- License Key -->
        <div>
            <x-input-label for="license_key" :value="__('License Key')" />
            <x-text-input id="license_key" class="block mt-1 w-full font-mono uppercase" type="text" name="license_key" :value="old('license_key')" required autofocus placeholder="XXXX-XXXX-XXXX-XXXX-XXXX" />
            <x-input-error :messages="$errors->get('license_key')" class="mt-2" />
        </div>

        <!-- Company Name -->
        <div class="mt-4">
            <x-input-label for="company_name" :value="__('Company Name')" />
            <x-text-input id="company_name" class="block mt-1 w-full" type="text" name="company_name" :value="old('company_name')" required placeholder="Your Company Inc." x-model="companyName" @input="syncSlug()" />
            <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
        </div>

        <!-- App URL Slug -->
        <div class="mt-4">
            <x-input-label for="app_slug" :value="__('App URL Slug')" />
            <div class="flex items-center mt-1 rounded-md border border-gray-300 overflow-hidden focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-indigo-500">
                <input id="app_slug" type="text" name="app_slug" required
                    class="block flex-1 border-0 bg-transparent px-3 py-2 text-sm font-mono focus:ring-0"
                    placeholder="acme-corp"
                    x-model="slug"
                    @input="checkAvailability()"
                    minlength="3" maxlength="63" />
                <span class="px-3 py-2 text-sm text-gray-500 bg-gray-100 border-r border-gray-300 whitespace-nowrap order-first">{{ url('/') }}/</span>
            </div>
            <p class="mt-1 text-xs text-gray-500">
                {{ __('Your app will be at:') }}
                <span class="font-mono text-indigo-600" x-text="previewUrl"></span>
            </p>
            <p x-show="checking" x-cloak class="mt-1 text-xs text-gray-400">{{ __('Checking availability...') }}</p>
            <p x-show="!checking && slug.length >= 3 && available === true" x-cloak class="mt-1 text-xs text-green-600">{{ __('Available') }}</p>
            <p x-show="!checking && slug.length >= 3 && available === false" x-cloak class="mt-1 text-xs text-red-600">{{ __('Already taken or reserved') }}</p>
            <x-input-error :messages="$errors->get('app_slug')" class="mt-2" />
        </div>

        <!-- Name -->
        <div class="mt-4">
            <x-input-label for="name" :value="__('Your Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>

    <script>
        function registerForm() {
            return {
                companyName: '{{ old('company_name', '') }}',
                slug: '{{ old('app_slug', '') }}',
                checking: false,
                available: null,
                checkTimeout: null,
                synced: {{ old('app_slug') ? 'false' : 'true' }},

                get previewUrl() {
                    return this.slug
                        ? '{{ url('/') }}/' + this.slug + '/dashboard'
                        : '...';
                },

                syncSlug() {
                    if (!this.synced) return;
                    this.slug = this.companyName
                        .toLowerCase()
                        .replace(/[^a-z0-9\s-]/g, '')
                        .replace(/\s+/g, '-')
                        .replace(/-+/g, '-')
                        .replace(/^-|-$/g, '');
                    this.checkAvailability();
                },

                checkAvailability() {
                    this.synced = false;
                    clearTimeout(this.checkTimeout);
                    this.available = null;
                    if (this.slug.length < 3) return;
                    this.checkTimeout = setTimeout(async () => {
                        this.checking = true;
                        try {
                            const res = await fetch('/register/check-slug?slug=' + encodeURIComponent(this.slug));
                            const data = await res.json();
                            this.available = data.available;
                        } catch (e) {
                            this.available = null;
                        }
                        this.checking = false;
                    }, 400);
                },
            };
        }
    </script>
</x-guest-layout>
