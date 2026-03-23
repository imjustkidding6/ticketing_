<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3">
                <a href="{{ route('members.index') }}" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </a>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $member->name }}</h2>
                @if($member->trashed())
                    <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">{{ __('Inactive') }}</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">{{ __('Active') }}</span>
                @endif
            </div>
            @if($pivotRole !== 'owner' && $member->id !== Auth::id())
                <a href="{{ route('members.edit', $member) }}" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    <svg class="-ml-0.5 mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                    </svg>
                    {{ __('Edit') }}
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Left Column: Stats & Account Info -->
                <div class="space-y-6">
                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-3">
                        <div class="rounded-xl bg-white p-4 text-center shadow-sm">
                            <p class="text-2xl font-semibold text-gray-900">{{ $stats['created'] }}</p>
                            <p class="text-xs text-gray-500">{{ __('Created') }}</p>
                        </div>
                        <div class="rounded-xl bg-white p-4 text-center shadow-sm">
                            <p class="text-2xl font-semibold text-gray-900">{{ $stats['assigned'] }}</p>
                            <p class="text-xs text-gray-500">{{ __('Assigned') }}</p>
                        </div>
                        <div class="rounded-xl bg-white p-4 text-center shadow-sm">
                            <p class="text-2xl font-semibold text-gray-900">{{ $stats['closed'] }}</p>
                            <p class="text-xs text-gray-500">{{ __('Closed') }}</p>
                        </div>
                    </div>

                    <!-- Account Information -->
                    <div class="rounded-xl bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ __('Account Information') }}</h3>
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-xs text-gray-400">{{ __('Email') }}</dt>
                                <dd class="text-sm text-gray-900">{{ $member->email }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-400">{{ __('Status') }}</dt>
                                <dd class="text-sm">
                                    @if($member->trashed())
                                        <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">{{ __('Inactive') }}</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">{{ __('Active') }}</span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-400">{{ __('Joined') }}</dt>
                                <dd class="text-sm text-gray-900">{{ $member->created_at->format('M d, Y') }}</dd>
                            </div>
                            @if($member->support_tier && app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::AgentEscalation))
                            <div>
                                <dt class="text-xs text-gray-400">{{ __('Support Tier') }}</dt>
                                <dd class="text-sm">
                                    @php
                                        $tierLabel = match($member->support_tier) {
                                            'tier_1' => 'Tier 1',
                                            'tier_2' => 'Tier 2',
                                            'tier_3' => 'Tier 3',
                                            default => $member->support_tier,
                                        };
                                        $tierBadge = match($member->support_tier) {
                                            'tier_1' => 'bg-green-100 text-green-800',
                                            'tier_2' => 'bg-blue-100 text-blue-800',
                                            'tier_3' => 'bg-purple-100 text-purple-800',
                                            default => 'bg-gray-100 text-gray-800',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $tierBadge }}">{{ $tierLabel }}</span>
                                </dd>
                            </div>
                            @endif
                            @if($member->departments->isNotEmpty())
                            <div>
                                <dt class="text-xs text-gray-400">{{ __('Departments') }}</dt>
                                <dd class="text-sm text-gray-900">{{ $member->departments->pluck('name')->join(', ') }}</dd>
                            </div>
                            @endif
                            <div>
                                <dt class="text-xs text-gray-400">{{ __('Roles') }}</dt>
                                <dd class="mt-1 flex flex-wrap gap-1">
                                    @if($pivotRole === 'owner')
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">{{ __('Owner') }}</span>
                                    @endif
                                    @forelse($member->roles as $role)
                                        @php
                                            $roleBadge = match($role->name) {
                                                'admin' => 'bg-red-100 text-red-800',
                                                'manager' => 'bg-blue-100 text-blue-800',
                                                'agent' => 'bg-green-100 text-green-800',
                                                default => 'bg-gray-100 text-gray-800',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $roleBadge }}">{{ ucfirst($role->name) }}</span>
                                    @empty
                                        <span class="text-sm text-gray-400">{{ __('No role assigned') }}</span>
                                    @endforelse
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Permissions -->
                    @if($member->roles->isNotEmpty())
                    <div class="rounded-xl bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-gray-900 mb-3">{{ __('Permissions') }}</h3>
                        <div class="grid grid-cols-1 gap-1.5">
                            @foreach($member->getAllPermissions() as $permission)
                                <div class="flex items-center gap-2 text-sm">
                                    <svg class="h-4 w-4 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                    <span class="text-gray-700">{{ ucfirst($permission->name) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>

                <!-- Right Column: Recent Activity & Assigned Tickets -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Recent Tickets Created -->
                    <div class="rounded-xl bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ __('Recent Tickets Created') }}</h3>
                        @if($recentCreated->isEmpty())
                            <p class="text-sm text-gray-400 py-4 text-center">{{ __('No tickets created yet.') }}</p>
                        @else
                            <div class="space-y-3">
                                @foreach($recentCreated as $ticket)
                                    <a href="{{ route('tickets.show', $ticket) }}" class="flex items-center justify-between rounded-lg border border-gray-100 p-3 hover:bg-gray-50 transition-colors">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-mono text-gray-400">#{{ $ticket->ticket_number }}</span>
                                                <span class="text-sm font-medium text-gray-900">{{ Str::limit($ticket->subject, 50) }}</span>
                                            </div>
                                            @if($ticket->client)
                                                <p class="mt-0.5 text-xs text-gray-500">{{ $ticket->client->name }}</p>
                                            @endif
                                        </div>
                                        <span class="text-xs text-gray-400 shrink-0">{{ $ticket->created_at->format('M d, Y') }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Assigned Tickets -->
                    <div class="rounded-xl bg-white shadow-sm">
                        <div class="border-b border-gray-200 px-5 py-4">
                            <h3 class="text-sm font-semibold text-gray-900">{{ __('Assigned Tickets') }}</h3>
                        </div>
                        @if($assignedTickets->isEmpty())
                            <p class="text-sm text-gray-400 py-8 text-center">{{ __('No tickets assigned.') }}</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Ticket') }}</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Client') }}</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Status') }}</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Priority') }}</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Created') }}</th>
                                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white">
                                        @foreach($assignedTickets as $ticket)
                                            <tr>
                                                <td class="whitespace-nowrap px-4 py-3">
                                                    <div class="text-sm font-medium text-gray-900">{{ Str::limit($ticket->subject, 40) }}</div>
                                                    <div class="text-xs text-gray-400 font-mono">#{{ $ticket->ticket_number }}</div>
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                                    {{ $ticket->client?->name ?? '-' }}
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3">
                                                    <x-badge :type="$ticket->status">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</x-badge>
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3">
                                                    <x-badge :type="$ticket->priority">{{ ucfirst($ticket->priority) }}</x-badge>
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                                    {{ $ticket->created_at->format('M d, Y') }}
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                                    <a href="{{ route('tickets.show', $ticket) }}" class="text-sm text-indigo-600 hover:text-indigo-900">{{ __('View') }}</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
