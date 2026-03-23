<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Notification Settings') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <!-- Settings Tabs -->
            <div class="mb-6 flex gap-4 border-b border-gray-200">
                <a href="{{ route('settings.general') }}" class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">{{ __('General') }}</a>
                <a href="{{ route('settings.ticket') }}" class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">{{ __('Tickets') }}</a>
                @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::EmailNotifications))
                <a href="{{ route('settings.notifications') }}" class="border-b-2 border-indigo-500 px-4 py-2 text-sm font-medium text-indigo-600">{{ __('Notifications') }}</a>
                @endif
                <a href="{{ route('settings.branding') }}" class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">{{ __('Branding') }}</a>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('settings.notifications') }}">
                    @csrf

                    <div class="space-y-4">
                        <p class="text-sm text-gray-500">{{ __('Configure which events trigger email notifications.') }}</p>

                        <label class="flex items-center gap-3">
                            <input type="checkbox" name="notify_on_ticket_create" value="1" {{ ($settings['notify_on_ticket_create'] ?? false) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm font-medium text-gray-700">{{ __('Ticket Created') }}</span>
                        </label>

                        <label class="flex items-center gap-3">
                            <input type="checkbox" name="notify_on_ticket_assign" value="1" {{ ($settings['notify_on_ticket_assign'] ?? false) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm font-medium text-gray-700">{{ __('Ticket Assigned') }}</span>
                        </label>

                        <label class="flex items-center gap-3">
                            <input type="checkbox" name="notify_on_ticket_close" value="1" {{ ($settings['notify_on_ticket_close'] ?? false) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm font-medium text-gray-700">{{ __('Ticket Closed') }}</span>
                        </label>

                        <label class="flex items-center gap-3">
                            <input type="checkbox" name="notify_on_comment" value="1" {{ ($settings['notify_on_comment'] ?? false) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm font-medium text-gray-700">{{ __('New Comment Added') }}</span>
                        </label>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Save Settings') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
