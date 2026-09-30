{{--
    Skills index (spec §6): name, category, agent count, status, actions.

    No description column, deliberately — §6 does not list one and every seeded skill has a
    NULL description, so the column would be hundreds of blank cells. It lives on the form.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Skills') }}</h2>
            <a href="{{ route('skills.create') }}" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                <svg class="-ml-0.5 mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ __('New Skill') }}
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
                <form method="GET" action="{{ route('skills.index') }}" class="flex flex-wrap items-center gap-3">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search skills..." class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <select name="category" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">{{ __('All Categories') }}</option>
                        @foreach($categories as $category)
                            <option value="{{ $category }}" {{ request('category') === $category ? 'selected' : '' }}>{{ $category }}</option>
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
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Category') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Agents') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Status') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Updated') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse($skills as $skill)
                            <tr>
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ $skill->name }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    @if($skill->skill_category)
                                        <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">{{ $skill->skill_category }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                {{-- A skill no agent holds shows 0 and stays deletable; it is vocabulary nobody has used yet, not a fault. --}}
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ trans_choice(':count agent|:count agents', $skill->ai_agents_count, ['count' => $skill->ai_agents_count]) }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    @if($skill->is_active)
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">{{ __('Active') }}</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">{{ __('Inactive') }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">@localdt($skill->updated_at, 'M d, Y g:i A')</td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <a href="{{ route('skills.edit', $skill) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('Edit') }}</a>
                                    <form action="{{ route('skills.toggle', $skill) }}" method="POST" class="inline ml-3">
                                        @csrf
                                        <button type="submit" class="text-gray-600 hover:text-gray-900">{{ $skill->is_active ? __('Deactivate') : __('Activate') }}</button>
                                    </form>
                                    <form action="{{ route('skills.destroy', $skill) }}" method="POST" class="inline ml-3" onsubmit="return confirm('{{ __('Delete this skill?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900">{{ __('Delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500">
                                    {{ __('No skills yet — install the default catalogue or create one.') }}
                                    <span class="mt-2 block">
                                        <a href="{{ route('ai-agents.catalogue.preview') }}" class="font-medium text-indigo-600 hover:text-indigo-500">{{ __('Install the default catalogue') }}</a>
                                        <span class="text-gray-400">&middot;</span>
                                        <a href="{{ route('skills.create') }}" class="font-medium text-indigo-600 hover:text-indigo-500">{{ __('Create a skill') }}</a>
                                    </span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $skills->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
