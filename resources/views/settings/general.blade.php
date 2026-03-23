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
