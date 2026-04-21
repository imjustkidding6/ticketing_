<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Create SLA Policies') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('sla.store') }}">
                    @csrf
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Policy Name') }}</label>
                                <input type="text" name="name" id="name" value="{{ old('name') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="{{ __('e.g. Standard SLA, Premium SLA') }}">
                                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="client_tier" class="block text-sm font-medium text-gray-700">{{ __('Client Tier') }}</label>
                                <select name="client_tier" id="client_tier" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">{{ __('Any Tier') }}</option>
                                    @foreach($tiers as $tier)
                                        <option value="{{ $tier }}" {{ old('client_tier') === $tier ? 'selected' : '' }}>{{ __(ucfirst($tier)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">{{ __('Description') }}</label>
                            <textarea name="description" id="description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('description') }}</textarea>
                        </div>

                        <div>
                            <h3 class="text-sm font-medium text-gray-700 mb-3">{{ __('Response & Resolution Times by Priority') }}</h3>
                            <p class="text-xs text-gray-500 mb-4">{{ __('Define the response and resolution times (in hours) for each priority level. Uncheck a priority to skip it.') }}</p>
                            @if($errors->any())
                                <div class="mb-4 rounded-md bg-red-50 p-3">
                                    <p class="text-sm text-red-700">{{ __('Please correct the errors below.') }}</p>
                                </div>
                            @endif

                            <div class="overflow-hidden rounded-lg border border-gray-200">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Include') }}</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Priority') }}</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Response Time (hours)') }}</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Resolution Time (hours)') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white">
                                        @foreach(['critical' => ['response' => 1, 'resolution' => 4], 'high' => ['response' => 4, 'resolution' => 8], 'medium' => ['response' => 8, 'resolution' => 24], 'low' => ['response' => 24, 'resolution' => 72]] as $priority => $defaults)
                                            <tr x-data="{ enabled: {{ old('priorities.'.$priority.'.enabled', 'true') }} }">
                                                <td class="px-4 py-3">
                                                    <input type="hidden" name="priorities[{{ $priority }}][enabled]" value="0">
                                                    <input type="checkbox" name="priorities[{{ $priority }}][enabled]" value="1"
                                                        x-model="enabled"
                                                        {{ old('priorities.'.$priority.'.enabled', true) ? 'checked' : '' }}
                                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                </td>
                                                <td class="px-4 py-3">
                                                    <span class="inline-flex items-center gap-2 text-sm font-medium text-gray-900">
                                                        @switch($priority)
                                                            @case('critical')
                                                                <span class="inline-block h-2.5 w-2.5 rounded-full bg-red-500"></span>
                                                                @break
                                                            @case('high')
                                                                <span class="inline-block h-2.5 w-2.5 rounded-full bg-orange-500"></span>
                                                                @break
                                                            @case('medium')
                                                                <span class="inline-block h-2.5 w-2.5 rounded-full bg-yellow-500"></span>
                                                                @break
                                                            @case('low')
                                                                <span class="inline-block h-2.5 w-2.5 rounded-full bg-blue-500"></span>
                                                                @break
                                                        @endswitch
                                                        {{ ucfirst($priority) }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <input type="number"
                                                        name="priorities[{{ $priority }}][response_time_hours]"
                                                        value="{{ old('priorities.'.$priority.'.response_time_hours', $defaults['response']) }}"
                                                        min="1"
                                                        :disabled="!enabled"
                                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100 disabled:text-gray-400">
                                                    @error('priorities.'.$priority.'.response_time_hours') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                                </td>
                                                <td class="px-4 py-3">
                                                    <input type="number"
                                                        name="priorities[{{ $priority }}][resolution_time_hours]"
                                                        value="{{ old('priorities.'.$priority.'.resolution_time_hours', $defaults['resolution']) }}"
                                                        min="1"
                                                        :disabled="!enabled"
                                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100 disabled:text-gray-400">
                                                    @error('priorities.'.$priority.'.resolution_time_hours') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <label class="flex items-center gap-3">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm font-medium text-gray-700">{{ __('Active') }}</span>
                        </label>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <a href="{{ route('sla.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">{{ __('Cancel') }}</a>
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Create Policies') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
