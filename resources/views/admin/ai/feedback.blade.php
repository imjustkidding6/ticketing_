@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">AI User Feedback & Ratings</h1>
            <p class="text-sm text-gray-500">Review thumbs up/down user feedback and optional comments on AI responses.</p>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white overflow-hidden shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                <tr>
                    <th class="px-4 py-3 text-left">Rating</th>
                    <th class="px-4 py-3 text-left">Tenant</th>
                    <th class="px-4 py-3 text-left">User</th>
                    <th class="px-4 py-3 text-left">User Question</th>
                    <th class="px-4 py-3 text-left">Feedback Comment</th>
                    <th class="px-4 py-3 text-left">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($feedbacks as $fb)
                    <tr>
                        <td class="px-4 py-3">
                            @if($fb->rating === 'thumbs_up')
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">👍 Helpful</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">👎 Needs Work</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-700">{{ $fb->tenant?->name ?? 'System' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $fb->user?->name ?? 'Guest' }}</td>
                        <td class="px-4 py-3 text-gray-800 max-w-xs truncate">{{ $fb->question ?? 'N/A' }}</td>
                        <td class="px-4 py-3 text-gray-600 max-w-xs truncate">{{ $fb->comment ?? 'No comment provided' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $fb->created_at->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">No user feedback logged yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4 border-t border-gray-200">
            {{ $feedbacks->links() }}
        </div>
    </div>
</div>
@endsection
