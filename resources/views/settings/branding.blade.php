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

                    <div class="space-y-6">
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

                        <!-- Colors -->
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label for="primary_color" class="block text-sm font-medium text-gray-700">{{ __('Primary Color') }}</label>
                                <p class="mt-1 text-xs text-gray-500">{{ __('Used for header, buttons, and links on the portal.') }}</p>
                                <div class="mt-2 flex items-center gap-3">
                                    <input type="color" name="primary_color" id="primary_color" value="{{ old('primary_color', $tenant->primary_color ?? '#4f46e5') }}" class="h-10 w-14 cursor-pointer rounded border border-gray-300">
                                    <input type="text" id="primary_color_hex" value="{{ old('primary_color', $tenant->primary_color ?? '#4f46e5') }}" class="w-28 rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" readonly>
                                </div>
                                @error('primary_color') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="accent_color" class="block text-sm font-medium text-gray-700">{{ __('Accent Color') }}</label>
                                <p class="mt-1 text-xs text-gray-500">{{ __('Used for hover states and secondary elements.') }}</p>
                                <div class="mt-2 flex items-center gap-3">
                                    <input type="color" name="accent_color" id="accent_color" value="{{ old('accent_color', $tenant->accent_color ?? '#4338ca') }}" class="h-10 w-14 cursor-pointer rounded border border-gray-300">
                                    <input type="text" id="accent_color_hex" value="{{ old('accent_color', $tenant->accent_color ?? '#4338ca') }}" class="w-28 rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" readonly>
                                </div>
                                @error('accent_color') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <!-- Preview -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('Preview') }}</label>
                            <div id="branding-preview" class="mt-2 overflow-hidden rounded-lg border border-gray-200">
                                <div id="preview-header" class="flex items-center gap-3 px-4 py-3 text-white" style="background-color: {{ $tenant->primary_color ?? '#4f46e5' }}">
                                    @if($tenant->logo_path)
                                        <img src="{{ $tenant->logoUrl() }}" alt="Logo" class="h-8 w-auto">
                                    @else
                                        <span class="text-sm font-semibold">{{ $tenant->name }}</span>
                                    @endif
                                </div>
                                <div class="bg-gray-50 p-4">
                                    <p class="text-sm text-gray-600">{{ __('This is how your portal header will look.') }}</p>
                                    <button type="button" id="preview-button" class="mt-2 rounded-md px-3 py-1.5 text-sm font-medium text-white" style="background-color: {{ $tenant->primary_color ?? '#4f46e5' }}">{{ __('Sample Button') }}</button>
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

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const primaryColor = document.getElementById('primary_color');
            const primaryHex = document.getElementById('primary_color_hex');
            const accentColor = document.getElementById('accent_color');
            const accentHex = document.getElementById('accent_color_hex');
            const previewHeader = document.getElementById('preview-header');
            const previewButton = document.getElementById('preview-button');

            primaryColor.addEventListener('input', function () {
                primaryHex.value = this.value;
                previewHeader.style.backgroundColor = this.value;
                previewButton.style.backgroundColor = this.value;
            });

            accentColor.addEventListener('input', function () {
                accentHex.value = this.value;
            });
        });
    </script>
    @endpush
</x-app-layout>
