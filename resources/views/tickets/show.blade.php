<x-app-layout>
    <x-slot name="header">
        <nav class="text-sm text-gray-500">
            <a href="{{ route('dashboard') }}" class="hover:text-gray-700">{{ __('Dashboard') }}</a>
            <span class="mx-1">/</span>
            <a href="{{ route('tickets.index') }}" class="hover:text-gray-700">{{ __('Tickets') }}</a>
            <span class="mx-1">/</span>
            <span class="text-gray-800">{{ __('View') }}</span>
        </nav>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
            {{-- Ticket Number & Actions --}}
            <div class="mb-6 flex items-start justify-between">
                <h1 class="text-2xl font-bold text-gray-900">{{ $ticket->ticket_number }}</h1>
                <div class="flex items-center gap-2">
                    <a href="{{ route('tickets.edit', $ticket) }}" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        <svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                        </svg>
                        {{ __('Edit') }}
                    </a>
                    <a href="{{ route('tickets.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                        {{ __('Back to Tickets') }}
                    </a>
                </div>
                <div class="mt-2 flex items-center gap-2">
                    @php
                        $completedTasks = $ticket->tasks->where('status', 'completed')->count();
                        $totalTasks = $ticket->tasks->count();
                    @endphp
                    @if($totalTasks > 0)
                        <x-badge :type="$completedTasks === $totalTasks ? 'completed' : 'in_progress'">
                            {{ $completedTasks === $totalTasks ? __('COMPLETE SET') : __('INCOMPLETE SET') }}
                        </x-badge>
                    @endif
                    <x-badge :type="$ticket->status">{{ strtoupper(str_replace('_', ' ', $ticket->status)) }}</x-badge>
                    <x-badge :type="$ticket->priority">{{ strtoupper($ticket->priority) }}</x-badge>
                    @if($ticket->isOverdue())
                        <x-badge type="overdue">{{ __('OVERDUE') }}</x-badge>
                    @endif
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                {{-- Left Column (Main Content) --}}
                <div class="lg:col-span-2 space-y-6">
                    {{-- Subject --}}
                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Subject') }}</h4>
                        <p class="mt-1 text-lg font-medium text-gray-900">{{ $ticket->subject }}</p>
                    </div>

                    {{-- Description --}}
                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Description') }}</h4>
                        <div class="mt-2 prose prose-sm max-w-none text-gray-700">
                            {!! nl2br(e($ticket->description)) !!}
                        </div>
                    </div>

                    {{-- Ticket Details --}}
                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Client') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    @if($ticket->client)
                                        <a href="{{ route('clients.show', $ticket->client) }}" class="text-indigo-600 hover:text-indigo-900">{{ $ticket->client->name }}</a>
                                    @else
                                        -
                                    @endif
                                </dd>
                            </div>
                            @if($ticket->category)
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Category') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $ticket->category->name }}</dd>
                            </div>
                            @endif
                            @if($ticket->department)
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Department') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $ticket->department->name }}</dd>
                            </div>
                            @endif
                            @if($ticket->products->count() > 0)
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Products / Services') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ $ticket->products->pluck('name')->join(', ') }}
                                </dd>
                            </div>
                            @endif
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Created By') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $ticket->creator?->name ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Created') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $ticket->created_at->format('m/d/Y, g:i:s A') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Incident Date/Time') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $ticket->incident_date ? $ticket->incident_date->format('m/d/Y, g:i:s A') : '-' }}</dd>
                            </div>
                            @if($ticket->preferred_service_date)
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Preferred Service Time') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $ticket->preferred_service_date->format('m/d/Y, g:i:s A') }}</dd>
                            </div>
                            @endif
                        </dl>
                    </div>

                    {{-- Task Checklist --}}
                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Task Checklist') }}</h4>
                            <span class="text-sm text-gray-500">
                                {{ $ticket->tasks->where('status', 'completed')->count() }}/{{ $ticket->tasks->count() }} {{ __('completed') }}
                            </span>
                        </div>

                        @if($ticket->tasks->count() > 0)
                            <ul class="mt-4 divide-y divide-gray-200">
                                @foreach($ticket->tasks->sortBy('sort_order') as $task)
                                    <li class="py-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="flex items-start gap-3 min-w-0 flex-1">
                                                @if($task->status === 'completed')
                                                    <div class="mt-0.5 h-5 w-5 shrink-0 rounded border-2 border-green-500 bg-green-500 flex items-center justify-center">
                                                        <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                        </svg>
                                                    </div>
                                                @elseif($task->status === 'cancelled')
                                                    <div class="mt-0.5 h-5 w-5 shrink-0 rounded border-2 border-gray-300 bg-gray-100 flex items-center justify-center">
                                                        <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </div>
                                                @else
                                                    <div class="mt-0.5 h-5 w-5 shrink-0 rounded border-2 {{ $task->status === 'in_progress' ? 'border-indigo-400 bg-indigo-50' : 'border-gray-300' }}"></div>
                                                @endif
                                                <div class="min-w-0">
                                                    <span class="text-sm {{ $task->isCompleted() ? 'text-gray-400 line-through' : ($task->status === 'cancelled' ? 'text-gray-400 line-through' : 'text-gray-900') }}">{{ $task->description }}</span>
                                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-0.5">
                                                        @if($task->assignee)
                                                            <span class="text-xs text-gray-400">{{ $task->assignee->name }}</span>
                                                        @endif
                                                        @if($task->completed_at)
                                                            <span class="text-xs text-green-600">{{ __('Completed') }} {{ $task->completed_at->format('m/d/Y, g:i A') }}</span>
                                                        @endif
                                                    </div>
                                                    @if($task->notes)
                                                        <p class="mt-0.5 text-xs text-gray-500">{{ $task->notes }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0">
                                                @if(!in_array($ticket->status, ['closed', 'cancelled']) && $task->canChangeStatus())
                                                    <form method="POST" action="{{ route('tickets.tasks.status', [$ticket, $task]) }}" class="flex items-center gap-1">
                                                        @csrf
                                                        <select name="status" onchange="this.form.submit()" class="rounded-md border-gray-300 text-xs py-1 pl-2 pr-7 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                            @foreach(['pending', 'in_progress', 'completed', 'cancelled'] as $ts)
                                                                <option value="{{ $ts }}" {{ $task->status === $ts ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $ts)) }}</option>
                                                            @endforeach
                                                        </select>
                                                    </form>
                                                @else
                                                    <x-badge :type="$task->status">{{ ucfirst(str_replace('_', ' ', $task->status)) }}</x-badge>
                                                @endif
                                                @if(!in_array($ticket->status, ['closed', 'cancelled']))
                                                    <form method="POST" action="{{ route('tickets.tasks.destroy', [$ticket, $task]) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-gray-400 hover:text-red-500" title="{{ __('Remove') }}">
                                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="mt-6 text-center py-8">
                                <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" />
                                </svg>
                                <p class="mt-2 text-sm text-gray-500">{{ __('No tasks created yet.') }}</p>
                            </div>
                        @endif

                        {{-- Add Task Form --}}
                        @if(!in_array($ticket->status, ['closed', 'cancelled']))
                            <form method="POST" action="{{ route('tickets.tasks.store', $ticket) }}" class="mt-4 flex items-end gap-3 border-t border-gray-200 pt-4">
                                @csrf
                                <div class="flex-1">
                                    <input type="text" name="description" required placeholder="{{ __('Add a task...') }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                </div>
                                <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                                    <svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                    {{ __('Add Task') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- Right Column (Sidebar) --}}
                <div class="lg:col-span-1 space-y-6">
                    {{-- Assignment & Priority --}}
                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Assignment & Priority') }}</h4>
                        <div class="mt-4 space-y-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('Assigned to') }}</dt>
                                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $ticket->assignee?->name ?? __('Unassigned') }}</dd>
                            </div>

                            @if(!in_array($ticket->status, ['closed', 'cancelled']))
                                @if(!$ticket->assigned_to)
                                    <form method="POST" action="{{ route('tickets.self-assign', $ticket) }}">
                                        @csrf
                                        <button type="submit" class="w-full rounded-md bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100">{{ __('Assign to Me') }}</button>
                                    </form>
                                @endif

                                @php
                                    $showSlaPolicy = $ticket->client?->tier ? \App\Models\SlaPolicy::active()
                                        ->where('client_tier', $ticket->client->tier)
                                        ->whereNotNull('priority')
                                        ->get()
                                        ->keyBy('priority') : collect();
                                @endphp

                                <form method="POST" action="{{ route('tickets.assign', $ticket) }}">
                                    @csrf
                                    <div class="space-y-3">
                                        <div>
                                            <label for="sidebar_assigned_to" class="block text-sm font-medium text-gray-500">{{ __('Assign To') }}</label>
                                            <select name="assigned_to" id="sidebar_assigned_to" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="">{{ __('Select agent') }}</option>
                                                @foreach($agents as $agent)
                                                    <option value="{{ $agent->id }}" {{ $ticket->assigned_to == $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label for="sidebar_priority" class="block text-sm font-medium text-gray-500">{{ __('Priority') }}</label>
                                            <select name="priority" id="sidebar_priority" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                @foreach(['low', 'medium', 'high', 'critical'] as $p)
                                                    <option value="{{ $p }}" {{ $ticket->priority === $p ? 'selected' : '' }}>
                                                        {{ ucfirst($p) }}{{ isset($showSlaPolicy[$p]) ? ' — Reso: ' . $showSlaPolicy[$p]->resolution_time_hours . 'h' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button type="submit" class="w-full rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Assign & Update') }}</button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    </div>

                    {{-- Status --}}
                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Status') }}</h4>
                        <div class="mt-4">
                            <form method="POST" action="{{ route('tickets.change-status', $ticket) }}">
                                @csrf
                                <select name="status" id="sidebar_status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    @php
                                        $availableStatuses = ['open', 'assigned', 'in_progress', 'closed', 'cancelled'];
                                        if (app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::SlaManagement)) {
                                            $availableStatuses = ['open', 'assigned', 'in_progress', 'on_hold', 'closed', 'cancelled'];
                                        }
                                    @endphp
                                    @foreach($availableStatuses as $status)
                                        <option value="{{ $status }}" {{ $ticket->status === $status ? 'selected' : '' }}>
                                            {{ ucfirst(str_replace('_', ' ', $status)) }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="mt-2 w-full rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Update Status') }}</button>
                            </form>
                        </div>
                    </div>

                    {{-- Timeline --}}
                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Timeline') }}</h4>
                        <dl class="mt-4 space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('Date Created') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $ticket->created_at->format('m/d/Y, g:i:s A') }}</dd>
                            </div>
                            @if($ticket->in_progress_at)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('Started') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $ticket->in_progress_at->format('m/d/Y, g:i:s A') }}</dd>
                            </div>
                            @endif
                            @if($ticket->closed_at)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('Closed') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $ticket->closed_at->format('m/d/Y, g:i:s A') }}</dd>
                            </div>
                            @endif
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('Last Update') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $ticket->updated_at->diffForHumans() }}</dd>
                            </div>
                        </dl>
                    </div>

                    {{-- Resolution Time --}}
                    @if($ticket->closed_at)
                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Resolution Time') }}</h4>
                        <dl class="mt-4 space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('Total (Created to Closed)') }}</dt>
                                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ \App\Models\Ticket::formatHours($ticket->getEffectiveResolutionTimeHours()) }}</dd>
                            </div>
                            @if($ticket->in_progress_at)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('Work Time (Started to Closed)') }}</dt>
                                <dd class="mt-1 text-sm font-semibold text-indigo-600">{{ \App\Models\Ticket::formatHours($ticket->getWorkResolutionTimeHours()) }}</dd>
                            </div>
                            @endif
                        </dl>
                    </div>
                    @endif

                    {{-- Spam Management --}}
                    @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::SpamManagement))
                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Spam Management') }}</h4>
                        <div class="mt-4">
                            @if($ticket->is_spam)
                                <div class="rounded-md bg-red-50 p-3 mb-3">
                                    <div class="flex items-center gap-2">
                                        <svg class="h-5 w-5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                        </svg>
                                        <span class="text-sm font-semibold text-red-700">{{ __('Marked as Spam') }}</span>
                                    </div>
                                    @if($ticket->spam_reason)
                                        <p class="mt-1 text-sm text-red-600 ml-7">{{ $ticket->spam_reason }}</p>
                                    @endif
                                    @if($ticket->marked_spam_at)
                                        <p class="mt-1 text-xs text-red-500 ml-7">{{ $ticket->marked_spam_at->format('m/d/Y, g:i A') }}</p>
                                    @endif
                                </div>
                                <form method="POST" action="{{ route('tickets.unmark-spam', $ticket) }}">
                                    @csrf
                                    <button type="submit" class="w-full rounded-md bg-green-50 px-3 py-2 text-sm font-medium text-green-700 hover:bg-green-100">
                                        {{ __('Remove Spam Flag') }}
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('tickets.mark-spam', $ticket) }}">
                                    @csrf
                                    <div class="space-y-3">
                                        <div>
                                            <label for="spam_reason" class="block text-sm font-medium text-gray-500">{{ __('Reason (optional)') }}</label>
                                            <textarea name="spam_reason" id="spam_reason" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="{{ __('Why is this spam?') }}"></textarea>
                                        </div>
                                        <button type="submit" class="w-full rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-100" onclick="return confirm('{{ __('Mark this ticket as spam?') }}')">
                                            {{ __('Mark as Spam') }}
                                        </button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Activity History / Audit Logs --}}
            @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::AuditLogs) && $ticket->history->count() > 0)
            <div class="mt-6 rounded-xl bg-white p-6 shadow-sm">
                <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-4">{{ __('Activity History') }}</h4>
                <div class="flow-root">
                    <ul class="-mb-8">
                        @foreach($ticket->history as $entry)
                        <li>
                            <div class="relative pb-8">
                                @if(!$loop->last)
                                    <span class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                @endif
                                <div class="relative flex items-start space-x-3">
                                    <div class="relative">
                                        @if($entry->action === 'created')
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 ring-8 ring-white">
                                                <svg class="h-4 w-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                                </svg>
                                            </div>
                                        @elseif($entry->action === 'status_changed')
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 ring-8 ring-white">
                                                <svg class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                                </svg>
                                            </div>
                                        @elseif($entry->action === 'assigned')
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 ring-8 ring-white">
                                                <svg class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                                </svg>
                                            </div>
                                        @elseif(in_array($entry->action, ['marked_spam', 'unmarked_spam']))
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-red-100 ring-8 ring-white">
                                                <svg class="h-4 w-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                                </svg>
                                            </div>
                                        @elseif(in_array($entry->action, ['billing_updated', 'marked_billable']))
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-yellow-100 ring-8 ring-white">
                                                <svg class="h-4 w-4 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                                                </svg>
                                            </div>
                                        @else
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 ring-8 ring-white">
                                                <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                                                </svg>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm text-gray-900">
                                            @if($entry->description)
                                                {{ $entry->description }}
                                            @elseif($entry->field_name)
                                                <span class="font-medium">{{ ucfirst(str_replace('_', ' ', $entry->field_name)) }}</span>
                                                changed
                                                @if($entry->old_value)
                                                    from <span class="font-medium">{{ $entry->old_value }}</span>
                                                @endif
                                                to <span class="font-medium">{{ $entry->new_value }}</span>
                                            @else
                                                {{ ucfirst(str_replace('_', ' ', $entry->action)) }}
                                            @endif
                                        </div>
                                        <div class="mt-0.5 text-xs text-gray-500">
                                            {{ $entry->user?->name ?? __('System') }} &middot; {{ $entry->created_at->format('m/d/Y, g:i A') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
