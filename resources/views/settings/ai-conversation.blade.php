<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <a href="{{ route('settings.ai.conversations') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
            </a>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('AI Conversation') }}</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="mb-4 rounded-lg bg-white p-4 text-sm shadow-sm">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div><span class="block text-xs text-gray-400">{{ __('Channel') }}</span>{{ $conversation->channel === 'agent' ? __('In-app') : __('Portal') }}</div>
                    <div><span class="block text-xs text-gray-400">{{ __('Who') }}</span>{{ $conversation->user?->name ?? $conversation->client?->email ?? __('Guest') }}</div>
                    <div><span class="block text-xs text-gray-400">{{ __('Started') }}</span>{{ \App\Support\TenantTime::format($conversation->created_at, 'M d, Y g:i A') }}</div>
                    <div><span class="block text-xs text-gray-400">{{ __('Messages') }}</span>{{ $messages->count() }}</div>
                </div>
            </div>

            <div class="space-y-3 rounded-xl bg-gray-50 p-4 shadow-inner">
                @forelse($messages as $m)
                    <div class="{{ $m->role === 'user' ? 'flex justify-end' : 'flex justify-start' }}">
                        <div class="max-w-[80%] whitespace-pre-line break-words rounded-2xl px-3 py-2 text-sm {{ $m->role === 'user' ? 'rounded-br-sm bg-indigo-600 text-white' : 'rounded-bl-sm bg-white text-gray-800 ring-1 ring-gray-200' }}">
                            {{ $m->content }}
                            <span class="mt-1 block text-[10px] {{ $m->role === 'user' ? 'text-indigo-200' : 'text-gray-400' }}">{{ \App\Support\TenantTime::format($m->created_at, 'g:i A') }}</span>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-gray-500">{{ __('This conversation has no messages.') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
