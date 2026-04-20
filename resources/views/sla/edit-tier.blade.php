<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ ucfirst($tier) }} {{ __('SLA Policy') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('sla.update-tier', $tier) }}" class="rounded-xl bg-white p-6 shadow-sm">
                @csrf

                <div class="mb-4">
                    <p class="text-sm text-gray-600">{{ __('Define response and resolution targets (in hours) for each priority level on the :tier tier.', ['tier' => $tier]) }}</p>
                </div>

                <div class="overflow-hidden rounded-md border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 text-xs font-medium uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-left">{{ __('Priority') }}</th>
                                <th class="px-4 py-2 text-center">{{ __('Response (hours)') }}</th>
                                <th class="px-4 py-2 text-center">{{ __('Resolution (hours)') }}</th>
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
                                        @error("rows.{$priority}.response") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" min="1" step="1" required
                                            name="rows[{{ $priority }}][resolution]"
                                            value="{{ old('rows.'.$priority.'.resolution', $rows[$priority]['resolution']) }}"
                                            class="block w-full rounded-md border-gray-300 text-sm text-center shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
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
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Save Policy') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
