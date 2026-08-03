@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">AI Analytics & Token Usage</h1>
            <p class="text-sm text-gray-500">Monitor token consumption, estimated OpenAI API costs, user feedback, and response metrics.</p>
        </div>
    </div>

    {{-- Usage Summary Grid --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Conversations</dt>
            <dd class="mt-2 text-3xl font-extrabold text-gray-900">{{ number_format($totalConversations) }}</dd>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Messages</dt>
            <dd class="mt-2 text-3xl font-extrabold text-gray-900">{{ number_format($totalMessages) }}</dd>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Estimated API Cost</dt>
            <dd class="mt-2 text-3xl font-extrabold text-emerald-600">${{ number_format($estimatedCost, 2) }}</dd>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Feedback Ratio</dt>
            <dd class="mt-2 text-3xl font-extrabold text-indigo-600">
                @if($thumbsUpCount + $thumbsDownCount > 0)
                    {{ round(($thumbsUpCount / ($thumbsUpCount + $thumbsDownCount)) * 100) }}% 👍
                @else
                    100% 👍
                @endif
            </dd>
        </div>
    </div>

    {{-- Feedback Breakdown Card --}}
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-base font-semibold text-gray-900 mb-4">User Satisfaction Ratings</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="flex items-center justify-between p-4 rounded-lg bg-emerald-50 border border-emerald-200">
                <div>
                    <span class="block text-sm font-medium text-emerald-800">Helpful Responses (Thumbs Up)</span>
                    <span class="text-2xl font-bold text-emerald-900">{{ number_format($thumbsUpCount) }}</span>
                </div>
                <span class="text-3xl">👍</span>
            </div>
            <div class="flex items-center justify-between p-4 rounded-lg bg-rose-50 border border-rose-200">
                <div>
                    <span class="block text-sm font-medium text-rose-800">Needs Improvement (Thumbs Down)</span>
                    <span class="text-2xl font-bold text-rose-900">{{ number_format($thumbsDownCount) }}</span>
                </div>
                <span class="text-3xl">👎</span>
            </div>
        </div>
    </div>
</div>
@endsection
