<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('SLA Compliance Report') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
            <!-- Date Filter -->
            <div class="mb-6 overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                <form method="GET" class="flex flex-wrap items-end gap-4">
                    <div>
                        <label for="from" class="block text-sm font-medium text-gray-700">{{ __('From') }}</label>
                        <input type="date" name="from" id="from" value="{{ $report['from'] }}" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <div>
                        <label for="to" class="block text-sm font-medium text-gray-700">{{ __('To') }}</label>
                        <input type="date" name="to" id="to" value="{{ $report['to'] }}" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Filter') }}</button>
                </form>
            </div>

            <!-- Compliance Overview -->
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-6">
                <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-gray-500">{{ __('Tickets with SLA') }}</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ $report['total_with_sla'] }}</p>
                </div>
                <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-gray-500">{{ __('Response Compliance') }}</p>
                    <p class="mt-2 text-3xl font-bold {{ $report['response_compliance'] >= 90 ? 'text-green-600' : ($report['response_compliance'] >= 70 ? 'text-yellow-600' : 'text-red-600') }}">
                        {{ $report['response_compliance'] }}%
                    </p>
                </div>
                <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-gray-500">{{ __('Resolution Compliance') }}</p>
                    <p class="mt-2 text-3xl font-bold {{ $report['resolution_compliance'] >= 90 ? 'text-green-600' : ($report['resolution_compliance'] >= 70 ? 'text-yellow-600' : 'text-red-600') }}">
                        {{ $report['resolution_compliance'] }}%
                    </p>
                </div>
                <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-gray-500">{{ __('Overall') }}</p>
                    @php
                        $overall = $report['total_with_sla'] > 0
                            ? round(($report['response_compliance'] + $report['resolution_compliance']) / 2, 1)
                            : 0;
                    @endphp
                    <p class="mt-2 text-3xl font-bold {{ $overall >= 90 ? 'text-green-600' : ($overall >= 70 ? 'text-yellow-600' : 'text-red-600') }}">
                        {{ $overall }}%
                    </p>
                </div>
            </div>

            <!-- Response SLA -->
            <div class="grid gap-6 sm:grid-cols-2 mb-6">
                <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Response SLA') }}</h3>
                    <div class="mt-4 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">{{ __('Met') }}</span>
                            <span class="text-sm font-semibold text-green-600">{{ $report['response_met'] }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">{{ __('Missed') }}</span>
                            <span class="text-sm font-semibold text-red-600">{{ $report['response_missed'] }}</span>
                        </div>
                        @if($report['response_met'] + $report['response_missed'] > 0)
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="bg-green-500 h-3 rounded-full" style="width: {{ $report['response_compliance'] }}%"></div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Resolution SLA') }}</h3>
                    <div class="mt-4 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">{{ __('Met') }}</span>
                            <span class="text-sm font-semibold text-green-600">{{ $report['resolution_met'] }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">{{ __('Missed') }}</span>
                            <span class="text-sm font-semibold text-red-600">{{ $report['resolution_missed'] }}</span>
                        </div>
                        @if($report['resolution_met'] + $report['resolution_missed'] > 0)
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="bg-green-500 h-3 rounded-full" style="width: {{ $report['resolution_compliance'] }}%"></div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <a href="{{ route('reports.overview') }}" class="text-sm text-indigo-600 hover:text-indigo-900">{{ __('Back to Reports') }}</a>
            </div>
        </div>
    </div>
</x-app-layout>
