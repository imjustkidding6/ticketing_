<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Service Report Settings') }}</h2>
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
                <a href="{{ route('settings.branding') }}" class="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">{{ __('Branding') }}</a>
                <a href="{{ route('settings.service-report') }}" class="border-b-2 border-indigo-500 px-4 py-2 text-sm font-medium text-indigo-600">{{ __('Service Report') }}</a>
            </div>

            @if(session('success'))
                <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('success') }}</div>
            @endif

            <div class="rounded-xl bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('settings.service-report') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="space-y-8">
                        {{-- Logo --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('Report Logo') }}</label>
                            <p class="mt-1 text-xs text-gray-500">{{ __('Printed at the top of each service-report PDF. If empty, your Portal Logo is used. Recommended: 400x120px, max 2MB.') }}</p>

                            <div class="mt-3 flex items-center gap-6">
                                @if($tenant->service_report_logo_path)
                                    <div class="shrink-0">
                                        <img src="{{ $tenant->serviceReportLogoUrl() }}" alt="{{ __('Report logo') }}" class="h-16 w-auto rounded border border-gray-200 object-contain p-1">
                                    </div>
                                @elseif($tenant->logo_path)
                                    <div class="shrink-0 relative">
                                        <img src="{{ $tenant->logoUrl() }}" class="h-16 w-auto rounded border border-gray-200 object-contain p-1 opacity-70">
                                        <span class="absolute -bottom-5 left-0 text-[10px] text-gray-400">{{ __('using portal logo') }}</span>
                                    </div>
                                @else
                                    <div class="flex h-16 w-32 shrink-0 items-center justify-center rounded border-2 border-dashed border-gray-300 text-xs text-gray-400">
                                        {{ __('No logo') }}
                                    </div>
                                @endif
                                <div class="flex flex-col gap-2">
                                    <input type="file" name="service_report_logo" accept="image/jpeg,image/png,image/svg+xml" class="text-sm text-gray-500 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                                    @if($tenant->service_report_logo_path)
                                        <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                                            <input type="checkbox" name="remove_service_report_logo" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            {{ __('Remove current logo') }}
                                        </label>
                                    @endif
                                </div>
                            </div>
                            @error('service_report_logo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Automation --}}
                        <div class="border-t border-gray-200 pt-6">
                            <h3 class="text-sm font-semibold text-gray-900">{{ __('Automation') }}</h3>
                            <label class="mt-3 flex items-start gap-3">
                                <input type="checkbox" name="auto_generate_on_close" value="1" {{ ($settings['auto_generate_on_close'] ?? '1') === '1' ? 'checked' : '' }} class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">
                                    <span class="font-medium">{{ __('Auto-generate on ticket close') }}</span>
                                    <span class="block text-xs text-gray-500">{{ __('Automatically create a service report PDF each time a ticket is closed.') }}</span>
                                </span>
                            </label>
                        </div>

                        {{-- Layout --}}
                        <div class="border-t border-gray-200 pt-6">
                            <h3 class="text-sm font-semibold text-gray-900">{{ __('Report Layout') }}</h3>
                            <div class="mt-4 space-y-4">
                                <div>
                                    <label for="report_title" class="block text-sm font-medium text-gray-700">{{ __('Report Title') }}</label>
                                    <input type="text" name="report_title" id="report_title" value="{{ old('report_title', $settings['report_title'] ?? 'Service Report') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    @error('report_title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="report_footer" class="block text-sm font-medium text-gray-700">{{ __('Report Footer') }}</label>
                                    <textarea name="report_footer" id="report_footer" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="{{ __('e.g. Thank you for choosing us.') }}">{{ old('report_footer', $settings['report_footer'] ?? '') }}</textarea>
                                    @error('report_footer') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <label class="flex items-start gap-3">
                                    <input type="checkbox" name="show_sla_metrics" value="1" {{ ($settings['show_sla_metrics'] ?? '1') === '1' ? 'checked' : '' }} class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="text-sm text-gray-700">
                                        <span class="font-medium">{{ __('Include SLA metrics') }}</span>
                                        <span class="block text-xs text-gray-500">{{ __('Show response/resolution SLA status on the PDF.') }}</span>
                                    </span>
                                </label>
                                <label class="flex items-start gap-3">
                                    <input type="checkbox" name="show_tasks" value="1" {{ ($settings['show_tasks'] ?? '1') === '1' ? 'checked' : '' }} class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="text-sm text-gray-700">
                                        <span class="font-medium">{{ __('Include task list') }}</span>
                                        <span class="block text-xs text-gray-500">{{ __('Show the ticket\'s tasks and their status on the PDF.') }}</span>
                                    </span>
                                </label>
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
