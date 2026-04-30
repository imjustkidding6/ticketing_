<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Team Schedule') }}</h2>
            <a href="{{ route('schedules.index') }}" class="inline-flex items-center rounded-md bg-white border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
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

            <div class="mb-4 flex items-center justify-between rounded-md bg-indigo-50 border border-indigo-200 px-4 py-2 text-xs">
                <div class="text-indigo-900">
                    <span class="font-semibold">{{ __('Timezone') }}:</span> {{ $tenantTz }}
                    @if($tenantTz === 'UTC')
                        <span class="ml-1 text-amber-700">({{ __('default — set in Settings → General') }})</span>
                    @endif
                </div>
                <div class="text-indigo-900">
                    <span class="font-semibold">{{ __('Now') }}:</span> {{ $localNow->format('D, g:i A') }}
                </div>
            </div>

            @forelse($agents as $agent)
                @php $onShift = $agent->isOnScheduleAt(); @endphp
                <div class="mb-6 overflow-hidden rounded-xl bg-white shadow-sm">
                    <div class="border-b border-gray-200 bg-gray-50 px-6 py-3 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900">{{ $agent->name }}</h3>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium {{ $onShift ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $onShift ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                            {{ $onShift ? __('On shift') : __('Off shift') }}
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    @foreach($days as $index => $day)
                                        <th class="px-4 py-2 text-center text-xs font-medium uppercase tracking-wider {{ $index === $todayIndex ? 'bg-indigo-50 text-indigo-700' : 'text-gray-500' }}">{{ $day }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    @foreach($days as $index => $day)
                                        <td class="px-4 py-3 text-center align-top {{ $index === $todayIndex ? 'bg-indigo-50/40' : '' }}">
                                            @php
                                                $daySchedules = $agent->schedules->where('day_of_week', $index);
                                            @endphp
                                            @forelse($daySchedules as $schedule)
                                                <div class="mb-1 rounded px-2 py-1 text-xs {{ $schedule->is_available ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                                                    {{ \Carbon\Carbon::parse($schedule->start_time)->format('g:iA') }}-{{ \Carbon\Carbon::parse($schedule->end_time)->format('g:iA') }}
                                                </div>
                                            @empty
                                                <span class="text-xs text-gray-300">-</span>
                                            @endforelse
                                        </td>
                                    @endforeach
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="rounded-xl bg-white p-12 text-center shadow-sm">
                    <p class="text-sm text-gray-500">{{ __('No agent schedules configured yet.') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
