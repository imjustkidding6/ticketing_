@extends('layouts.admin')

@section('title', $bug->reference())

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.bugs.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; Back to bug reports</a>
    </div>

    @if(session('success'))<div class="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>@endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Main --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-xl bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="font-mono text-xs text-gray-400">{{ $bug->reference() }}</p>
                        <h1 class="mt-1 text-xl font-semibold text-gray-900">{{ $bug->title }}</h1>
                    </div>
                    <span class="shrink-0 inline-flex rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700">{{ ucfirst(str_replace('_',' ',$bug->status)) }}</span>
                </div>

                <h3 class="mt-5 text-xs font-semibold uppercase tracking-wide text-gray-400">Description</h3>
                <p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ $bug->description }}</p>

                @if($bug->steps_to_reproduce)
                    <h3 class="mt-5 text-xs font-semibold uppercase tracking-wide text-gray-400">Steps to reproduce</h3>
                    <p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ $bug->steps_to_reproduce }}</p>
                @endif
            </div>

            @if($conversationExcerpt->isNotEmpty())
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Conversation excerpt</h3>
                    <div class="mt-3 space-y-2">
                        @foreach($conversationExcerpt as $m)
                            <div class="text-sm">
                                <span class="font-medium {{ $m->role === 'user' ? 'text-gray-900' : 'text-indigo-600' }}">{{ $m->role === 'user' ? 'User' : 'Assistant' }}:</span>
                                <span class="text-gray-600">{{ \Illuminate\Support\Str::limit($m->content, 300) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            <div class="rounded-xl bg-white p-6 shadow-sm">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Details</h3>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Severity</dt><dd class="font-medium text-gray-800">{{ ucfirst($bug->severity) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Area</dt><dd class="font-medium text-gray-800">{{ $bug->area ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Tenant</dt><dd class="font-medium text-gray-800">{{ $bug->tenant?->name ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Reporter</dt><dd class="font-medium text-gray-800">{{ $bug->reporter?->name ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Reported</dt><dd class="font-medium text-gray-800">{{ $bug->created_at->format('M d, Y g:i A') }}</dd></div>
                    @if($bug->github_pr_url)
                        <div class="flex justify-between"><dt class="text-gray-500">PR</dt><dd><a href="{{ $bug->github_pr_url }}" target="_blank" class="font-medium text-indigo-600 hover:text-indigo-500">View PR</a></dd></div>
                    @endif
                </dl>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">AI Programmer</h3>
                @if(in_array($bug->status, ['new','triaged']))
                    <form method="POST" action="{{ route('admin.bugs.fix', $bug->id) }}" class="mt-3"
                          onsubmit="return confirm('Send {{ $bug->reference() }} to the AI Programmer?')">
                        @csrf
                        <button type="submit" class="w-full rounded-lg bg-gradient-to-br from-indigo-600 to-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:from-indigo-500 hover:to-violet-500">
                            Fix with AI Programmer
                        </button>
                    </form>
                    <p class="mt-2 text-xs text-gray-500">Hands this bug to Claude Code, which will open a fix PR for review.</p>
                @else
                    <p class="mt-3 text-sm text-gray-600">This bug was sent to the AI Programmer.</p>
                    {{-- Phase 1: manual status advance (Phase 2 the GitHub webhook does this automatically) --}}
                    <form method="POST" action="{{ route('admin.bugs.status', $bug->id) }}" class="mt-3 space-y-2">
                        @csrf
                        <select name="status" class="block w-full rounded-md border-gray-300 text-sm">
                            <option value="pr_opened">PR opened</option>
                            <option value="merged">Merged / shipped</option>
                            <option value="rejected">Rejected</option>
                            <option value="closed">Closed</option>
                        </select>
                        <button type="submit" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Update status</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection
