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
                                        @if($ticket->client->email)
                                            <p class="text-xs text-gray-500 mt-0.5">
                                                <a href="mailto:{{ $ticket->client->email }}" class="hover:text-indigo-600">{{ $ticket->client->email }}</a>
                                            </p>
                                        @endif
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

                    {{-- Comments & Updates --}}
                    @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::ClientComments))
                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-4">{{ __('Comments & Updates') }}</h4>

                        {{-- Comment List --}}
                        @if($ticket->comments->isNotEmpty())
                            <div class="space-y-4">
                                @foreach($ticket->comments as $comment)
                                    <div class="rounded-lg border {{ $comment->type === 'internal' ? 'border-yellow-200 bg-yellow-50' : 'border-gray-200 bg-gray-50' }} p-4">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="flex items-center gap-2">
                                                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 shrink-0">
                                                    {{ strtoupper(substr($comment->user?->name ?? '?', 0, 1)) }}
                                                </div>
                                                <div>
                                                    <span class="text-sm font-medium text-gray-900">{{ $comment->user?->name ?? __('Unknown') }}</span>
                                                    <span class="ml-1 text-xs text-gray-400">{{ $comment->created_at->format('m/d/Y, g:i A') }}</span>
                                                    @if($comment->edited_at)
                                                        <span class="ml-1 text-xs text-gray-400 italic">({{ __('edited') }})</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0">
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $comment->type === 'internal' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' }}">
                                                    {{ $comment->type === 'internal' ? __('Internal') : __('Public') }}
                                                </span>
                                                @if($comment->user_id === Auth::id())
                                                    <form method="POST" action="{{ route('tickets.comments.destroy', [$ticket, $comment]) }}" class="inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-gray-400 hover:text-red-500" onclick="return confirm('{{ __('Delete this comment?') }}')" title="{{ __('Delete') }}">
                                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="mt-2 text-sm text-gray-700 prose prose-sm max-w-none">
                                            {!! nl2br(e($comment->content)) !!}
                                        </div>
                                        @if($comment->attachments)
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                @foreach($comment->attachments as $idx => $attachment)
                                                    <a href="{{ route('tickets.comments.attachment', [$ticket, $comment, $idx]) }}" class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-xs text-gray-600 hover:bg-gray-100 hover:text-gray-800">
                                                        <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                                                        </svg>
                                                        {{ $attachment['name'] }}
                                                        <span class="text-gray-400">({{ number_format(($attachment['size'] ?? 0) / 1024, 0) }}KB)</span>
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-400 text-center py-4">{{ __('No comments yet.') }}</p>
                        @endif

                        {{-- Add Comment Form --}}
                        @if(!in_array($ticket->status, ['closed', 'cancelled']))
                            <form method="POST" action="{{ route('tickets.comments.store', $ticket) }}" enctype="multipart/form-data" class="mt-4 border-t border-gray-200 pt-4">
                                @csrf
                                {{-- Canned Response Insertion --}}
                                @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::CannedResponses) && $cannedResponses->isNotEmpty())
                                    <div class="mb-3">
                                        <select id="canned-response-select" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" onchange="if(this.value){document.getElementById('comment_content').value+=this.options[this.selectedIndex].dataset.content;this.value='';}">
                                            <option value="">{{ __('Insert canned response...') }}</option>
                                            @php $grouped = $cannedResponses->groupBy('category'); @endphp
                                            @foreach($grouped as $category => $responses)
                                                <optgroup label="{{ $category ?: __('Uncategorized') }}">
                                                    @foreach($responses as $cr)
                                                        <option value="{{ $cr->id }}" data-content="{{ e($cr->content) }}">{{ $cr->name }}</option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div class="space-y-3">
                                    <textarea name="content" id="comment_content" rows="3" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="{{ __('Write a comment...') }}"></textarea>
                                    <div>
                                        <input type="file" name="attachments[]" multiple class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-gray-700 hover:file:bg-gray-200">
                                        <p class="mt-1 text-xs text-gray-400">{{ __('Max 5 files, 10MB each. Images, PDF, docs, zip.') }}</p>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <select name="type" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="public">{{ __('Public') }}</option>
                                            <option value="internal">{{ __('Internal Note') }}</option>
                                        </select>
                                        <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                                            {{ __('Add Comment') }}
                                        </button>
                                    </div>
                                </div>
                            </form>
                        @endif
                    </div>
                    @endif
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

                            {{-- Reopen Ticket --}}
                            @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::TicketReopening) && $ticket->status === 'closed')
                                <form method="POST" action="{{ route('tickets.reopen', $ticket) }}" class="mt-3 border-t border-gray-100 pt-3">
                                    @csrf
                                    <button type="submit" class="w-full rounded-md bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700 hover:bg-amber-100" onclick="return confirm('{{ __('Reopen this ticket?') }}')">
                                        {{ __('Reopen Ticket') }}
                                    </button>
                                    @if($ticket->reopened_count > 0)
                                        <p class="mt-1 text-center text-xs text-gray-400">{{ __('Reopened') }} {{ $ticket->reopened_count }} {{ __('time(s)') }}</p>
                                    @endif
                                </form>
                            @endif
                        </div>
                    </div>

                    {{-- Escalation --}}
                    @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::AgentEscalation))
                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Escalation') }}</h4>
                        <div class="mt-4">
                            {{-- Current Tier --}}
                            <div class="mb-3">
                                <span class="text-sm text-gray-500">{{ __('Current Tier:') }}</span>
                                @php
                                    $tierLabel = match($ticket->current_tier) {
                                        'tier_2' => 'Tier 2',
                                        'tier_3' => 'Tier 3',
                                        default => 'Tier 1',
                                    };
                                    $tierBadge = match($ticket->current_tier) {
                                        'tier_2' => 'bg-blue-100 text-blue-800',
                                        'tier_3' => 'bg-purple-100 text-purple-800',
                                        default => 'bg-green-100 text-green-800',
                                    };
                                @endphp
                                <span class="ml-1 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $tierBadge }}">{{ $tierLabel }}</span>
                                @if($ticket->escalation_count > 0)
                                    <span class="ml-1 text-xs text-gray-400">({{ $ticket->escalation_count }}x)</span>
                                @endif
                            </div>

                            {{-- Escalate Form --}}
                            @if(!in_array($ticket->status, ['closed', 'cancelled']))
                            @php
                                $tierOrder = ['tier_1' => 1, 'tier_2' => 2, 'tier_3' => 3];
                                $currentTierLevel = $tierOrder[$ticket->current_tier ?? 'tier_1'] ?? 1;
                            @endphp

                            @if($currentTierLevel < 3)
                            <form method="POST" action="{{ route('tickets.escalate', $ticket) }}" class="space-y-3"
                                  x-data="escalationForm()"
                            >
                                @csrf
                                <div>
                                    <label for="escalate_to_tier" class="block text-sm font-medium text-gray-500">{{ __('Escalate To') }}</label>
                                    <select name="to_tier" id="escalate_to_tier" required x-model="selectedTier" @change="filterAgents()" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        @foreach(['tier_1' => 'Tier 1', 'tier_2' => 'Tier 2', 'tier_3' => 'Tier 3'] as $value => $label)
                                            @if(($tierOrder[$value] ?? 0) > $currentTierLevel)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="escalate_assigned_to" class="block text-sm font-medium text-gray-500">{{ __('Reassign To (optional)') }}</label>
                                    <select name="assigned_to" id="escalate_assigned_to" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="">{{ __('Keep current') }}</option>
                                    </select>
                                    <p class="mt-1 text-xs text-gray-400" x-show="filteredAgents.length === 0 && selectedTier">{{ __('No agents available at this tier level.') }}</p>
                                </div>
                                <div>
                                    <label for="escalate_reason" class="block text-sm font-medium text-gray-500">{{ __('Reason (optional)') }}</label>
                                    <textarea name="reason" id="escalate_reason" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="{{ __('Why is this being escalated?') }}"></textarea>
                                </div>
                                <button type="submit" class="w-full rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Escalate') }}</button>
                            </form>
                            <script>
                                function escalationForm() {
                                    var tierOrder = { tier_1: 1, tier_2: 2, tier_3: 3 };
                                    var tierLabels = { tier_1: 'Tier 1', tier_2: 'Tier 2', tier_3: 'Tier 3' };
                                    @php
                                        $currentTenant = \App\Models\Tenant::find(session('current_tenant_id'));
                                        $ownerIds = $currentTenant ? $currentTenant->users()->wherePivot('role', 'owner')->pluck('users.id')->toArray() : [];
                                        $escalationAgents = $agents->filter(function ($a) use ($ownerIds) {
                                            return $a->support_tier !== null || in_array($a->id, $ownerIds);
                                        })->map(function ($a) {
                                            return ['id' => $a->id, 'name' => $a->name, 'tier' => $a->support_tier];
                                        })->values();
                                    @endphp
                                    var ownerIds = @json($ownerIds);
                                    var allAgents = @json($escalationAgents);
                                    return {
                                        selectedTier: '',
                                        filteredAgents: [],
                                        filterAgents() {
                                            var targetLevel = tierOrder[this.selectedTier] || 0;
                                            this.filteredAgents = allAgents
                                                .filter(function(a) {
                                                    if (ownerIds.indexOf(a.id) !== -1) return true;
                                                    var agentLevel = tierOrder[a.tier] || 0;
                                                    return agentLevel >= targetLevel;
                                                })
                                                .map(function(a) {
                                                    var label = tierLabels[a.tier] || 'Unset';
                                                    if (ownerIds.indexOf(a.id) !== -1) label = 'Owner';
                                                    return { id: a.id, name: a.name, tierLabel: label };
                                                });
                                            // Rebuild select options
                                            var sel = document.getElementById('escalate_assigned_to');
                                            sel.innerHTML = '<option value="">{{ __("Keep current") }}</option>';
                                            this.filteredAgents.forEach(function(agent) {
                                                var opt = document.createElement('option');
                                                opt.value = agent.id;
                                                opt.textContent = agent.name + ' (' + agent.tierLabel + ')';
                                                sel.appendChild(opt);
                                            });
                                        }
                                    };
                                }
                            </script>
                            @else
                            <p class="text-xs text-gray-400 italic">{{ __('Already at highest tier.') }}</p>
                            @endif
                            @endif

                            {{-- Escalation History --}}
                            @if($ticket->escalations->isNotEmpty())
                            <div class="mt-4 border-t border-gray-100 pt-3">
                                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-2">{{ __('History') }}</p>
                                <div class="space-y-2">
                                    @foreach($ticket->escalations as $esc)
                                        <div class="text-xs text-gray-500">
                                            <span class="font-medium text-gray-700">{{ $esc->from_tier ?? 'Tier 1' }}</span>
                                            <svg class="inline h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                                            <span class="font-medium text-gray-700">{{ $esc->to_tier }}</span>
                                            <span class="text-gray-400">by {{ $esc->escalatedByUser?->name ?? __('System') }}</span>
                                            <span class="text-gray-400">&middot; {{ $esc->created_at->format('m/d/Y g:i A') }}</span>
                                            @if($esc->reason)
                                                <p class="mt-0.5 text-gray-400 italic">{{ $esc->reason }}</p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif

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

                    {{-- Ticket Merging --}}
                    @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::TicketMerging))
                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Merge Ticket') }}</h4>
                        <div class="mt-4">
                            @if($ticket->is_merged)
                                <div class="rounded-md bg-gray-50 p-3">
                                    <p class="text-sm text-gray-600">
                                        {{ __('This ticket was merged into') }}
                                        <a href="{{ route('tickets.show', $ticket->merged_into_ticket_id) }}" class="font-medium text-indigo-600 hover:text-indigo-800">
                                            {{ $ticket->mergedIntoTicket?->ticket_number ?? '#'.$ticket->merged_into_ticket_id }}
                                        </a>
                                    </p>
                                    @if($ticket->merged_at)
                                        <p class="mt-1 text-xs text-gray-400">{{ $ticket->merged_at->format('m/d/Y, g:i A') }}</p>
                                    @endif
                                </div>
                            @elseif(!in_array($ticket->status, ['closed', 'cancelled']))
                                <form method="POST" action="{{ route('tickets.merge', $ticket) }}" class="space-y-3">
                                    @csrf
                                    <div>
                                        <label for="merge_target" class="block text-sm font-medium text-gray-500">{{ __('Merge into') }}</label>
                                        <select name="target_ticket_id" id="merge_target" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                            <option value="">{{ __('Select target ticket') }}</option>
                                            @foreach($mergeableTickets as $mt)
                                                <option value="{{ $mt->id }}">{{ $mt->ticket_number }} — {{ Str::limit($mt->subject, 40) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button type="submit" class="w-full rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-100" onclick="return confirm('{{ __('Merge this ticket? This action will close this ticket and move its data to the target.') }}')">
                                        {{ __('Merge into Selected') }}
                                    </button>
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
                                        @elseif(in_array($entry->action, ['comment_added', 'comment_edited', 'comment_deleted']))
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-cyan-100 ring-8 ring-white">
                                                <svg class="h-4 w-4 text-cyan-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                                                </svg>
                                            </div>
                                        @elseif(in_array($entry->action, ['task_added', 'task_updated', 'task_status_changed', 'task_deleted']))
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-teal-100 ring-8 ring-white">
                                                <svg class="h-4 w-4 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </div>
                                        @elseif($entry->action === 'reopened')
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-100 ring-8 ring-white">
                                                <svg class="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" />
                                                </svg>
                                            </div>
                                        @elseif(in_array($entry->action, ['escalated']))
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-purple-100 ring-8 ring-white">
                                                <svg class="h-4 w-4 text-purple-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h5.25m5.25-.75L17.25 9m0 0L21 12.75M17.25 9v12" />
                                                </svg>
                                            </div>
                                        @elseif(in_array($entry->action, ['deleted', 'restored']))
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-rose-100 ring-8 ring-white">
                                                <svg class="h-4 w-4 text-rose-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                                </svg>
                                            </div>
                                        @elseif(in_array($entry->action, ['merged', 'unmerged']))
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-fuchsia-100 ring-8 ring-white">
                                                <svg class="h-4 w-4 text-fuchsia-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
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
