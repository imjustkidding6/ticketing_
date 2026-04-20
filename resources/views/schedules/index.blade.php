<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('My Schedule') }}</h2>
            <a href="{{ route('schedules.team') }}" class="inline-flex items-center rounded-md bg-white border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                {{ __('Team Schedule') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            @php
                $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            @endphp

            <!-- Add Schedule Entry -->
            <div class="mb-6 overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">{{ __('Add Schedule Entry') }}</h3>
                <form method="POST" action="{{ route('schedules.store') }}" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-6">
                    @csrf
                    <div class="sm:col-span-2">
                        <label for="day_of_week" class="block text-sm font-medium text-gray-700">{{ __('Day') }}</label>
                        <select name="day_of_week" id="day_of_week" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @foreach($days as $index => $day)
                                <option value="{{ $index }}" {{ old('day_of_week') == $index ? 'selected' : '' }}>{{ $day }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="start_time" class="block text-sm font-medium text-gray-700">{{ __('Start') }}</label>
                        <input type="time" name="start_time" id="start_time" value="{{ old('start_time', '09:00') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <div>
                        <label for="end_time" class="block text-sm font-medium text-gray-700">{{ __('End') }}</label>
                        <input type="time" name="end_time" id="end_time" value="{{ old('end_time', '17:00') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <div class="flex items-end gap-3 sm:col-span-2">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="is_available" value="1" checked class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm text-gray-700">{{ __('Available') }}</span>
                        </label>
                        <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Add') }}</button>
                    </div>
                </form>
                @if($errors->any())
                    <div class="mt-3">
                        @foreach($errors->all() as $error)
                            <p class="text-sm text-red-600">{{ $error }}</p>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Weekly Schedule -->
            <div class="space-y-4">
                @foreach($days as $index => $day)
                    <div class="overflow-hidden rounded-xl bg-white shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-3">
                            <h3 class="text-sm font-semibold text-gray-900">{{ $day }}</h3>
                        </div>
                        <div class="divide-y divide-gray-200">
                            @forelse($schedules->get($index, collect()) as $schedule)
                                <div class="flex items-center justify-between px-6 py-3">
                                    <div class="flex items-center gap-4">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $schedule->is_available ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $schedule->is_available ? __('Available') : __('Unavailable') }}
                                        </span>
                                        <span class="text-sm text-gray-900">
                                            {{ \Carbon\Carbon::parse($schedule->start_time)->format('g:i A') }} - {{ \Carbon\Carbon::parse($schedule->end_time)->format('g:i A') }}
                                        </span>
                                        @if($schedule->notes)
                                            <span class="text-xs text-gray-500">{{ $schedule->notes }}</span>
                                        @endif
                                    </div>
                                    <form method="POST" action="{{ route('schedules.destroy', $schedule) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-gray-400 hover:text-red-500">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            @empty
                                <div class="px-6 py-3">
                                    <p class="text-sm text-gray-400">{{ __('No schedule set') }}</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
