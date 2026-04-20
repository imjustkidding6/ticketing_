<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('General Settings') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <!-- Settings Tabs -->
            <div class="mb-6 flex gap-4 border-b border-gray-200">
                <a href="{{ route('settings.general') }}" class="border-b-2 border-indigo-500 px-4 py-2 text-sm font-medium text-indigo-600">{{ __('General') }}</a>
                <a href="{{ route('settings.ticket') }}" class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">{{ __('Tickets') }}</a>
                @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::EmailNotifications))
                <a href="{{ route('settings.notifications') }}" class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">{{ __('Notifications') }}</a>
                @endif
                <a href="{{ route('settings.branding') }}" class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">{{ __('Branding') }}</a>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('settings.general') }}">
                    @csrf

                    <div class="space-y-6">
                        <div>
                            <label for="company_name" class="block text-sm font-medium text-gray-700">{{ __('Company Name') }}</label>
                            <input type="text" name="company_name" id="company_name" value="{{ old('company_name', $settings['company_name'] ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @error('company_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Company Contact Info --}}
                        <div class="border-t border-gray-200 pt-6">
                            <h3 class="text-sm font-semibold text-gray-900">{{ __('Company Contact Information') }}</h3>
                            <p class="mt-1 text-xs text-gray-500">{{ __('Used in service reports (PDF) and email notifications.') }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="company_email" class="block text-sm font-medium text-gray-700">{{ __('Support Email') }}</label>
                                <input type="email" name="company_email" id="company_email" value="{{ old('company_email', $settings['company_email'] ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="support@company.com">
                                @error('company_email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="company_phone" class="block text-sm font-medium text-gray-700">{{ __('Phone') }}</label>
                                <input type="text" name="company_phone" id="company_phone" value="{{ old('company_phone', $settings['company_phone'] ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="+1 234 567 8900">
                                @error('company_phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label for="company_address" class="block text-sm font-medium text-gray-700">{{ __('Address') }}</label>
                            <textarea name="company_address" id="company_address" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="123 Main St, City, State 12345">{{ old('company_address', $settings['company_address'] ?? '') }}</textarea>
                            @error('company_address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="company_website" class="block text-sm font-medium text-gray-700">{{ __('Website') }}</label>
                            <input type="url" name="company_website" id="company_website" value="{{ old('company_website', $settings['company_website'] ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="https://www.company.com">
                            @error('company_website') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="border-t border-gray-200 pt-6">
                            <h3 class="text-sm font-semibold text-gray-900">{{ __('Regional Settings') }}</h3>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="timezone" class="block text-sm font-medium text-gray-700">{{ __('Timezone') }}</label>
                                <select name="timezone" id="timezone" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    @foreach(['UTC', 'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles', 'Europe/London', 'Europe/Paris', 'Asia/Tokyo', 'Asia/Singapore', 'Australia/Sydney'] as $tz)
                                        <option value="{{ $tz }}" {{ ($settings['timezone'] ?? 'UTC') === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                                    @endforeach
                                </select>
                                @error('timezone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="date_format" class="block text-sm font-medium text-gray-700">{{ __('Date Format') }}</label>
                                <select name="date_format" id="date_format" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    @foreach(['Y-m-d' => '2026-03-02', 'm/d/Y' => '03/02/2026', 'd/m/Y' => '02/03/2026', 'M d, Y' => 'Mar 02, 2026'] as $format => $example)
                                        <option value="{{ $format }}" {{ ($settings['date_format'] ?? 'M d, Y') === $format ? 'selected' : '' }}>{{ $example }}</option>
                                    @endforeach
                                </select>
                                @error('date_format') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Save Settings') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
