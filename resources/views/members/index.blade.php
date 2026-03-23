<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('User Management') }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $usedSeats }} / {{ $totalSeats }} {{ __('seats used') }}</p>
            </div>
            <a href="{{ route('members.create') }}" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                <svg class="-ml-0.5 mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" />
                </svg>
                {{ __('Add User') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
            <!-- Filters -->
            <div class="mb-6 rounded-xl bg-white p-4 shadow-sm">
                <form method="GET" action="{{ route('members.index') }}" class="flex flex-wrap items-center gap-3">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name or email..." class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <select name="role" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">{{ __('All Roles') }}</option>
                        @foreach($roles as $role)
                            <option value="{{ $role }}" {{ request('role') === $role ? 'selected' : '' }}>{{ ucfirst($role) }}</option>
                        @endforeach
                    </select>
                    @if($departments->isNotEmpty())
                    <select name="department_id" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">{{ __('All Departments') }}</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                    @endif
                    <button type="submit" class="rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">{{ __('Filter') }}</button>
                    @if(request()->hasAny(['search', 'role', 'department_id']))
                        <a href="{{ route('members.index') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Clear') }}</a>
                    @endif
                    <span class="ml-auto text-sm text-gray-500">{{ $users->total() }} {{ __('users') }}</span>
                </form>
            </div>

            <!-- User Cards -->
            @if($users->isEmpty())
                <div class="rounded-xl bg-white p-12 text-center shadow-sm">
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                        <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                        </svg>
                    </div>
                    <p class="text-sm text-gray-500">{{ __('No users found.') }}</p>
                    <a href="{{ route('members.create') }}" class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        {{ __('Add a user') }}
                    </a>
                </div>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($users as $user)
                        @php
                            $isCurrentUser = $user->id === Auth::id();
                            $isInactive = $user->trashed();
                            $userRole = $user->roles->first()?->name;
                            $roleBadge = match($userRole) {
                                'admin' => 'bg-red-100 text-red-800',
                                'manager' => 'bg-blue-100 text-blue-800',
                                'agent' => 'bg-green-100 text-green-800',
                                default => 'bg-gray-100 text-gray-800',
                            };
                            $pivotRole = $user->pivot->role;
                        @endphp
                        <div class="rounded-xl bg-white p-5 shadow-sm {{ $isInactive ? 'opacity-60' : '' }}">
                            <!-- Header -->
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700 shrink-0">
                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-semibold text-gray-900">{{ $user->name }}</span>
                                            @if($isInactive)
                                                <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">{{ __('Inactive') }}</span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-gray-500">{{ $user->email }}</p>
                                    </div>
                                </div>
                                @if($pivotRole === 'owner')
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ __('Owner') }}</span>
                                @endif
                            </div>

                            <!-- Role & Department Badges -->
                            <div class="mt-3 flex flex-wrap items-center gap-1.5">
                                @if($userRole)
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $roleBadge }}">
                                        {{ ucfirst($userRole) }}
                                    </span>
                                @endif
                                @foreach($user->departments as $dept)
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" style="background-color: {{ $dept->color ?? '#e5e7eb' }}20; color: {{ $dept->color ?? '#6b7280' }}">
                                        {{ $dept->name }}
                                    </span>
                                @endforeach
                                @if($user->support_tier && app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::AgentEscalation))
                                    @php
                                        $tierLabel = match($user->support_tier) {
                                            'tier_1' => 'Tier 1',
                                            'tier_2' => 'Tier 2',
                                            'tier_3' => 'Tier 3',
                                            default => $user->support_tier,
                                        };
                                    @endphp
                                    <span class="inline-flex items-center rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-medium text-purple-800">
                                        {{ $tierLabel }}
                                    </span>
                                @endif
                            </div>

                            <!-- Stats -->
                            <div class="mt-4 grid grid-cols-2 gap-3 border-t border-gray-100 pt-3">
                                <div>
                                    <p class="text-xs text-gray-400">{{ __('Created') }}</p>
                                    <p class="text-sm font-medium text-gray-900">{{ $user->created_tickets_count ?? 0 }} {{ __('tickets') }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400">{{ __('Assigned') }}</p>
                                    <p class="text-sm font-medium text-gray-900">{{ $user->assigned_tickets_count ?? 0 }} {{ __('tickets') }}</p>
                                </div>
                            </div>

                            <!-- Join date -->
                            <p class="mt-2 text-xs text-gray-400">{{ __('Joined') }} {{ $user->pivot->joined_at ? \Carbon\Carbon::parse($user->pivot->joined_at)->format('M d, Y') : $user->created_at->format('M d, Y') }}</p>

                            <!-- Actions -->
                            <div class="mt-3 flex items-center gap-2 border-t border-gray-100 pt-3">
                                <a href="{{ route('members.show', $member = $user) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">{{ __('View') }}</a>
                                @if(!$isCurrentUser && $pivotRole !== 'owner')
                                    <a href="{{ route('members.edit', $user) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">{{ __('Edit') }}</a>
                                    <form method="POST" action="{{ route('members.destroy', $user) }}" class="inline" onsubmit="return confirm('{{ __('Are you sure you want to remove this user?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800">{{ __('Delete') }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
