<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">{{ __('Clients') }}</h2>
            <a href="{{ route('clients.create') }}" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition-colors">
                <svg class="-ml-0.5 mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ __('New Client') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
            {{-- Filters Bar --}}
            <div class="mb-4 rounded-xl bg-white dark:bg-gray-800 p-4 shadow-sm border border-gray-100 dark:border-gray-700">
                <form method="GET" action="{{ route('clients.index') }}" class="flex flex-wrap items-center gap-3">
                    <div class="flex-1 min-w-50">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search clients...') }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:placeholder-gray-400">
                    </div>
                    <select name="tier" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                        <option value="">{{ __('All Tiers') }}</option>
                        <option value="basic" {{ request('tier') === 'basic' ? 'selected' : '' }}>{{ __('Basic') }}</option>
                        <option value="premium" {{ request('tier') === 'premium' ? 'selected' : '' }}>{{ __('Premium') }}</option>
                        <option value="enterprise" {{ request('tier') === 'enterprise' ? 'selected' : '' }}>{{ __('Enterprise') }}</option>
                    </select>
                    <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                        <option value="">{{ __('All Statuses') }}</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                    </select>
                    <button type="submit" class="rounded-md bg-gray-100 dark:bg-gray-700 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">{{ __('Filter') }}</button>
                    @if(request()->hasAny(['search', 'tier', 'status']))
                        <a href="{{ route('clients.index') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors">{{ __('Clear') }}</a>
                    @endif
                </form>
            </div>

            {{-- Table --}}
            <x-data-table>
                <thead class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <x-sortable-th column="name" :label="__('Client')" />
                        <x-sortable-th column="contact_person" :label="__('Contact')" />
                        <x-sortable-th column="tier" :label="__('Tier')" />
                        <x-sortable-th column="status" :label="__('Status')" />
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                    @forelse($clients as $client)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="whitespace-nowrap px-6 py-4">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $client->name }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $client->email }}</div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ $client->contact_person ?? '-' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <x-badge :type="$client->tier">{{ ucfirst($client->tier) }}</x-badge>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <x-badge :type="$client->status === 'active' ? 'active' : 'inactive'">{{ $client->status === 'active' ? __('Active') : __('Inactive') }}</x-badge>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                <a href="{{ route('clients.show', $client) }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 transition-colors">{{ __('View') }}</a>
                                <a href="{{ route('clients.edit', $client) }}" class="ml-3 text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 transition-colors">{{ __('Edit') }}</a>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state :colspan="5" :message="__('No clients found.')" :action-url="route('clients.create')" :action-label="__('Add a client')">
                            <x-slot name="icon">
                                <svg class="h-6 w-6 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                </svg>
                            </x-slot>
                        </x-empty-state>
                    @endforelse
                </tbody>
            </x-data-table>

            <div class="mt-4">
                {{ $clients->links() }}
            </div>
        </div>
    </div>
</x-app-layout>