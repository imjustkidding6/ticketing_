<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ ucfirst($tier) }} {{ __('SLA Policy') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('sla.update-tier', $tier) }}" class="rounded-xl bg-white p-6 shadow-sm">
                @csrf

                <div class="mb-4">
                    <p class="text-sm text-gray-600">{{ __('Define response and resolution targets for each priority level on the :tier tier.', ['tier' => $tier]) }}</p>
                    <p class="mt-1 text-sm text-gray-500">{{ __('Each target carries its own clock. Calendar hours are elapsed clock time, counting nights, weekends and holidays. Business hours count only the time your workspace is open. Time on hold pauses either clock.') }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('Changing a target or its clock affects new tickets only — tickets already open keep the deadline they were given.') }}</p>
                </div>

                @if($errors->has('rows'))
                    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{{ $errors->first('rows') }}</div>
                @endif

                @unless($calendarUsable)
                    {{-- The state every workspace is in until someone sets up a calendar, so
                         it has to say what to do rather than just greying a control out. --}}
                    <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                        {{ __('Business hours are unavailable until you set up a business-hours calendar.') }}
                        <a href="{{ route('sla.calendar.edit') }}" class="font-semibold underline">{{ __('Set up your business-hours calendar') }}</a>
                    </div>
                @endunless

                @if($tier === 'enterprise')
                    {{-- Warn, never block: the default is calendar for every enterprise cell,
                         and a tenant whose enterprise clients are weekday-only may say so. --}}
                    <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                        {{ __('Enterprise-tier clients are promised round-the-clock support. Business hours pause the clock at night and at weekends.') }}
                    </div>
                @endif

                <div class="overflow-hidden rounded-md border border-gray-200">
                    <table data-tour="sla-targets" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 text-xs font-medium uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-left">{{ __('Priority') }}</th>
                                <th class="px-4 py-2 text-center">{{ __('Response') }}</th>
                                <th class="px-4 py-2 text-center">{{ __('Resolution') }}</th>
                                <th class="px-4 py-2 text-center">{{ __('Active') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            @foreach(['critical', 'high', 'medium', 'low'] as $priority)
                                <tr>
                                    <td class="px-4 py-3">
                                        <x-badge :type="$priority">{{ ucfirst($priority) }}</x-badge>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" min="1" step="1" required
                                            name="rows[{{ $priority }}][response]"
                                            value="{{ old('rows.'.$priority.'.response', $rows[$priority]['response']) }}"
                                            class="block w-full rounded-md border-gray-300 text-sm text-center shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <select name="rows[{{ $priority }}][response_mode]"
                                            {!! $calendarUsable ? '' : 'title="'.__('Set up your business-hours calendar first').'"' !!}
                                            class="mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="calendar" {{ old('rows.'.$priority.'.response_mode', $rows[$priority]['response_mode']) === 'calendar' ? 'selected' : '' }}>{{ __('calendar hours') }}</option>
                                            <option value="business" {{ $calendarUsable ? '' : 'disabled' }} {{ old('rows.'.$priority.'.response_mode', $rows[$priority]['response_mode']) === 'business' ? 'selected' : '' }}>{{ __('business hours') }}</option>
                                        </select>
                                        @error("rows.{$priority}.response") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" min="1" step="1" required
                                            name="rows[{{ $priority }}][resolution]"
                                            value="{{ old('rows.'.$priority.'.resolution', $rows[$priority]['resolution']) }}"
                                            class="block w-full rounded-md border-gray-300 text-sm text-center shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <select name="rows[{{ $priority }}][resolution_mode]"
                                            {!! $calendarUsable ? '' : 'title="'.__('Set up your business-hours calendar first').'"' !!}
                                            class="mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="calendar" {{ old('rows.'.$priority.'.resolution_mode', $rows[$priority]['resolution_mode']) === 'calendar' ? 'selected' : '' }}>{{ __('calendar hours') }}</option>
                                            <option value="business" {{ $calendarUsable ? '' : 'disabled' }} {{ old('rows.'.$priority.'.resolution_mode', $rows[$priority]['resolution_mode']) === 'business' ? 'selected' : '' }}>{{ __('business hours') }}</option>
                                        </select>
                                        @error("rows.{$priority}.resolution") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <input type="checkbox"
                                            name="rows[{{ $priority }}][is_active]" value="1"
                                            {{ old('rows.'.$priority.'.is_active', $rows[$priority]['is_active']) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <a href="{{ route('sla.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">{{ __('Cancel') }}</a>
                    <button type="submit" data-tour="sla-save" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Save Policy') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
