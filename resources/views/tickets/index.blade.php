<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">{{ __('Tickets') }}</h2>
            <div class="flex items-center gap-2">
                @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::SpamManagement))
                    <a href="{{ route('tickets.spam') }}" class="inline-flex items-center rounded-md bg-red-50 dark:bg-red-500/10 px-3 py-2 text-sm font-medium text-red-700 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-500/20 transition-colors" title="{{ __('Spam tickets') }}">
                        <svg class="-ml-0.5 mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                        {{ __('Spam') }}
                    </a>
                @endif
                <a href="{{ route('tickets.create') }}" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition-colors">
                    <svg class="-ml-0.5 mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ __('New Ticket') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
            {{-- Filters Bar --}}
            <div class="mb-4 rounded-xl bg-white dark:bg-gray-800 p-4 shadow-sm border border-gray-100 dark:border-gray-700">
                <form method="GET" action="{{ route('tickets.index') }}" class="flex flex-wrap items-center gap-3">
                    <div class="flex-1 min-w-50">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search tickets...') }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:placeholder-gray-400">
                    </div>
                    <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                        <option value="">{{ __('All Statuses') }}</option>
                        <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>{{ __('Open') }}</option>
                        <option value="assigned" {{ request('status') === 'assigned' ? 'selected' : '' }}>{{ __('Assigned') }}</option>
                        <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>{{ __('In Progress') }}</option>
                        <option value="on_hold" {{ request('status') === 'on_hold' ? 'selected' : '' }}>{{ __('On Hold') }}</option>
                        <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>{{ __('Closed') }}</option>
                        <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>{{ __('Cancelled') }}</option>
                    </select>
                    <select name="priority" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                        <option value="">{{ __('All Priorities') }}</option>
                        <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>{{ __('Low') }}</option>
                        <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>{{ __('Medium') }}</option>
                        <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>{{ __('High') }}</option>
                        <option value="critical" {{ request('priority') === 'critical' ? 'selected' : '' }}>{{ __('Critical') }}</option>
                    </select>
                    <select name="department_id" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                        <option value="">{{ __('All Departments') }}</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                    <select name="assigned_to" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                        <option value="">{{ __('All Agents') }}</option>
                        @foreach($agents as $agent)
                            <option value="{{ $agent->id }}" {{ request('assigned_to') == $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                        @endforeach
                    </select>
                    @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::TicketMerging))
                        <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 cursor-pointer select-none">
                            <input type="checkbox" name="show_merged" value="1" {{ request()->boolean('show_merged') ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700">
                            {{ __('Show merged') }}
                        </label>
                    @endif
                    <button type="submit" class="rounded-md bg-gray-100 dark:bg-gray-700 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">{{ __('Filter') }}</button>
                    @if(request()->hasAny(['search', 'status', 'priority', 'department_id', 'assigned_to', 'show_merged']))
                        <a href="{{ route('tickets.index') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors">{{ __('Clear') }}</a>
                    @endif
                </form>
            </div>

            {{-- Table --}}
            <x-data-table>
                <thead class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <x-sortable-th column="ticket_number" :label="__('Ticket')" />
                        <x-sortable-th column="client" :label="__('Client')" />
                        <x-sortable-th column="priority" :label="__('Priority')" />
                        <x-sortable-th column="status" :label="__('Status')" />
                        <x-sortable-th column="assignee" :label="__('Assigned To')" />
                        <x-sortable-th column="created_at" :label="__('Created')" />
                        <x-sortable-th column="resolution" :label="__('Resolution')" />
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                    @forelse($tickets as $ticket)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors {{ $ticket->isOverdue() ? 'bg-red-50/60 dark:bg-red-950/20' : '' }}">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium flex items-center gap-2 flex-wrap">
                                    <a href="{{ route('tickets.show', $ticket) }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 transition-colors font-mono">{{ $ticket->ticket_number }}</a>
                                    @if(($ticket->merged_tickets_count ?? 0) > 0)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-purple-100 dark:bg-purple-900/40 px-2 py-0.5 text-[10px] font-medium text-purple-700 dark:text-purple-300" title="{{ __('Merged from other tickets') }}">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7v8a4 4 0 0 0 4 4 4 4 0 0 0 4-4V7M4 11h16" />
                                            </svg>
                                            +{{ $ticket->merged_tickets_count }} {{ __('merged') }}
                                        </span>
                                    @endif
                                    @if($ticket->is_merged)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-[10px] font-medium text-gray-600 dark:text-gray-300">{{ __('merged archive') }}</span>
                                    @endif
                                </div>
                                <div class="text-sm text-gray-900 dark:text-gray-200 mt-0.5">{{ Str::limit($ticket->subject, 50) }}</div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ $ticket->client?->name ?? '-' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <x-badge :type="$ticket->priority">{{ ucfirst($ticket->priority) }}</x-badge>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <x-badge :type="$ticket->status">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</x-badge>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ $ticket->assignee?->name ?? __('Unassigned') }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                @localdt($ticket->created_at, 'M d, Y')
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ \App\Models\Ticket::formatHours($ticket->getEffectiveResolutionTimeHours()) }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                <a href="{{ route('tickets.show', $ticket) }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 transition-colors">{{ __('View') }}</a>
                                <a href="{{ route('tickets.edit', $ticket) }}" class="ml-3 text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300 transition-colors">{{ __('Edit') }}</a>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state :colspan="8" :message="__('No tickets found.')" :action-url="route('tickets.create')" :action-label="__('Create your first ticket')">
                            <x-slot name="icon">
                                <svg class="h-6 w-6 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z" />
                                </svg>
                            </x-slot>
                        </x-empty-state>
                    @endforelse
                </tbody>
            </x-data-table>

            <div class="mt-4">
                {{ $tickets->links() }}
            </div>
        </div>
    </div>
</x-app-layout>