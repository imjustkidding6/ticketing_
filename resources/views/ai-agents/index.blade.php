<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('AI Agents') }}</h2>
            <a href="{{ route('ai-agents.create') }}" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                <svg class="-ml-0.5 mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ __('New Agent') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
            <div class="mb-4">
                <a href="{{ route('ai-agents.setup') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; {{ __('Back to AI agents') }}</a>
            </div>

            @if(session('success'))
                <div class="mb-4 rounded-md bg-green-50 p-4">
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
            @endif

            @error('delete')
                <div class="mb-4 rounded-md bg-red-50 p-4">
                    <p class="text-sm font-medium text-red-800">{{ $message }}</p>
                </div>
            @enderror

            <!-- Filters -->
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <form method="GET" action="{{ route('ai-agents.index') }}" class="flex flex-wrap items-center gap-3">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search agents..." class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <select name="role_type" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">{{ __('All Roles') }}</option>
                        @foreach(\App\Models\AiAgent::ROLE_TYPES as $roleType)
                            <option value="{{ $roleType }}" {{ request('role_type') === $roleType ? 'selected' : '' }}>{{ ucfirst($roleType) }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">{{ __('All Statuses') }}</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                    </select>
                    <button type="submit" class="rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">{{ __('Filter') }}</button>
                </form>
            </div>

            <div class="overflow-hidden rounded-xl bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Name') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Role') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Skills') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Departments') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Priority') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Status') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Updated') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse($agents as $agent)
                            <tr>
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                    {{ $agent->name }}
                                    @if($agent->is_default)
                                        <span class="ml-2 inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800">{{ __('Default') }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ ucfirst($agent->role_type) }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ $agent->skills_count }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ $agent->departments_count }}</td>
                                {{-- 0 means unranked under the zero-last ordering rule, so it is not printed as a rank. --}}
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ $agent->priority ?: '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    @if($agent->is_active)
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">{{ __('Active') }}</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">{{ __('Inactive') }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">@localdt($agent->updated_at, 'M d, Y g:i A')</td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <a href="{{ route('ai-agents.edit', $agent) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('Edit') }}</a>
                                    <form action="{{ route('ai-agents.toggle', $agent) }}" method="POST" class="inline ml-3">
                                        @csrf
                                        <button type="submit" class="text-gray-600 hover:text-gray-900">{{ $agent->is_active ? __('Deactivate') : __('Activate') }}</button>
                                    </form>
                                    <form action="{{ route('ai-agents.destroy', $agent) }}" method="POST" class="inline ml-3" onsubmit="return confirm('{{ __('Delete this agent?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900">{{ __('Delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-sm text-gray-500">
                                    {{ __('No agents configured yet — install the default catalogue or create one.') }}
                                    <span class="mt-2 block">
                                        <a href="{{ route('ai-agents.catalogue.preview') }}" class="font-medium text-indigo-600 hover:text-indigo-500">{{ __('Install the default catalogue') }}</a>
                                        <span class="text-gray-400">&middot;</span>
                                        <a href="{{ route('ai-agents.create') }}" class="font-medium text-indigo-600 hover:text-indigo-500">{{ __('Create an agent') }}</a>
                                    </span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $agents->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
