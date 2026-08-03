@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">AI Prompt Management & Versioning</h1>
            <p class="text-sm text-gray-500">Manage, preview, and restore global, tenant, and department system prompts.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-emerald-50 p-4 text-sm font-medium text-emerald-800 ring-1 ring-emerald-200">
            {{ session('success') }}
        </div>
    @endif

    {{-- Create Prompt Form --}}
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Create New System Prompt Version</h2>
        <form action="{{ route('admin.ai.prompts.store') }}" method="POST" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Prompt Type</label>
                    <select name="type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="global">Global System Prompt</option>
                        <option value="portal">Customer Portal Prompt</option>
                        <option value="agent">Agent Copilot Prompt</option>
                        <option value="department">Department Prompt</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Name / Identifier</label>
                    <input type="text" name="name" required placeholder="e.g. Standard Support Copilot" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tenant ID (Optional)</label>
                    <input type="number" name="tenant_id" placeholder="Leave empty for global" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Prompt System Instructions</label>
                <textarea name="prompt" rows="5" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="You are a helpful support assistant..."></textarea>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">Save Prompt Version</button>
            </div>
        </form>
    </div>

    {{-- Prompts History Table --}}
    <div class="rounded-xl border border-gray-200 bg-white overflow-hidden shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                <tr>
                    <th class="px-4 py-3 text-left">Version</th>
                    <th class="px-4 py-3 text-left">Name / Type</th>
                    <th class="px-4 py-3 text-left">Tenant</th>
                    <th class="px-4 py-3 text-left">Created By</th>
                    <th class="px-4 py-3 text-left">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($prompts as $p)
                    <tr>
                        <td class="px-4 py-3 font-semibold text-gray-900">v{{ $p->version }}</td>
                        <td class="px-4 py-3">
                            <span class="font-medium text-gray-900">{{ $p->name }}</span>
                            <span class="ml-2 inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ strtoupper($p->type) }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $p->tenant?->name ?? 'Global' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $p->author?->name ?? 'System' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $p->created_at->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">No prompt templates created yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4 border-t border-gray-200">
            {{ $prompts->links() }}
        </div>
    </div>
</div>
@endsection
