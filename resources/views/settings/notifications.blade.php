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

            <form method="POST" action="{{ route('settings.notifications') }}">
                @csrf

                {{-- Notification Triggers --}}
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('Email Notification Triggers') }}</h3>
                    <p class="mt-1 text-xs text-gray-500">{{ __('Choose which events send email notifications.') }}</p>

                    <div class="mt-4 space-y-4">
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
                </div>

                {{-- SMTP Configuration --}}
                <div class="mt-6 rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('SMTP Configuration') }}</h3>
                    <p class="mt-1 text-xs text-gray-500">{{ __('Configure your outgoing email server. Leave blank to use the system default.') }}</p>

                    <div class="mt-4 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="mail_host" class="block text-sm font-medium text-gray-700">{{ __('SMTP Host') }}</label>
                                <input type="text" name="mail_host" id="mail_host" value="{{ old('mail_host', $settings['mail_host'] ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="smtp.gmail.com">
                                @error('mail_host') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="mail_port" class="block text-sm font-medium text-gray-700">{{ __('SMTP Port') }}</label>
                                <input type="number" name="mail_port" id="mail_port" value="{{ old('mail_port', $settings['mail_port'] ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="587">
                                @error('mail_port') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="mail_username" class="block text-sm font-medium text-gray-700">{{ __('Username') }}</label>
                                <input type="text" name="mail_username" id="mail_username" value="{{ old('mail_username', $settings['mail_username'] ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="your@email.com">
                                @error('mail_username') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="mail_password" class="block text-sm font-medium text-gray-700">{{ __('Password') }}</label>
                                <input type="password" name="mail_password" id="mail_password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="{{ ($settings['mail_password'] ?? false) ? '••••••••' : '' }}">
                                <p class="mt-1 text-xs text-gray-500">{{ __('Leave blank to keep current password.') }}</p>
                                @error('mail_password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label for="mail_encryption" class="block text-sm font-medium text-gray-700">{{ __('Encryption') }}</label>
                            <select name="mail_encryption" id="mail_encryption" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="tls" {{ ($settings['mail_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' }}>TLS</option>
                                <option value="ssl" {{ ($settings['mail_encryption'] ?? '') === 'ssl' ? 'selected' : '' }}>SSL</option>
                                <option value="none" {{ ($settings['mail_encryption'] ?? '') === 'none' ? 'selected' : '' }}>{{ __('None') }}</option>
                            </select>
                            @error('mail_encryption') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="mail_from_address" class="block text-sm font-medium text-gray-700">{{ __('From Address') }}</label>
                                <input type="email" name="mail_from_address" id="mail_from_address" value="{{ old('mail_from_address', $settings['mail_from_address'] ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="support@company.com">
                                @error('mail_from_address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="mail_from_name" class="block text-sm font-medium text-gray-700">{{ __('From Name') }}</label>
                                <input type="text" name="mail_from_name" id="mail_from_name" value="{{ old('mail_from_name', $settings['mail_from_name'] ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="Company Support">
                                @error('mail_from_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Admin Notification Email --}}
                <div class="mt-6 rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('Admin Notification Email') }}</h3>
                    <p class="mt-1 text-xs text-gray-500">{{ __('This email address will receive all ticket notifications (created, assigned, status changes). Separate from the SMTP From Address.') }}</p>
                    <div class="mt-4">
                        <label for="admin_notification_email" class="block text-sm font-medium text-gray-700">{{ __('Admin Email') }}</label>
                        <input type="email" name="admin_notification_email" id="admin_notification_email" value="{{ old('admin_notification_email', $settings['admin_notification_email'] ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="admin@company.com">
                        @error('admin_notification_email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Save Settings') }}</button>
                </div>
            </form>

            {{-- Test Email --}}
            <div class="mt-6 rounded-xl bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900">{{ __('Test Email') }}</h3>
                <p class="mt-1 text-xs text-gray-500">{{ __('Send a test email to verify your SMTP configuration is working.') }}</p>

                <form method="POST" action="{{ route('settings.notifications.test') }}" class="mt-4 flex items-end gap-3">
                    @csrf
                    <div class="flex-1">
                        <label for="test_email" class="block text-sm font-medium text-gray-700">{{ __('Recipient Email') }}</label>
                        <input type="email" name="test_email" id="test_email" required value="{{ Auth::user()->email }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="test@example.com">
                        @error('test_email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500">
                        <svg class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                        </svg>
                        {{ __('Send Test') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
