<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">{{ __('Team Schedule') }}</h2>
            <a href="{{ route('schedules.index') }}" class="inline-flex items-center rounded-md bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                {{ __('My Schedule') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
            @php
                $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                $tenantTz = \App\Support\TenantTime::timezone();
                $localNow = now()->copy()->setTimezone($tenantTz);
                $todayIndex = (int) $localNow->dayOfWeek;
            @endphp

            <div class="mb-4 flex items-center justify-between rounded-md bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-900/60 px-4 py-2 text-xs">
                <div class="text-indigo-900 dark:text-indigo-200">
                    <span class="font-semibold">{{ __('Timezone') }}:</span> {{ $tenantTz }}
                    @if($tenantTz === 'UTC')
                        <span class="ml-1 text-amber-700 dark:text-amber-400">({{ __('default — set in Settings → General') }})</span>
                    @endif
                </div>
                <div class="text-indigo-900 dark:text-indigo-200">
                    <span class="font-semibold">{{ __('Now') }}:</span> {{ $localNow->format('D, g:i A') }}
                </div>
            </div>

            @forelse($agents as $agent)
                @php $onShift = $agent->isOnScheduleAt(); @endphp
                <div class="mb-6 overflow-hidden rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50 px-6 py-3 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $agent->name }}</h3>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium {{ $onShift ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $onShift ? 'bg-green-500 dark:bg-green-400' : 'bg-gray-400 dark:bg-gray-500' }}"></span>
                            {{ $onShift ? __('On shift') : __('Off shift') }}
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    @foreach($days as $index => $day)
                                        <th class="px-4 py-2 text-center text-xs font-medium uppercase tracking-wider {{ $index === $todayIndex ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300' : 'text-gray-500 dark:text-gray-400' }}">{{ $day }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                <tr>
                                    @foreach($days as $index => $day)
                                        <td class="px-4 py-3 text-center align-top {{ $index === $todayIndex ? 'bg-indigo-50/40 dark:bg-indigo-900/10' : '' }}">
                                            @php
                                                $daySchedules = $agent->schedules->where('day_of_week', $index);
                                            @endphp
                                            @forelse($daySchedules as $schedule)
                                                <div class="mb-1 rounded px-2 py-1 text-xs {{ $schedule->is_available ? 'bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300' : 'bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300' }}">
                                                    {{ \Carbon\Carbon::parse($schedule->start_time)->format('g:iA') }}-{{ \Carbon\Carbon::parse($schedule->end_time)->format('g:iA') }}
                                                </div>
                                            @empty
                                                <span class="text-xs text-gray-300 dark:text-gray-600">-</span>
                                            @endforelse
                                        </td>
                                    @endforeach
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="rounded-xl bg-white dark:bg-gray-800 p-12 text-center shadow-sm border border-gray-100 dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No agent schedules configured yet.') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>