@extends('layouts.admin')

@section('content')
<div class="space-y-6" x-data="aiPlayground()">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">AI Prompt Playground</h1>
            <p class="text-sm text-gray-500">Test, evaluate, and benchmark system prompts directly against OpenAI models.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Input configuration --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">System Prompt Instructions</label>
                <textarea x-model="systemPrompt" rows="6" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="You are a senior customer support agent..."></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">User Input Message</label>
                <textarea x-model="userMessage" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="How do I configure custom SLA breach warnings?"></textarea>
            </div>
            <button type="button" @click="runTest()" :disabled="loading" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50">
                <span x-show="!loading">Run Prompt Test</span>
                <span x-show="loading">Generating Output...</span>
            </button>
        </div>

        {{-- Output Response & Stats --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm flex flex-col justify-between space-y-4">
            <div>
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-2">Model Completion Output</h2>
                <div class="min-h-[220px] rounded-lg bg-gray-50 p-4 border border-gray-200 font-mono text-sm whitespace-pre-line text-gray-800" x-text="output || 'Click Run Prompt Test to view model response output...'"></div>
            </div>

            <div x-show="stats" class="grid grid-cols-3 gap-2 border-t border-gray-200 pt-3 text-center">
                <div>
                    <span class="block text-xs text-gray-500">Latency</span>
                    <span class="font-bold text-gray-900" x-text="stats.duration + 's'"></span>
                </div>
                <div>
                    <span class="block text-xs text-gray-500">Prompt Tokens</span>
                    <span class="font-bold text-gray-900" x-text="stats.prompt_tokens"></span>
                </div>
                <div>
                    <span class="block text-xs text-gray-500">Completion Tokens</span>
                    <span class="font-bold text-gray-900" x-text="stats.completion_tokens"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function aiPlayground() {
        return {
            systemPrompt: 'You are Nexus AI, an intelligent customer support assistant for a SaaS ticketing platform. Answer concisely and accurately.',
            userMessage: 'How do I check my ticket status?',
            output: '',
            loading: false,
            stats: null,

            async runTest() {
                if (!this.systemPrompt || !this.userMessage || this.loading) return;
                this.loading = true;
                this.output = '';

                try {
                    const res = await fetch('{{ route('admin.ai.playground.run') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            system_prompt: this.systemPrompt,
                            user_message: this.userMessage
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.output = data.reply;
                        this.stats = {
                            duration: data.duration_seconds,
                            prompt_tokens: data.usage?.prompt_tokens ?? 0,
                            completion_tokens: data.usage?.completion_tokens ?? 0
                        };
                    } else {
                        this.output = 'Error: ' + (data.message || 'Failed to generate prompt output');
                    }
                } catch (e) {
                    this.output = 'Error: Connection failed.';
                } finally {
                    this.loading = false;
                }
            }
        };
    }
</script>
@endsection
