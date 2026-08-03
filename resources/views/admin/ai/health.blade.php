@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">AI Infrastructure Health Monitor</h1>
            <p class="text-sm text-gray-500">Live operational status of OpenAI API, circuit breakers, cache hit ratios, and latency.</p>
        </div>
        <div class="flex items-center gap-2">
            @if($status === 'healthy')
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3.5 py-1 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-600/20">
                    🟢 Healthy
                </span>
            @elseif($status === 'warning')
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3.5 py-1 text-sm font-semibold text-amber-700 ring-1 ring-amber-600/20">
                    🟡 Warning
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-3.5 py-1 text-sm font-semibold text-rose-700 ring-1 ring-rose-600/20">
                    🔴 Critical Outage
                </span>
            @endif
        </div>
    </div>

    {{-- Status Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-medium text-gray-500 uppercase">OpenAI API Service</h3>
            <p class="mt-2 text-xl font-bold text-gray-900">{{ $openAiAvailable ? 'Available' : 'Unavailable' }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-medium text-gray-500 uppercase">Circuit Breaker State</h3>
            <p class="mt-2 text-xl font-bold {{ $circuitStatus['state'] === 'CLOSED' ? 'text-emerald-600' : 'text-rose-600' }}">{{ $circuitStatus['state'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-medium text-gray-500 uppercase">Cache Hit Ratio</h3>
            <p class="mt-2 text-xl font-bold text-indigo-600">{{ $metrics['cache_hit_ratio'] }}%</p>
        </div>
    </div>

    {{-- Latency & Operational Metrics --}}
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Latency & Performance Metrics</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-center">
            <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                <span class="block text-xs font-medium text-gray-500">Average Latency</span>
                <span class="text-2xl font-extrabold text-gray-900">{{ $metrics['avg_latency_ms'] }} ms</span>
            </div>
            <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                <span class="block text-xs font-medium text-gray-500">P95 Latency</span>
                <span class="text-2xl font-extrabold text-gray-900">{{ $metrics['p95_latency_ms'] }} ms</span>
            </div>
            <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                <span class="block text-xs font-medium text-gray-500">P99 Latency</span>
                <span class="text-2xl font-extrabold text-gray-900">{{ $metrics['p99_latency_ms'] }} ms</span>
            </div>
            <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                <span class="block text-xs font-medium text-gray-500">Error Rate</span>
                <span class="text-2xl font-extrabold text-gray-900">{{ $metrics['error_rate_percent'] }}%</span>
            </div>
        </div>
    </div>
</div>
@endsection
