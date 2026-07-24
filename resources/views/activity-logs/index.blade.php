<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">{{ __('Activity Logs') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
            {{-- Filters --}}
            <div class="mb-6 rounded-xl bg-white dark:bg-gray-800 p-4 shadow-sm border border-gray-100 dark:border-gray-700">
                <form method="GET" action="{{ route('activity-logs.index') }}" class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('Resource') }}</label>
                        <select name="subject_type" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('All Resources') }}</option>
                            @foreach($subjectTypes as $type)
                                <option value="{{ $type }}" {{ request('subject_type') === $type ? 'selected' : '' }}>{{ class_basename($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('Action') }}</label>
                        <select name="action" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('All Actions') }}</option>
                            @foreach($actionTypes as $action)
                                <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $action)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('User') }}</label>
                        <select name="user_id" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('All Users') }}</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('From') }}</label>
                        <input type="date" name="from" value="{{ request('from') }}" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('To') }}</label>
                        <input type="date" name="to" value="{{ request('to') }}" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <button type="submit" class="rounded-md bg-gray-100 dark:bg-gray-700 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">{{ __('Filter') }}</button>
                    @if(request()->hasAny(['subject_type', 'action', 'user_id', 'from', 'to']))
                        <a href="{{ route('activity-logs.index') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors">{{ __('Clear') }}</a>
                    @endif
                    <span class="ml-auto text-sm text-gray-500 dark:text-gray-400">{{ $logs->total() }} {{ __('entries') }}</span>
                </form>
            </div>

            {{-- Logs Table --}}
            <div class="overflow-hidden rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Resource') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Subject') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Action') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Details') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('User') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                        @forelse($logs as $log)
                            @php
                                $actionBadge = match(true) {
                                    str_contains($log->action, 'created') => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
                                    str_contains($log->action, 'updated') => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
                                    str_contains($log->action, 'deleted') => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
                                    str_contains($log->action, 'restored') || str_contains($log->action, 'reactivated') => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
                                    str_contains($log->action, 'deactivated') => 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
                                    str_contains($log->action, 'assigned') => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
                                    str_contains($log->action, 'status') => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
                                    str_contains($log->action, 'spam') => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
                                    str_contains($log->action, 'billing') || str_contains($log->action, 'billable') => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
                                    str_contains($log->action, 'merged') => 'bg-fuchsia-100 text-fuchsia-800 dark:bg-fuchsia-900/40 dark:text-fuchsia-300',
                                    str_contains($log->action, 'reopened') => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
                                    str_contains($log->action, 'escalated') => 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
                                    default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                };
                                $subjectShort = class_basename($log->subject_type);
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs font-medium text-gray-700 dark:text-gray-300">{{ $subjectShort }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    @if($log->subject_type === \App\Models\Ticket::class && $log->subject_id)
                                        <a href="{{ route('tickets.show', $log->subject_id) }}" class="font-mono text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 transition-colors">{{ $log->subject_label ?? ('#'.$log->subject_id) }}</a>
                                    @else
                                        <span class="text-gray-800 dark:text-gray-200">{{ $log->subject_label ?? ('#'.$log->subject_id) }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $actionBadge }}">
                                        {{ ucfirst(str_replace('_', ' ', $log->action)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 max-w-md">
                                    @if($log->description)
                                        <div class="truncate">{{ Str::limit($log->description, 80) }}</div>
                                    @endif
                                    @if(!empty($log->changes))
                                        <details class="mt-1">
                                            <summary class="cursor-pointer text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors">{{ __('Changes') }} ({{ count($log->changes) }})</summary>
                                            <ul class="mt-1 text-xs text-gray-500 dark:text-gray-400 space-y-0.5">
                                                @foreach($log->changes as $field => $diff)
                                                    <li>
                                                        <span class="font-medium text-gray-600 dark:text-gray-300">{{ ucfirst(str_replace('_', ' ', $field)) }}:</span>
                                                        <span class="text-rose-600 dark:text-rose-400">{{ is_scalar($diff['old'] ?? null) ? Str::limit((string)($diff['old'] ?? '—'), 40) : json_encode($diff['old'] ?? null) }}</span>
                                                        →
                                                        <span class="text-emerald-600 dark:text-emerald-400">{{ is_scalar($diff['new'] ?? null) ? Str::limit((string)($diff['new'] ?? '—'), 40) : json_encode($diff['new'] ?? null) }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @endif
                                    @if(!$log->description && empty($log->changes))
                                        -
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $log->user?->name ?? __('System') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-400 dark:text-gray-500">@localdt($log->created_at, 'm/d/Y g:i A')</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No activity logs found.') }}</td>
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