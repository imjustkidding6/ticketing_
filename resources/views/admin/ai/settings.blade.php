@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">AI Platform Settings & Capabilities</h1>
            <p class="text-sm text-gray-500">Configure global OpenAI parameters and toggle AI capabilities system-wide.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-emerald-50 p-4 text-sm font-medium text-emerald-800 ring-1 ring-emerald-200">
            {{ session('success') }}
        </div>
    @endif

    <form action="{{ route('admin.ai.settings.update') }}" method="POST" class="space-y-6">
        @csrf

        {{-- Model & Hyperparameters --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-6">
            <h2 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-3">OpenAI Model Parameters</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Chat Completion Model</label>
                    <input type="text" name="openai_model" value="{{ old('openai_model', $settings['openai_model']) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Embedding Model</label>
                    <input type="text" name="embedding_model" value="{{ old('embedding_model', $settings['embedding_model']) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Temperature (0.0 to 2.0)</label>
                    <input type="number" step="0.1" min="0" max="2" name="temperature" value="{{ old('temperature', $settings['temperature']) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Max Completion Tokens</label>
                    <input type="number" name="max_tokens" value="{{ old('max_tokens', $settings['max_tokens']) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Top P</label>
                    <input type="number" step="0.05" min="0" max="1" name="top_p" value="{{ old('top_p', $settings['top_p']) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Frequency Penalty</label>
                    <input type="number" step="0.1" min="-2" max="2" name="frequency_penalty" value="{{ old('frequency_penalty', $settings['frequency_penalty']) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Presence Penalty</label>
                    <input type="number" step="0.1" min="-2" max="2" name="presence_penalty" value="{{ old('presence_penalty', $settings['presence_penalty']) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
            </div>
        </div>

        {{-- Feature Flags --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-6">
            <h2 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-3">AI Capability Toggles</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <label class="flex items-center space-x-3 p-3 rounded-lg border border-gray-100 hover:bg-gray-50">
                    <input type="checkbox" name="feature_portal_ai" value="1" {{ $settings['feature_portal_ai'] ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm font-medium text-gray-900">Customer Portal AI Chat</span>
                </label>
                <label class="flex items-center space-x-3 p-3 rounded-lg border border-gray-100 hover:bg-gray-50">
                    <input type="checkbox" name="feature_agent_copilot" value="1" {{ $settings['feature_agent_copilot'] ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm font-medium text-gray-900">Agent Workspace AI Copilot</span>
                </label>
                <label class="flex items-center space-x-3 p-3 rounded-lg border border-gray-100 hover:bg-gray-50">
                    <input type="checkbox" name="feature_knowledge_search" value="1" {{ $settings['feature_knowledge_search'] ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm font-medium text-gray-900">Knowledge Base Tool Calling</span>
                </label>
                <label class="flex items-center space-x-3 p-3 rounded-lg border border-gray-100 hover:bg-gray-50">
                    <input type="checkbox" name="feature_web_search" value="1" {{ $settings['feature_web_search'] ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm font-medium text-gray-900">Web Search Integration</span>
                </label>
                <label class="flex items-center space-x-3 p-3 rounded-lg border border-gray-100 hover:bg-gray-50">
                    <input type="checkbox" name="feature_vision" value="1" {{ $settings['feature_vision'] ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm font-medium text-gray-900">Multimodal Image Vision Support</span>
                </label>
                <label class="flex items-center space-x-3 p-3 rounded-lg border border-gray-100 hover:bg-gray-50">
                    <input type="checkbox" name="feature_self_learning" value="1" {{ $settings['feature_self_learning'] ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm font-medium text-gray-900">Self-Learning Knowledge Snippets</span>
                </label>
                <label class="flex items-center space-x-3 p-3 rounded-lg border border-gray-100 hover:bg-gray-50">
                    <input type="checkbox" name="feature_bug_reporting" value="1" {{ $settings['feature_bug_reporting'] ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm font-medium text-gray-900">Automated Bug Reporting</span>
                </label>
                <label class="flex items-center space-x-3 p-3 rounded-lg border border-gray-100 hover:bg-gray-50">
                    <input type="checkbox" name="feature_charts" value="1" {{ $settings['feature_charts'] ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm font-medium text-gray-900">Interactive ApexCharts Rendering</span>
                </label>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">Save AI Configuration</button>
        </div>
    </form>
</div>
@endsection
