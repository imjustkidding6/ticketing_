<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Learned Answers') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="mb-4 flex items-center justify-between">
                <a href="{{ route('settings.ai') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; {{ __('Back to AI settings') }}</a>
                <span class="text-xs text-gray-500">{{ __('Answers your agents saved from the assistant. These are reused for similar questions — remove any that are wrong or sensitive.') }}</span>
            </div>

            @if(session('success'))
                <div class="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
            @endif

            <div class="space-y-3">
                @forelse($snippets as $snippet)
                    <div class="rounded-xl bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-gray-900">{{ $snippet->question }}</p>
                                <p class="mt-1 whitespace-pre-line text-sm text-gray-600">{{ \Illuminate\Support\Str::limit($snippet->answer, 500) }}</p>
                                <p class="mt-2 text-xs text-gray-400">
                                    {{ __('Saved by') }} {{ $snippet->creator?->name ?? __('Unknown') }}
                                    &middot; {{ \App\Support\TenantTime::format($snippet->created_at, 'M d, Y g:i A') }}
                                    @if(! $snippet->embedding)
                                        &middot; <span class="text-amber-600">{{ __('not searchable (no embedding)') }}</span>
                                    @endif
                                </p>
                            </div>
                            <form method="POST" action="{{ route('settings.ai.knowledge.delete', $snippet) }}" onsubmit="return confirm('{{ __('Remove this learned answer?') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="shrink-0 rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 hover:text-red-600">{{ __('Remove') }}</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center">
                        <p class="text-sm font-medium text-gray-700">{{ __('Nothing learned yet.') }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ __('When the "Let agents teach the assistant from chat" setting is on, agents can save a helpful reply with the "Save to knowledge" button in the assistant.') }}</p>
                    </div>
                @endforelse
            </div>

            <div class="mt-4">{{ $snippets->links() }}</div>
        </div>
    </div>
</x-app-layout>
