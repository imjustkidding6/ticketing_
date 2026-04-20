<x-client-portal-layout :tenant="$tenant">
    <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
        @if(session('success'))
            <div class="mb-6 rounded-md bg-green-50 border border-green-200 p-4">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        <div class="rounded-xl bg-white p-8 shadow-sm">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-semibold text-gray-900">{{ $ticket->ticket_number }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ __('Submitted') }} {{ $ticket->created_at->format('M d, Y \a\t g:i A') }}</p>
                </div>
                <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium
                    @if(in_array($ticket->status, ['open', 'assigned', 'in_progress'])) bg-blue-100 text-blue-800
                    @elseif($ticket->status === 'on_hold') bg-yellow-100 text-yellow-800
                    @elseif($ticket->status === 'closed') bg-green-100 text-green-800
                    @else bg-gray-100 text-gray-800
                    @endif">
                    {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                </span>
            </div>

            <div class="border-t border-gray-200 pt-6 space-y-4">
                <div>
                    <h3 class="text-lg font-medium text-gray-900">{{ $ticket->subject }}</h3>
                    <div class="mt-3 prose prose-sm text-gray-600 max-w-none">
                        {!! nl2br(e($ticket->description)) !!}
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 pt-4 border-t border-gray-100">
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">{{ __('Priority') }}</dt>
                        <dd class="mt-1">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                @if($ticket->priority === 'critical') bg-red-100 text-red-800
                                @elseif($ticket->priority === 'high') bg-orange-100 text-orange-800
                                @elseif($ticket->priority === 'medium') bg-yellow-100 text-yellow-800
                                @else bg-gray-100 text-gray-800
                                @endif">
                                {{ ucfirst($ticket->priority) }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">{{ __('Department') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $ticket->department?->name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">{{ __('Category') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $ticket->category?->name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">{{ __('Last Updated') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $ticket->updated_at->diffForHumans() }}</dd>
                    </div>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-gray-200 flex items-center justify-between">
                <p class="text-xs text-gray-400">{{ __('Bookmark this page to check back on your ticket status.') }}</p>
                <a href="{{ route('tenant.submit-ticket', ['slug' => $tenant->slug]) }}" class="text-sm" style="color: var(--portal-primary);">{{ __('Submit another ticket') }}</a>
            </div>
        </div>
    </div>
</x-client-portal-layout>
