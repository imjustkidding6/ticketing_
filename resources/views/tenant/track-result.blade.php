<x-client-portal-layout :tenant="$tenant" :hide-nav="true">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        @if(session('success'))
            <div class="mb-6 rounded-md bg-green-50 border border-green-200 p-4">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        @if(session('info'))
            <div class="mb-6 rounded-md bg-indigo-50 border border-indigo-200 p-4">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                    </svg>
                    <p class="text-sm font-medium text-indigo-800">{{ session('info') }}</p>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 rounded-md bg-red-50 border border-red-200 p-4">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                    <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Left Column: Ticket Details --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Header --}}
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h2 class="text-2xl font-semibold text-gray-900">{{ $ticket->subject }}</h2>
                            <p class="mt-1 text-sm text-gray-500">
                                {{ __('Created on') }} {{ $ticket->created_at->format('F d, Y \a\t g:i A') }}
                                @if($ticket->category)
                                    &middot; {{ __('Category') }}: {{ $ticket->category->name }}
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium
                                @if(in_array($ticket->status, ['open', 'assigned', 'in_progress'])) bg-blue-100 text-blue-800
                                @elseif($ticket->status === 'on_hold') bg-yellow-100 text-yellow-800
                                @elseif($ticket->status === 'closed') bg-green-100 text-green-800
                                @else bg-gray-100 text-gray-800
                                @endif">
                                {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                            </span>
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium
                                @if($ticket->priority === 'critical') bg-red-100 text-red-800
                                @elseif($ticket->priority === 'high') bg-orange-100 text-orange-800
                                @elseif($ticket->priority === 'medium') bg-yellow-100 text-yellow-800
                                @else bg-gray-100 text-gray-800
                                @endif">
                                {{ ucfirst($ticket->priority) }}
                            </span>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">{{ __('Description') }}</h4>
                        <div class="prose prose-sm text-gray-600 max-w-none">
                            {!! nl2br(e($ticket->description)) !!}
                        </div>
                    </div>

                    {{-- Ticket Attachments --}}
                    @if($ticket->attachments && count($ticket->attachments) > 0)
                    <div class="mt-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">{{ __('Attachments') }}</h4>
                        <div class="flex flex-wrap gap-2">
                            @foreach($ticket->attachments as $attachment)
                                <span class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-xs text-gray-600">
                                    <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                                    </svg>
                                    {{ $attachment['name'] }}
                                    <span class="text-gray-400">({{ number_format(($attachment['size'] ?? 0) / 1024, 0) }}KB)</span>
                                </span>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Status Explanation --}}
                    <div class="mt-4 rounded-lg border border-indigo-200 bg-indigo-50 p-4">
                        <div class="flex items-start gap-3">
                            <svg class="h-5 w-5 text-indigo-600 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                            </svg>
                            <div>
                                <h4 class="text-sm font-medium text-indigo-900">{{ __('Current Status') }}</h4>
                                <p class="text-sm text-indigo-800 mt-1">
                                    @switch($ticket->status)
                                        @case('open')
                                            {{ __('Your ticket has been received and is waiting to be assigned to a support agent.') }}
                                            @break
                                        @case('assigned')
                                            {{ __('Your ticket has been assigned to a support agent who will review it shortly.') }}
                                            @break
                                        @case('in_progress')
                                            {{ __('A support agent is actively working on your ticket.') }}
                                            @break
                                        @case('on_hold')
                                            {{ __('Your ticket is temporarily on hold. We will resume work as soon as possible.') }}
                                            @break
                                        @case('closed')
                                            {{ __('This ticket has been completed and closed.') }}
                                            @break
                                        @case('cancelled')
                                            {{ __('Your ticket has been cancelled. If you need further assistance, please create a new ticket.') }}
                                            @break
                                        @default
                                            {{ __('Your ticket is being processed.') }}
                                    @endswitch
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Comments & Updates (Enterprise - client_comments feature) --}}
                @if($canReply ?? false)
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ __('Comments & Updates') }}</h3>

                    @if(($ticket->comments ?? collect())->isNotEmpty())
                        <div class="space-y-4">
                            @foreach($ticket->comments as $comment)
                                <div class="rounded-lg border {{ $comment->user_id ? 'border-indigo-200 bg-indigo-50' : 'border-gray-200 bg-gray-50' }} p-4">
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="flex h-6 w-6 items-center justify-center rounded-full {{ $comment->user_id ? 'bg-indigo-200 text-indigo-700' : 'bg-gray-200 text-gray-600' }} text-xs font-semibold shrink-0">
                                            {{ $comment->user_id ? strtoupper(substr($comment->user?->name ?? 'A', 0, 1)) : 'Y' }}
                                        </div>
                                        <span class="text-sm font-medium {{ $comment->user_id ? 'text-indigo-900' : 'text-gray-900' }}">
                                            {{ $comment->user_id ? ($comment->user?->name ?? __('Support Agent')) : __('You') }}
                                        </span>
                                        <span class="text-xs text-gray-400">{{ $comment->created_at->format('M d, Y g:i A') }}</span>
                                    </div>
                                    <div class="text-sm text-gray-700">
                                        {!! nl2br(e($comment->content)) !!}
                                    </div>
                                    @if($comment->attachments)
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @foreach($comment->attachments as $attachment)
                                                <span class="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-gray-50 px-2 py-1 text-xs text-gray-600">
                                                    <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                                                    </svg>
                                                    {{ $attachment['name'] }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-400 text-center py-2">{{ __('No updates yet.') }}</p>
                    @endif

                    {{-- Reply Form --}}
                    @if(($canReply ?? false) && $ticket->tracking_token)
                        <form method="POST" action="{{ route('tenant.track-ticket.reply', ['slug' => $tenant->slug, 'token' => $ticket->tracking_token]) }}" enctype="multipart/form-data" class="mt-4 border-t border-gray-200 pt-4">
                            @csrf
                            <div class="space-y-3">
                                <textarea name="content" rows="3" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="{{ __('Write a reply...') }}"></textarea>
                                @error('content') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                <div>
                                    <input type="file" name="attachments[]" multiple class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-gray-700 hover:file:bg-gray-200">
                                    <p class="mt-1 text-xs text-gray-400">{{ __('Max 3 files, 10MB each.') }}</p>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" class="rounded-md px-4 py-2 text-sm font-semibold text-white shadow-sm" style="background-color: var(--portal-primary);">
                                        {{ __('Send Reply') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>
                @endif
            </div>

            {{-- Right Column: Sidebar --}}
            <div class="space-y-6">
                {{-- Ticket Status --}}
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ __('Ticket Status') }}</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">{{ __('Current Status') }}</span>
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                @if(in_array($ticket->status, ['open', 'assigned', 'in_progress'])) bg-blue-100 text-blue-800
                                @elseif($ticket->status === 'on_hold') bg-yellow-100 text-yellow-800
                                @elseif($ticket->status === 'closed') bg-green-100 text-green-800
                                @else bg-gray-100 text-gray-800
                                @endif">
                                {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-600">
                            @switch($ticket->status)
                                @case('open')
                                    {{ __('Your ticket has been received and is waiting to be assigned to a support agent.') }}
                                    @break
                                @case('assigned')
                                    {{ __('Your ticket has been assigned to a support agent who will review it shortly.') }}
                                    @break
                                @case('in_progress')
                                    {{ __('A support agent is actively working on your ticket.') }}
                                    @break
                                @case('on_hold')
                                    {{ __('Your ticket is temporarily on hold. We will resume work as soon as possible.') }}
                                    @break
                                @case('closed')
                                    {{ __('This ticket has been completed and closed.') }}
                                    @break
                                @case('cancelled')
                                    {{ __('Your ticket has been cancelled. If you need further assistance, please create a new ticket.') }}
                                    @break
                                @default
                                    {{ __('Your ticket is being processed.') }}
                            @endswitch
                        </p>
                        <div class="border-t border-gray-100 pt-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">{{ __('Priority') }}</span>
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                    @if($ticket->priority === 'critical') bg-red-100 text-red-800
                                    @elseif($ticket->priority === 'high') bg-orange-100 text-orange-800
                                    @elseif($ticket->priority === 'medium') bg-yellow-100 text-yellow-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ ucfirst($ticket->priority) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Ticket Information --}}
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ __('Ticket Information') }}</h3>
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-gray-500">{{ __('Ticket Number') }}</dt>
                            <dd class="font-mono font-medium text-gray-900">{{ $ticket->ticket_number }}</dd>
                        </div>
                        @if($ticket->client)
                        <div>
                            <dt class="text-gray-500">{{ __('Organization') }}</dt>
                            <dd class="text-gray-900">{{ $ticket->client->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ __('Contact Email') }}</dt>
                            <dd class="text-gray-900">{{ $ticket->client->email }}</dd>
                        </div>
                        @endif
                        @if($ticket->department)
                        <div>
                            <dt class="text-gray-500">{{ __('Department') }}</dt>
                            <dd class="text-gray-900">{{ $ticket->department->name }}</dd>
                        </div>
                        @endif
                        @if($ticket->category)
                        <div>
                            <dt class="text-gray-500">{{ __('Category') }}</dt>
                            <dd class="text-gray-900">{{ $ticket->category->name }}</dd>
                        </div>
                        @endif
                        <div>
                            <dt class="text-gray-500">{{ __('Created') }}</dt>
                            <dd class="text-gray-900">{{ $ticket->created_at->format('M d, Y g:i A') }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ __('Last Updated') }}</dt>
                            <dd class="text-gray-900">{{ $ticket->updated_at->diffForHumans() }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- Quick Actions --}}
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ __('Quick Actions') }}</h3>
                    <div class="space-y-2">
                        <a href="{{ route('tenant.track-ticket', ['slug' => $tenant->slug]) }}" class="flex items-center justify-center w-full rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                            <svg class="mr-2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                            {{ __('Track Another Ticket') }}
                        </a>
                        <a href="{{ route('tenant.submit-ticket', ['slug' => $tenant->slug]) }}" class="flex items-center justify-center w-full rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                            <svg class="mr-2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            {{ __('Submit New Ticket') }}
                        </a>
                        <a href="{{ route('tenant.landing', ['slug' => $tenant->slug]) }}" class="flex items-center justify-center w-full rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                            <svg class="mr-2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                            </svg>
                            {{ __('Back to Home') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-client-portal-layout>
