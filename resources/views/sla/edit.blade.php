<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Edit SLA Policy') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('sla.update', $policy) }}">
                    @csrf
                    @method('PUT')
                    <div class="space-y-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Policy Name') }}</label>
                            <input type="text" name="name" id="name" value="{{ old('name', $policy->name) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">{{ __('Description') }}</label>
                            <textarea name="description" id="description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('description', $policy->description) }}</textarea>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="client_tier" class="block text-sm font-medium text-gray-700">{{ __('Client Tier') }}</label>
                                <select name="client_tier" id="client_tier" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">{{ __('Any Tier') }}</option>
                                    <option value="basic" {{ old('client_tier', $policy->client_tier) === 'basic' ? 'selected' : '' }}>{{ __('Basic') }}</option>
                                    <option value="premium" {{ old('client_tier', $policy->client_tier) === 'premium' ? 'selected' : '' }}>{{ __('Premium') }}</option>
                                    <option value="enterprise" {{ old('client_tier', $policy->client_tier) === 'enterprise' ? 'selected' : '' }}>{{ __('Enterprise') }}</option>
                                </select>
                            </div>
                            <div>
                                <label for="priority" class="block text-sm font-medium text-gray-700">{{ __('Priority') }}</label>
                                <select name="priority" id="priority" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">{{ __('Any Priority') }}</option>
                                    <option value="low" {{ old('priority', $policy->priority) === 'low' ? 'selected' : '' }}>{{ __('Low') }}</option>
                                    <option value="medium" {{ old('priority', $policy->priority) === 'medium' ? 'selected' : '' }}>{{ __('Medium') }}</option>
                                    <option value="high" {{ old('priority', $policy->priority) === 'high' ? 'selected' : '' }}>{{ __('High') }}</option>
                                    <option value="critical" {{ old('priority', $policy->priority) === 'critical' ? 'selected' : '' }}>{{ __('Critical') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="response_time_hours" class="block text-sm font-medium text-gray-700">{{ __('Response Time (hours)') }}</label>
                                <input type="number" name="response_time_hours" id="response_time_hours" value="{{ old('response_time_hours', $policy->response_time_hours) }}" min="1" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="resolution_time_hours" class="block text-sm font-medium text-gray-700">{{ __('Resolution Time (hours)') }}</label>
                                <input type="number" name="resolution_time_hours" id="resolution_time_hours" value="{{ old('resolution_time_hours', $policy->resolution_time_hours) }}" min="1" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                        </div>

                        <label class="flex items-center gap-3">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $policy->is_active) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm font-medium text-gray-700">{{ __('Active') }}</span>
                        </label>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <a href="{{ route('sla.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">{{ __('Cancel') }}</a>
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Update Policy') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
