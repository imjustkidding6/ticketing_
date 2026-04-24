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
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Resource') }}</label>
                        <select name="subject_type" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('All Resources') }}</option>
                            @foreach($subjectTypes as $type)
                                <option value="{{ $type }}" {{ request('subject_type') === $type ? 'selected' : '' }}>{{ class_basename($type) }}</option>
                            @endforeach
                        </select>
                    </div>
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
                    @if(request()->hasAny(['subject_type', 'action', 'user_id', 'from', 'to']))
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
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Resource') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Subject') }}</th>
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
                                    str_contains($log->action, 'created') => 'bg-green-100 text-green-800',
                                    str_contains($log->action, 'updated') => 'bg-blue-100 text-blue-800',
                                    str_contains($log->action, 'deleted') => 'bg-rose-100 text-rose-800',
                                    str_contains($log->action, 'restored') || str_contains($log->action, 'reactivated') => 'bg-emerald-100 text-emerald-800',
                                    str_contains($log->action, 'deactivated') => 'bg-orange-100 text-orange-800',
                                    str_contains($log->action, 'assigned') => 'bg-indigo-100 text-indigo-800',
                                    str_contains($log->action, 'status') => 'bg-sky-100 text-sky-800',
                                    str_contains($log->action, 'spam') => 'bg-red-100 text-red-800',
                                    str_contains($log->action, 'billing') || str_contains($log->action, 'billable') => 'bg-yellow-100 text-yellow-800',
                                    str_contains($log->action, 'merged') => 'bg-fuchsia-100 text-fuchsia-800',
                                    str_contains($log->action, 'reopened') => 'bg-amber-100 text-amber-800',
                                    str_contains($log->action, 'escalated') => 'bg-purple-100 text-purple-800',
                                    default => 'bg-gray-100 text-gray-800',
                                };
                                $subjectShort = class_basename($log->subject_type);
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">{{ $subjectShort }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">
                                    @if($log->subject_type === \App\Models\Ticket::class && $log->subject_id)
                                        <a href="{{ route('tickets.show', $log->subject_id) }}" class="font-mono text-indigo-600 hover:text-indigo-800">{{ $log->subject_label ?? ('#'.$log->subject_id) }}</a>
                                    @else
                                        <span class="text-gray-800">{{ $log->subject_label ?? ('#'.$log->subject_id) }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $actionBadge }}">
                                        {{ ucfirst(str_replace('_', ' ', $log->action)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 max-w-md">
                                    @if($log->description)
                                        <div class="truncate">{{ Str::limit($log->description, 80) }}</div>
                                    @endif
                                    @if(!empty($log->changes))
                                        <details class="mt-1">
                                            <summary class="cursor-pointer text-xs text-gray-500 hover:text-gray-700">{{ __('Changes') }} ({{ count($log->changes) }})</summary>
                                            <ul class="mt-1 text-xs text-gray-500 space-y-0.5">
                                                @foreach($log->changes as $field => $diff)
                                                    <li>
                                                        <span class="font-medium">{{ ucfirst(str_replace('_', ' ', $field)) }}:</span>
                                                        <span class="text-rose-600">{{ is_scalar($diff['old'] ?? null) ? Str::limit((string)($diff['old'] ?? '—'), 40) : json_encode($diff['old'] ?? null) }}</span>
                                                        →
                                                        <span class="text-emerald-600">{{ is_scalar($diff['new'] ?? null) ? Str::limit((string)($diff['new'] ?? '—'), 40) : json_encode($diff['new'] ?? null) }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @endif
                                    @if(!$log->description && empty($log->changes))
                                        -
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ $log->user?->name ?? __('System') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-400">@localdt($log->created_at, 'm/d/Y g:i A')</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-sm text-gray-500">{{ __('No activity logs found.') }}</td>
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
