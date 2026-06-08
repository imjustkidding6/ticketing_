<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('AI Conversations') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="mb-4 flex items-center justify-between">
                <a href="{{ route('settings.ai') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; {{ __('Back to AI settings') }}</a>
                <span class="text-xs text-gray-500">{{ __('Saved transcripts of every AI conversation in this workspace.') }}</span>
            </div>

            <div class="overflow-hidden rounded-xl bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3">{{ __('Channel') }}</th>
                            <th class="px-4 py-3">{{ __('Who') }}</th>
                            <th class="px-4 py-3">{{ __('Messages') }}</th>
                            <th class="px-4 py-3">{{ __('Started') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        @forelse($conversations as $c)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    @if($c->channel === 'agent')
                                        <span class="inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">{{ __('In-app') }}</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">{{ __('Portal') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ $c->user?->name ?? $c->client?->email ?? __('Guest') }}
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ $c->messages_count }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ \App\Support\TenantTime::format($c->created_at, 'M d, Y g:i A') }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('settings.ai.conversation', $c) }}" class="font-medium text-indigo-600 hover:text-indigo-500">{{ __('View') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500">{{ __('No AI conversations yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $conversations->links() }}</div>
        </div>
    </div>
</x-app-layout>
