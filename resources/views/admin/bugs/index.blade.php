@extends('layouts.admin')

@section('title', 'AI Bug Reports')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">AI Bug Reports</h1>
            <p class="text-sm text-gray-500 mt-1">Product bugs reported by users through the AI Assistant. Review one and hand it to the AI Programmer.</p>
        </div>
        <div class="flex gap-3 text-sm">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-3 py-1 text-red-700 font-medium">{{ $counts['new'] }} new</span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-amber-700 font-medium">{{ $counts['escalated'] }} with AI</span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-3 py-1 text-blue-700 font-medium">{{ $counts['pr_opened'] }} PR open</span>
        </div>
    </div>

    @if(session('success'))<div class="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>@endif

    <form method="GET" action="{{ route('admin.bugs.index') }}" class="mb-4 flex flex-wrap gap-3">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search title or description..."
               class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-64">
        <select name="status" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="">All statuses</option>
            @foreach(['new','triaged','escalated','pr_opened','merged','closed','rejected'] as $s)
                <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
            @endforeach
        </select>
        <select name="severity" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="">All severities</option>
            @foreach(['low','medium','high','critical'] as $sev)
                <option value="{{ $sev }}" {{ request('severity') === $sev ? 'selected' : '' }}>{{ ucfirst($sev) }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Filter</button>
    </form>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <th class="px-4 py-3">Ref</th>
                    <th class="px-4 py-3">Title</th>
                    <th class="px-4 py-3">Severity</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Tenant</th>
                    <th class="px-4 py-3">Reported</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm">
                @forelse($bugs as $bug)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $bug->reference() }}</td>
                        <td class="px-4 py-3 text-gray-800">{{ \Illuminate\Support\Str::limit($bug->title, 60) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
                                {{ in_array($bug->severity, ['high','critical']) ? 'bg-red-50 text-red-700' : 'bg-gray-100 text-gray-600' }}">{{ ucfirst($bug->severity) }}</span>
                        </td>
                        <td class="px-4 py-3"><span class="inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">{{ ucfirst(str_replace('_',' ',$bug->status)) }}</span></td>
                        <td class="px-4 py-3 text-gray-500">{{ $bug->tenant?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $bug->created_at->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('admin.bugs.show', $bug->id) }}" class="font-medium text-indigo-600 hover:text-indigo-500">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">No bug reports yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $bugs->links() }}</div>
@endsection
