<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Ticket Settings') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <!-- Settings Tabs -->
            <div class="mb-6 flex gap-4 border-b border-gray-200">
                <a href="{{ route('settings.general') }}" class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">{{ __('General') }}</a>
                <a href="{{ route('settings.ticket') }}" class="border-b-2 border-indigo-500 px-4 py-2 text-sm font-medium text-indigo-600">{{ __('Tickets') }}</a>
                @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::EmailNotifications))
                <a href="{{ route('settings.notifications') }}" class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">{{ __('Notifications') }}</a>
                @endif
                <a href="{{ route('settings.branding') }}" class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">{{ __('Branding') }}</a>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('settings.ticket') }}">
                    @csrf

                    <div class="space-y-6">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="default_priority" class="block text-sm font-medium text-gray-700">{{ __('Default Priority') }}</label>
                                <select name="default_priority" id="default_priority" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="low" {{ ($settings['default_priority'] ?? 'medium') === 'low' ? 'selected' : '' }}>{{ __('Low') }}</option>
                                    <option value="medium" {{ ($settings['default_priority'] ?? 'medium') === 'medium' ? 'selected' : '' }}>{{ __('Medium') }}</option>
                                    <option value="high" {{ ($settings['default_priority'] ?? 'medium') === 'high' ? 'selected' : '' }}>{{ __('High') }}</option>
                                    <option value="critical" {{ ($settings['default_priority'] ?? 'medium') === 'critical' ? 'selected' : '' }}>{{ __('Critical') }}</option>
                                </select>
                            </div>
                            <div>
                                <label for="ticket_prefix" class="block text-sm font-medium text-gray-700">{{ __('Ticket Prefix') }}</label>
                                <input type="text" name="ticket_prefix" id="ticket_prefix" value="{{ old('ticket_prefix', $settings['ticket_prefix'] ?? 'TKT') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                        </div>

                        <div>
                            <label class="flex items-center gap-3">
                                <input type="checkbox" name="auto_assignment" value="1" {{ ($settings['auto_assignment'] ?? false) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm font-medium text-gray-700">{{ __('Enable auto-assignment') }}</span>
                            </label>
                            <p class="ml-8 text-xs text-gray-500">{{ __('Automatically assign tickets based on department and availability.') }}</p>
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
