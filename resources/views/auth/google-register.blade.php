<x-guest-layout>
    <div class="mb-4 p-3 bg-gray-50 rounded-md border border-gray-200 flex items-center gap-3">
        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
        </svg>
        <div>
            <p class="text-sm font-medium text-gray-700">Signed in as <span class="font-semibold">{{ $profile['name'] }}</span></p>
            <p class="text-xs text-gray-500">{{ $profile['email'] }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('auth.google.register.store') }}" x-data="registerForm()">
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

        <div class="flex items-center justify-end mt-6">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('register') }}">
                {{ __('Use email instead') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Create Account') }}
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
