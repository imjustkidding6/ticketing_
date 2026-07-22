@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">AI Conversation Manager</h1>
            <p class="text-sm text-gray-500">Monitor, replay, filter, export, and manage system-wide AI chat conversations.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.ai.conversations.export', request()->query()) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-gray-800">
                Export CSV
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <form method="GET" class="flex flex-wrap gap-4 items-center">
            <div>
                <select name="channel" class="rounded-md border-gray-300 text-sm focus:ring-indigo-500">
                    <option value="">All Channels</option>
                    <option value="portal" {{ request('channel') === 'portal' ? 'selected' : '' }}>Portal</option>
                    <option value="agent" {{ request('channel') === 'agent' ? 'selected' : '' }}>Agent</option>
                </select>
            </div>
            <div>
                <select name="tenant_id" class="rounded-md border-gray-300 text-sm focus:ring-indigo-500">
                    <option value="">All Tenants</option>
                    @foreach($tenants as $t)
                        <option value="{{ $t->id }}" {{ request('tenant_id') == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Filter</button>
        </form>
    </div>

    {{-- Conversations Table --}}
    <div class="rounded-xl border border-gray-200 bg-white overflow-hidden shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                <tr>
                    <th class="px-4 py-3 text-left">ID</th>
                    <th class="px-4 py-3 text-left">Tenant</th>
                    <th class="px-4 py-3 text-left">Channel</th>
                    <th class="px-4 py-3 text-left">User / Session</th>
                    <th class="px-4 py-3 text-left">Messages</th>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($conversations as $c)
                    <tr>
                        <td class="px-4 py-3 font-semibold text-gray-900">#{{ $c->id }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $c->tenant?->name ?? 'System' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium {{ $c->channel === 'portal' ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700' }}">{{ strtoupper($c->channel) }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $c->user?->name ?? 'Guest (' . substr($c->session_token ?? '', 0, 8) . '...)' }}</td>
                        <td class="px-4 py-3 font-semibold text-gray-800">{{ $c->messages->count() }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $c->updated_at->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 text-right">
                            <form action="{{ route('admin.ai.conversations.delete', $c->id) }}" method="POST" onsubmit="return confirm('Delete conversation #{{ $c->id }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-rose-600 hover:text-rose-800 font-medium">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-500">No conversations found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4 border-t border-gray-200">
            {{ $conversations->links() }}
        </div>
    </div>
</div>
@endsection
