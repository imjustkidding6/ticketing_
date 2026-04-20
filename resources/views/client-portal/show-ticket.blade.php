<x-client-portal-layout :tenant="$tenant">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        <!-- Ticket Header -->
        <div class="flex items-start justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-semibold text-gray-900">{{ $ticket->ticket_number }}</h1>
                    <x-badge :type="$ticket->status">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</x-badge>
                    <x-badge :type="$ticket->priority">{{ ucfirst($ticket->priority) }}</x-badge>
                </div>
                <h2 class="mt-2 text-lg text-gray-700">{{ $ticket->subject }}</h2>
            </div>
            <a href="{{ route('portal.dashboard', ['tenant' => $tenant->slug]) }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                {{ __('Back to Dashboard') }}
            </a>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <!-- Ticket Details -->
            <div class="lg:col-span-2">
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-400">{{ __('Description') }}</h3>
                    <div class="mt-4 prose prose-sm max-w-none text-gray-700">
                        {!! nl2br(e($ticket->description)) !!}
                    </div>
                </div>

                <!-- Comments section (placeholder - will be built in Phase 11) -->
                <div class="mt-6 rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-400">{{ __('Updates') }}</h3>
                    <p class="mt-4 text-sm text-gray-500">{{ __('Ticket updates and replies will appear here.') }}</p>
                </div>
            </div>

            <!-- Sidebar Info -->
            <div class="lg:col-span-1">
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-400">{{ __('Details') }}</h3>
                    <dl class="mt-4 space-y-4">
                        @if($ticket->department)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('Department') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $ticket->department->name }}</dd>
                        </div>
                        @endif
                        @if($ticket->category)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('Category') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $ticket->category->name }}</dd>
                        </div>
                        @endif
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('Created') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $ticket->created_at->format('M d, Y \a\t g:i A') }}</dd>
                        </div>
                        @if($ticket->closed_at)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('Closed') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $ticket->closed_at->format('M d, Y \a\t g:i A') }}</dd>
                        </div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-client-portal-layout>
