<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Create Client') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('clients.store') }}">
                    @csrf

                    <div class="space-y-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Company / Client Name') }}</label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">{{ __('Email') }}</label>
                                <input type="email" name="email" id="email" value="{{ old('email') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700">{{ __('Phone') }}</label>
                                <input type="text" name="phone" id="phone" value="{{ old('phone') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label for="contact_person" class="block text-sm font-medium text-gray-700">{{ __('Contact Person') }}</label>
                            <input type="text" name="contact_person" id="contact_person" value="{{ old('contact_person') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @error('contact_person') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="address" class="block text-sm font-medium text-gray-700">{{ __('Address') }}</label>
                            <textarea name="address" id="address" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('address') }}</textarea>
                            @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @php $hasSla = app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::SlaManagement); @endphp
                            <div x-data="{ selectedTier: '{{ old('tier', 'basic') }}' }">
                                <label for="tier" class="block text-sm font-medium text-gray-700">{{ $hasSla ? __('SLA Tier') : __('Tier') }}</label>
                                <select name="tier" id="tier" required x-model="selectedTier" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="basic">{{ __('Basic') }}</option>
                                    <option value="premium">{{ __('Premium') }}</option>
                                    <option value="enterprise">{{ __('Enterprise') }}</option>
                                </select>
                                @error('tier') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                                @if($hasSla)
                                @foreach(['basic', 'premium', 'enterprise'] as $tier)
                                    <div x-show="selectedTier === '{{ $tier }}'" x-cloak class="mt-2 rounded-md bg-gray-50 border border-gray-200 p-3">
                                        @if(($slaPolicies[$tier] ?? collect())->isNotEmpty())
                                            <table class="w-full text-xs">
                                                <thead>
                                                    <tr class="text-gray-500">
                                                        <th class="text-left py-1">{{ __('Priority') }}</th>
                                                        <th class="text-right py-1">{{ __('Response') }}</th>
                                                        <th class="text-right py-1">{{ __('Resolution') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($slaPolicies[$tier]->sortBy('priority') as $policy)
                                                        <tr class="text-gray-600 border-t border-gray-200">
                                                            <td class="py-1 font-medium">{{ ucfirst($policy->priority ?? 'Any') }}</td>
                                                            <td class="py-1 text-right">{{ $policy->response_time_hours }}h</td>
                                                            <td class="py-1 text-right">{{ $policy->resolution_time_hours }}h</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        @else
                                            <p class="text-xs text-gray-400">{{ __('No SLA policies configured for this tier.') }}</p>
                                        @endif
                                    </div>
                                @endforeach
                                @endif
                            </div>
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">{{ __('Status') }}</label>
                                <select name="status" id="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                                    <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                                </select>
                                @error('status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <a href="{{ route('clients.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">{{ __('Cancel') }}</a>
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Create Client') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
