@extends('layouts.admin')

@section('title', 'Tenant Feedback')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Tenant Feedback</h1>
            <p class="text-sm text-gray-500 mt-1">Feedback submitted by tenant users about the system.</p>
        </div>
        <div class="flex gap-3 text-sm">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-3 py-1 text-red-700 font-medium">
                {{ $counts['new'] }} new
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-3 py-1 text-blue-700 font-medium">
                {{ $counts['read'] }} read
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-3 py-1 text-green-700 font-medium">
                {{ $counts['resolved'] }} resolved
            </span>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.feedback.index') }}" class="mb-4 flex flex-wrap gap-3">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Search subject or message..."
               class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-64">
        <select name="type" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
            <option value="">All types</option>
            <option value="bug" {{ request('type') === 'bug' ? 'selected' : '' }}>Bug</option>
            <option value="suggestion" {{ request('type') === 'suggestion' ? 'selected' : '' }}>Suggestion</option>
            <option value="compliment" {{ request('type') === 'compliment' ? 'selected' : '' }}>Compliment</option>
            <option value="other" {{ request('type') === 'other' ? 'selected' : '' }}>Other</option>
        </select>
        <select name="status" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
            <option value="">All statuses</option>
            <option value="new" {{ request('status') === 'new' ? 'selected' : '' }}>New</option>
            <option value="read" {{ request('status') === 'read' ? 'selected' : '' }}>Read</option>
            <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
        </select>
        <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Filter</button>
        @if(request('search') || request('type') || request('status'))
            <a href="{{ route('admin.feedback.index') }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">Clear</a>
        @endif
    </form>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant / User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($feedbacks as $fb)
                    <tr class="{{ $fb->status === 'new' ? 'bg-indigo-50/40' : '' }}">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ $fb->tenant?->name ?? '—' }}</div>
                            <div class="text-xs text-gray-500">{{ $fb->user?->name ?? 'Unknown user' }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $typeColor = match($fb->type) {
                                    'bug'        => 'bg-red-100 text-red-700',
                                    'suggestion' => 'bg-blue-100 text-blue-700',
                                    'compliment' => 'bg-emerald-100 text-emerald-700',
                                    default      => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $typeColor }}">
                                {{ ucfirst($fb->type) }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-900">{{ $fb->subject ?: '—' }}</div>
                            <div class="text-xs text-gray-500 mt-0.5 line-clamp-1 max-w-xs">{{ $fb->body }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $statusColor = match($fb->status) {
                                    'new'      => 'bg-red-100 text-red-700',
                                    'read'     => 'bg-yellow-100 text-yellow-700',
                                    'resolved' => 'bg-green-100 text-green-700',
                                    default    => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                                {{ ucfirst($fb->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ \App\Support\TenantTime::format($fb->created_at, 'M d, Y g:i A') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                            <a href="{{ route('admin.feedback.show', $fb) }}"
                               class="text-indigo-600 hover:text-indigo-800 font-medium">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-400">No feedback found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $feedbacks->links() }}</div>
@endsection
