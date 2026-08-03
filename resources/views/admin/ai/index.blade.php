@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">AI Knowledge & Vector Pipeline</h1>
            <p class="text-sm text-gray-500">Monitor automated embedding pipeline, queue status, and semantic knowledge coverage.</p>
        </div>
        <div class="flex items-center gap-2">
            @if($isConfigured)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-600/20">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span> OpenAI Connected
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 ring-1 ring-amber-600/20">
                    <span class="h-2 w-2 rounded-full bg-amber-500"></span> OpenAI Not Configured
                </span>
            @endif
        </div>
    </div>

    {{-- Metrics Grid --}}
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Generated Embeddings</dt>
            <dd class="mt-2 text-3xl font-extrabold text-gray-900">{{ number_format($metrics['generated_count'] ?? 0) }}</dd>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Queue Failures</dt>
            <dd class="mt-2 text-3xl font-extrabold text-gray-900">{{ number_format($metrics['failure_count'] ?? 0) }}</dd>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Processing Time</dt>
            <dd class="mt-2 text-3xl font-extrabold text-gray-900">{{ number_format($metrics['total_duration_seconds'] ?? 0) }}s</dd>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Cache Misses</dt>
            <dd class="mt-2 text-3xl font-extrabold text-gray-900">{{ number_format($metrics['cache_misses'] ?? 0) }}</dd>
        </div>
    </div>

    {{-- Knowledge Coverage --}}
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Semantic Knowledge Base Coverage</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-200/60">
                <div class="flex justify-between items-center mb-1">
                    <span class="text-sm font-medium text-gray-700">KB Articles</span>
                    <span class="text-xs font-semibold text-gray-500">{{ $kbEmbedded }} / {{ $kbTotal }}</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-indigo-600 h-2 rounded-full" style="width: {{ $kbTotal > 0 ? min(100, round(($kbEmbedded / $kbTotal) * 100)) : 0 }}%"></div>
                </div>
            </div>

            <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-200/60">
                <div class="flex justify-between items-center mb-1">
                    <span class="text-sm font-medium text-gray-700">Resolved Tickets</span>
                    <span class="text-xs font-semibold text-gray-500">{{ $ticketEmbedded }} / {{ $ticketTotal }}</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-emerald-600 h-2 rounded-full" style="width: {{ $ticketTotal > 0 ? min(100, round(($ticketEmbedded / $ticketTotal) * 100)) : 0 }}%"></div>
                </div>
            </div>

            <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-200/60">
                <div class="flex justify-between items-center mb-1">
                    <span class="text-sm font-medium text-gray-700">Learned Snippets</span>
                    <span class="text-xs font-semibold text-gray-500">{{ $snippetEmbedded }} / {{ $snippetTotal }}</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-violet-600 h-2 rounded-full" style="width: {{ $snippetTotal > 0 ? min(100, round(($snippetEmbedded / $snippetTotal) * 100)) : 0 }}%"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
