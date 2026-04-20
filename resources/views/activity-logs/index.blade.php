<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Activity Logs') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
            {{-- Filters --}}
            <div class="mb-6 rounded-xl bg-white p-4 shadow-sm">
                <form method="GET" action="{{ route('activity-logs.index') }}" class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Action') }}</label>
                        <select name="action" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('All Actions') }}</option>
                            @foreach($actionTypes as $action)
                                <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $action)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('User') }}</label>
                        <select name="user_id" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('All Users') }}</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('From') }}</label>
                        <input type="date" name="from" value="{{ request('from') }}" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('To') }}</label>
                        <input type="date" name="to" value="{{ request('to') }}" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <button type="submit" class="rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">{{ __('Filter') }}</button>
                    @if(request()->hasAny(['action', 'user_id', 'from', 'to']))
                        <a href="{{ route('activity-logs.index') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Clear') }}</a>
                    @endif
                    <span class="ml-auto text-sm text-gray-500">{{ $logs->total() }} {{ __('entries') }}</span>
                </form>
            </div>

            {{-- Logs Table --}}
            <div class="overflow-hidden rounded-xl bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Ticket') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Action') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Details') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('User') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse($logs as $log)
                            @php
                                $actionBadge = match(true) {
                                    $log->action === 'created' => 'bg-green-100 text-green-800',
                                    $log->action === 'status_changed' => 'bg-blue-100 text-blue-800',
                                    $log->action === 'assigned' => 'bg-indigo-100 text-indigo-800',
                                    in_array($log->action, ['comment_added', 'comment_edited', 'comment_deleted']) => 'bg-cyan-100 text-cyan-800',
                                    in_array($log->action, ['task_added', 'task_updated', 'task_status_changed', 'task_deleted']) => 'bg-teal-100 text-teal-800',
                                    $log->action === 'escalated' => 'bg-purple-100 text-purple-800',
                                    $log->action === 'reopened' => 'bg-amber-100 text-amber-800',
                                    in_array($log->action, ['marked_spam', 'unmarked_spam']) => 'bg-red-100 text-red-800',
                                    in_array($log->action, ['billing_updated', 'marked_billable']) => 'bg-yellow-100 text-yellow-800',
                                    in_array($log->action, ['deleted', 'restored']) => 'bg-rose-100 text-rose-800',
                                    in_array($log->action, ['merged', 'unmerged']) => 'bg-fuchsia-100 text-fuchsia-800',
                                    default => 'bg-gray-100 text-gray-800',
                                };
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-4 py-3">
                                    @if($log->ticket)
                                        <a href="{{ route('tickets.show', $log->ticket) }}" class="text-sm font-mono text-indigo-600 hover:text-indigo-800">{{ $log->ticket->ticket_number }}</a>
                                    @else
                                        <span class="text-sm text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $actionBadge }}">
                                        {{ ucfirst(str_replace('_', ' ', $log->action)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 max-w-md truncate">
                                    @if($log->description)
                                        {{ Str::limit($log->description, 80) }}
                                    @elseif($log->field_name)
                                        {{ ucfirst(str_replace('_', ' ', $log->field_name)) }}: {{ $log->old_value }} → {{ $log->new_value }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ $log->user?->name ?? __('System') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-400">{{ $log->created_at->format('m/d/Y g:i A') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-12 text-center text-sm text-gray-500">{{ __('No activity logs found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $logs->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
