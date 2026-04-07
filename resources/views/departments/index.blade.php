<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Departments') }}</h2>
            @php
                $tenant = Auth::user()->currentTenant();
                $canManage = $tenant?->plan()?->hasFeature('department_management');
            @endphp
            @if($canManage)
                <a href="{{ route('departments.create') }}" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    <svg class="-ml-0.5 mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ __('New Department') }}
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
            <x-data-table>
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Department') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Code') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Categories') }}</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Status') }}</th>
                        @if($canManage)
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($departments as $department)
                        <tr>
                            <td class="whitespace-nowrap px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="h-3 w-3 rounded-full" style="background-color: {{ $department->color }}"></div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">{{ $department->name }}</div>
                                        @if($department->description)
                                            <div class="text-sm text-gray-500 truncate max-w-xs">{{ $department->description }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                <span class="inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $department->code }}</span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                {{ $department->categories_count }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <x-badge :type="$department->is_active ? 'active' : 'inactive'">{{ $department->is_active ? __('Active') : __('Inactive') }}</x-badge>
                                @if($department->is_default)
                                    <x-badge type="default_tag">{{ __('Default') }}</x-badge>
                                @endif
                            </td>
                            @if($canManage)
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <a href="{{ route('departments.edit', $department) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('Edit') }}</a>
                                    @unless($department->is_default)
                                        <form action="{{ route('departments.destroy', $department) }}" method="POST" class="inline ml-3" onsubmit="return confirm('Are you sure?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900">{{ __('Delete') }}</button>
                                        </form>
                                    @endunless
                                </td>
                            @endif
                        </tr>
                    @empty
                        <x-empty-state :colspan="$canManage ? 5 : 4" :message="__('No departments found.')">
                            <x-slot name="icon">
                                <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                </svg>
                            </x-slot>
                        </x-empty-state>
                    @endforelse
                </tbody>
            </x-data-table>
            <div class="mt-4">
                {{ $departments->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
