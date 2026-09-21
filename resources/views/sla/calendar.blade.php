{{--
    The workspace's definition of "when we are open". One calendar per workspace; the
    tables allow more and a picker can be added additively later.

    Replace-all: the whole week and the whole holiday list post together and the
    controller swaps both child sets inside a transaction. Per-row AJAX across three
    tables to edit one logical record would be the wrong shape here.

    Times are wall-clock strings, not instants, so nothing on this page goes through
    TenantTime::format() — "we open at nine" means nine on the clock on the wall, in the
    calendar's own timezone.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Business Hours Calendar') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900 space-y-1">
                <p>{{ __('These hours are used by any SLA target you have set to business hours. A day with no shifts is a closed day, and two shifts on one day is a closed lunch.') }}</p>
                <p>{{ __('Tickets already open keep the deadline they were given.') }}</p>
                <p class="font-semibold">{{ __('Changing these hours affects reporting for tickets already closed.') }}</p>
                @if($consequence)
                    {{-- The sentence this whole feature exists to answer, computed with the
                         same SlaClock the deadlines use rather than written by hand. --}}
                    <p class="font-semibold">{{ __('A 48 business-hour target on this calendar is about :working working days — roughly :calendar days on the wall calendar.', ['working' => $consequence['working_days'], 'calendar' => $consequence['calendar_days']]) }}</p>
                @endif
                @if($businessTargetCount > 0)
                    <p>{{ trans_choice('{1}:count SLA target currently uses business hours.|[2,*]:count SLA targets currently use business hours.', $businessTargetCount, ['count' => $businessTargetCount]) }}</p>
                @endif
            </div>

            <form method="POST" action="{{ route('sla.calendar.update') }}" class="rounded-xl bg-white p-6 shadow-sm space-y-8"
                x-data="{
                    windows: {{ Js::from(old('windows', $windows)) }},
                    days: {{ Js::from(old('days', $dayOpen)) }},
                    holidays: {{ Js::from(old('holidays', $holidays)) }},
                    addShift(day) { (this.windows[day] ||= []).push({ start: '09:00', end: '17:00' }) },
                    open(day) { return !!this.days[day] },
                    toggle(day) {
                        // Switching a day on with nothing in it would leave the admin
                        // staring at a validation error, so give it the ordinary day.
                        if (this.days[day] && !(this.windows[day] || []).length) this.addShift(day)
                    },
                    addHoliday() { this.holidays.push({ name: '', month: 1, day: 1, year: null, recurring: true }) },
                }">
                @csrf

                <div>
                    <label for="timezone" class="block text-sm font-medium text-gray-700">{{ __('Timezone') }}</label>
                    <select id="timezone" name="timezone" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach(timezone_identifiers_list() as $tz)
                            <option value="{{ $tz }}" {{ old('timezone', $timezone) === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">{{ __('The calendar carries its own timezone. It starts from your workspace timezone, but changing where reports are read will not move your deadlines.') }}</p>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('Which days is support available, and between what times?') }}</h3>
                    <p class="mt-1 text-xs text-gray-500">{{ __('Untick a day to say you are closed then — weekends included. A day that is ticked needs an opening and a closing time.') }}</p>
                    <div class="mt-3 divide-y divide-gray-100 rounded-md border border-gray-200">
                        @foreach([1 => __('Monday'), 2 => __('Tuesday'), 3 => __('Wednesday'), 4 => __('Thursday'), 5 => __('Friday'), 6 => __('Saturday'), 7 => __('Sunday')] as $iso => $label)
                            <div class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-start">
                                <label class="flex w-40 items-center gap-2 pt-1 text-sm font-medium text-gray-700">
                                    <input type="checkbox" value="1" x-model="days[{{ $iso }}]" @change="toggle({{ $iso }})"
                                        name="days[{{ $iso }}]"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    {{ $label }}
                                </label>
                                <div class="flex-1 space-y-2">
                                    <template x-if="!open({{ $iso }})">
                                        <p class="text-sm text-gray-500">{{ __('Closed — no support on this day, and the SLA clock does not run.') }}</p>
                                    </template>
                                    <template x-for="(row, index) in (open({{ $iso }}) ? (windows[{{ $iso }}] || []) : [])" :key="index">
                                        <div class="flex items-center gap-2">
                                            <input type="time" x-model="row.start" :name="`windows[{{ $iso }}][${index}][start]`"
                                                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <span class="text-sm text-gray-400">&ndash;</span>
                                            <input type="time" x-model="row.end" :name="`windows[{{ $iso }}][${index}][end]`"
                                                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <button type="button" @click="windows[{{ $iso }}].splice(index, 1)"
                                                class="rounded-md px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50">{{ __('Remove') }}</button>
                                        </div>
                                    </template>
                                    {{-- A second shift on the same day is how a closed lunch is expressed, so
                                         the break does not count as working time. --}}
                                    <button type="button" x-show="open({{ $iso }})" @click="addShift({{ $iso }})"
                                        class="text-xs font-medium text-indigo-600 hover:text-indigo-500">{{ __('+ Add a shift (split the day around a break)') }}</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('Holidays') }}</h3>
                    <p class="mt-1 text-xs text-gray-500">{{ __('Holidays repeat every year unless you untick the box — a calendar nobody repopulates in January is how agents get flagged for breaching on days nobody worked.') }}</p>
                    <div class="mt-3 space-y-2">
                        <template x-for="(holiday, index) in holidays" :key="index">
                            <div class="flex flex-wrap items-center gap-2 rounded-md border border-gray-200 px-3 py-2">
                                <input type="text" x-model="holiday.name" :name="`holidays[${index}][name]`" placeholder="{{ __('Christmas Day') }}"
                                    class="min-w-[10rem] flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <select x-model.number="holiday.month" :name="`holidays[${index}][month]`"
                                    class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach(range(1, 12) as $m)
                                        <option value="{{ $m }}">{{ \Carbon\Carbon::create(2024, $m, 1)->format('M') }}</option>
                                    @endforeach
                                </select>
                                <input type="number" min="1" max="31" x-model.number="holiday.day" :name="`holidays[${index}][day]`"
                                    class="w-20 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <label class="flex items-center gap-1.5 text-xs text-gray-600">
                                    <input type="checkbox" x-model="holiday.recurring" :name="`holidays[${index}][recurring]`" value="1"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    {{ __('repeats every year') }}
                                </label>
                                <input type="number" min="1970" max="2999" x-show="!holiday.recurring" x-model.number="holiday.year" :name="`holidays[${index}][year]`" placeholder="{{ __('Year') }}"
                                    class="w-24 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <button type="button" @click="holidays.splice(index, 1)"
                                    class="rounded-md px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50">{{ __('Remove') }}</button>
                            </div>
                        </template>
                        <p x-show="!holidays.length" class="text-sm text-gray-500">{{ __('No holidays yet.') }}</p>
                        <button type="button" @click="addHoliday()"
                            class="text-xs font-medium text-indigo-600 hover:text-indigo-500">{{ __('+ Add a holiday') }}</button>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                    <a href="{{ route('sla.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">{{ __('Cancel') }}</a>
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Save business hours') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
