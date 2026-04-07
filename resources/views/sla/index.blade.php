<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('SLA Policies') }}</h2>
            <a href="{{ route('sla.create') }}" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                <svg class="-ml-0.5 mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ __('New Policy') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
            <x-data-table>
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Name') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Client Tier') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Priority') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Response') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Resolution') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Status') }}</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($policies as $policy)
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $policy->name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $policy->client_tier ? ucfirst($policy->client_tier) : __('Any') }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $policy->priority ? ucfirst($policy->priority) : __('Any') }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $policy->response_time_hours }}h</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $policy->resolution_time_hours }}h</td>
                            <td class="px-6 py-4">
                                <x-badge :type="$policy->is_active ? 'active' : 'inactive'">{{ $policy->is_active ? __('Active') : __('Inactive') }}</x-badge>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                <a href="{{ route('sla.edit', $policy) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('Edit') }}</a>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state :colspan="7" :message="__('No SLA policies found.')" :action-url="route('sla.create')" :action-label="__('Create a policy')">
                            <x-slot name="icon">
                                <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                                </svg>
                            </x-slot>
                        </x-empty-state>
                    @endforelse
                </tbody>
            </x-data-table>
            <div class="mt-4">{{ $policies->links() }}</div>
        </div>
    </div>
</x-app-layout>
