<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Branding Settings') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <!-- Settings Tabs -->
            <div class="mb-6 flex gap-4 border-b border-gray-200">
                <a href="{{ route('settings.general') }}" class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">{{ __('General') }}</a>
                <a href="{{ route('settings.ticket') }}" class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">{{ __('Tickets') }}</a>
                @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::EmailNotifications))
                <a href="{{ route('settings.notifications') }}" class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">{{ __('Notifications') }}</a>
                @endif
                <a href="{{ route('settings.branding') }}" class="border-b-2 border-indigo-500 px-4 py-2 text-sm font-medium text-indigo-600">{{ __('Branding') }}</a>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('settings.branding') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="space-y-8">
                        <!-- Logo Upload -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('Portal Logo') }}</label>
                            <p class="mt-1 text-xs text-gray-500">{{ __('Displayed on your client portal. Recommended: 200x60px. Max 2MB. JPG, PNG, or SVG.') }}</p>

                            <div class="mt-3 flex items-center gap-6">
                                @if($tenant->logo_path)
                                    <div class="shrink-0">
                                        <img src="{{ $tenant->logoUrl() }}" alt="{{ $tenant->name }} logo" class="h-16 w-auto rounded border border-gray-200 object-contain p-1">
                                    </div>
                                @else
                                    <div class="flex h-16 w-32 shrink-0 items-center justify-center rounded border-2 border-dashed border-gray-300 text-xs text-gray-400">
                                        {{ __('No logo') }}
                                    </div>
                                @endif

                                <div class="flex flex-col gap-2">
                                    <input type="file" name="logo" id="logo" accept="image/jpeg,image/png,image/svg+xml" class="text-sm text-gray-500 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                                    @if($tenant->logo_path)
                                        <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                                            <input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            {{ __('Remove current logo') }}
                                        </label>
                                    @endif
                                </div>
                            </div>
                            @error('logo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <!-- Light Mode Colors -->
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                                <svg class="h-4 w-4 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" /></svg>
                                {{ __('Light Mode Colors') }}
                            </h3>
                            <div class="mt-3 grid grid-cols-2 gap-6">
                                <div>
                                    <label for="primary_color" class="block text-sm font-medium text-gray-700">{{ __('Primary Color') }}</label>
                                    <p class="mt-1 text-xs text-gray-500">{{ __('Buttons, links, active states.') }}</p>
                                    <div class="mt-2 flex items-center gap-3">
                                        <input type="color" name="primary_color" id="primary_color" value="{{ old('primary_color', $tenant->primary_color ?? '#4f46e5') }}" class="h-10 w-14 cursor-pointer rounded border border-gray-300">
                                        <input type="text" id="primary_color_hex" value="{{ old('primary_color', $tenant->primary_color ?? '#4f46e5') }}" class="w-28 rounded-md border-gray-300 font-mono text-sm shadow-sm" readonly>
                                    </div>
                                    @error('primary_color') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="accent_color" class="block text-sm font-medium text-gray-700">{{ __('Accent Color') }}</label>
                                    <p class="mt-1 text-xs text-gray-500">{{ __('Hover states, secondary elements.') }}</p>
                                    <div class="mt-2 flex items-center gap-3">
                                        <input type="color" name="accent_color" id="accent_color" value="{{ old('accent_color', $tenant->accent_color ?? '#4338ca') }}" class="h-10 w-14 cursor-pointer rounded border border-gray-300">
                                        <input type="text" id="accent_color_hex" value="{{ old('accent_color', $tenant->accent_color ?? '#4338ca') }}" class="w-28 rounded-md border-gray-300 font-mono text-sm shadow-sm" readonly>
                                    </div>
                                    @error('accent_color') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Dark Mode Colors -->
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                                <svg class="h-4 w-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" /></svg>
                                {{ __('Dark Mode Colors') }}
                            </h3>
                            <div class="mt-3 grid grid-cols-2 gap-6">
                                <div>
                                    <label for="dark_primary_color" class="block text-sm font-medium text-gray-700">{{ __('Primary Color') }}</label>
                                    <p class="mt-1 text-xs text-gray-500">{{ __('Buttons, links, active states in dark mode.') }}</p>
                                    <div class="mt-2 flex items-center gap-3">
                                        <input type="color" name="dark_primary_color" id="dark_primary_color" value="{{ old('dark_primary_color', $tenant->dark_primary_color ?? '#818cf8') }}" class="h-10 w-14 cursor-pointer rounded border border-gray-300">
                                        <input type="text" id="dark_primary_color_hex" value="{{ old('dark_primary_color', $tenant->dark_primary_color ?? '#818cf8') }}" class="w-28 rounded-md border-gray-300 font-mono text-sm shadow-sm" readonly>
                                    </div>
                                    @error('dark_primary_color') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="dark_accent_color" class="block text-sm font-medium text-gray-700">{{ __('Accent Color') }}</label>
                                    <p class="mt-1 text-xs text-gray-500">{{ __('Hover states, secondary elements in dark mode.') }}</p>
                                    <div class="mt-2 flex items-center gap-3">
                                        <input type="color" name="dark_accent_color" id="dark_accent_color" value="{{ old('dark_accent_color', $tenant->dark_accent_color ?? '#6366f1') }}" class="h-10 w-14 cursor-pointer rounded border border-gray-300">
                                        <input type="text" id="dark_accent_color_hex" value="{{ old('dark_accent_color', $tenant->dark_accent_color ?? '#6366f1') }}" class="w-28 rounded-md border-gray-300 font-mono text-sm shadow-sm" readonly>
                                    </div>
                                    @error('dark_accent_color') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Preview -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('Preview') }}</label>
                            <div class="mt-2 grid grid-cols-2 gap-4">
                                <div class="overflow-hidden rounded-lg border border-gray-200">
                                    <div class="text-center text-xs font-medium text-gray-500 py-1 bg-gray-50">{{ __('Light') }}</div>
                                    <div id="preview-light-header" class="flex items-center gap-3 px-4 py-3 text-white" style="background-color: {{ $tenant->primary_color ?? '#4f46e5' }}">
                                        <span class="text-sm font-semibold">{{ $tenant->name }}</span>
                                    </div>
                                    <div class="bg-white p-4">
                                        <button type="button" id="preview-light-btn" class="rounded-md px-3 py-1.5 text-sm font-medium text-white" style="background-color: {{ $tenant->primary_color ?? '#4f46e5' }}">{{ __('Button') }}</button>
                                        <a href="#" id="preview-light-link" class="ml-3 text-sm font-medium" style="color: {{ $tenant->primary_color ?? '#4f46e5' }}">{{ __('Link') }}</a>
                                    </div>
                                </div>
                                <div class="overflow-hidden rounded-lg border border-gray-700">
                                    <div class="text-center text-xs font-medium text-gray-400 py-1 bg-gray-800">{{ __('Dark') }}</div>
                                    <div id="preview-dark-header" class="flex items-center gap-3 px-4 py-3 text-white" style="background-color: {{ $tenant->dark_primary_color ?? '#818cf8' }}">
                                        <span class="text-sm font-semibold">{{ $tenant->name }}</span>
                                    </div>
                                    <div class="bg-gray-900 p-4">
                                        <button type="button" id="preview-dark-btn" class="rounded-md px-3 py-1.5 text-sm font-medium text-white" style="background-color: {{ $tenant->dark_primary_color ?? '#818cf8' }}">{{ __('Button') }}</button>
                                        <a href="#" id="preview-dark-link" class="ml-3 text-sm font-medium" style="color: {{ $tenant->dark_primary_color ?? '#818cf8' }}">{{ __('Link') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Save Branding') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function syncColorInput(colorId, hexId, previewIds) {
                var colorEl = document.getElementById(colorId);
                var hexEl = document.getElementById(hexId);
                colorEl.addEventListener('input', function () {
                    hexEl.value = this.value;
                    previewIds.forEach(function(id) {
                        var el = document.getElementById(id);
                        if (el) {
                            if (el.tagName === 'A') el.style.color = colorEl.value;
                            else el.style.backgroundColor = colorEl.value;
                        }
                    });
                });
            }
            syncColorInput('primary_color', 'primary_color_hex', ['preview-light-header', 'preview-light-btn', 'preview-light-link']);
            syncColorInput('accent_color', 'accent_color_hex', []);
            syncColorInput('dark_primary_color', 'dark_primary_color_hex', ['preview-dark-header', 'preview-dark-btn', 'preview-dark-link']);
            syncColorInput('dark_accent_color', 'dark_accent_color_hex', []);
        });
    </script>
</x-app-layout>
