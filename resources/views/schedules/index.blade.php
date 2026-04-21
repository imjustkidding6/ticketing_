<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ __('Weekly Schedule') }} — <span class="font-normal text-gray-500">{{ $target->name }}</span>
            </h2>
            <a href="{{ route('schedules.team') }}" class="text-sm text-indigo-600 hover:text-indigo-900">{{ __('View team schedule') }} →</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800">{{ session('success') }}</div>
            @endif

            @if($canManageOthers && $manageableAgents->isNotEmpty())
                <form method="GET" class="mb-4 flex items-center gap-3">
                    <label for="user_switch" class="text-sm font-medium text-gray-700">{{ __('Editing schedule for:') }}</label>
                    <select name="user_id" id="user_switch" onchange="this.form.submit()" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach($manageableAgents as $agent)
                            <option value="{{ $agent->id }}" {{ $target->id === $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif

            <form method="POST" action="{{ route('schedules.save') }}" class="rounded-xl bg-white p-6 shadow-sm">
                @csrf
                @if($target->id !== auth()->id())
                    <input type="hidden" name="user_id" value="{{ $target->id }}">
                @endif

                <p class="mb-4 text-sm text-gray-600">{{ __('Set when this agent is available each day. Toggle the checkbox to mark a day as unavailable.') }}</p>

                <div class="overflow-hidden rounded-md border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 text-xs font-medium uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-left">{{ __('Day') }}</th>
                                <th class="px-4 py-2 text-center">{{ __('Available') }}</th>
                                <th class="px-4 py-2 text-center">{{ __('Start') }}</th>
                                <th class="px-4 py-2 text-center">{{ __('End') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            @foreach($days as $dayIndex => $dayName)
                                @php $row = $week[$dayIndex]; @endphp
                                <tr class="{{ $row['available'] ? '' : 'bg-gray-50' }}">
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $dayName }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <input type="checkbox" name="week[{{ $dayIndex }}][available]" value="1"
                                            {{ $row['available'] ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="time" required step="60"
                                            name="week[{{ $dayIndex }}][start]"
                                            value="{{ old('week.'.$dayIndex.'.start', $row['start']) }}"
                                            class="w-full rounded-md border-gray-300 text-sm text-center shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        @error("week.{$dayIndex}.start") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="time" required step="60"
                                            name="week[{{ $dayIndex }}][end]"
                                            value="{{ old('week.'.$dayIndex.'.end', $row['end']) }}"
                                            class="w-full rounded-md border-gray-300 text-sm text-center shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        @error("week.{$dayIndex}.end") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex items-center justify-end">
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        {{ __('Save Schedule') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
