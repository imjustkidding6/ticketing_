<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('dashboard') }}" class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
            </a>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('My Recent Activity') }}</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl px-4 sm:px-4 lg:px-6">

            <div class="rounded-xl bg-white shadow-sm">
                @if($myActivity->count() > 0)
                    <ul class="divide-y divide-gray-200">
                        @foreach($myActivity as $entry)
                            <li class="p-4 sm:px-6">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-sm text-gray-700">{{ $entry->description ?? ucfirst(str_replace('_', ' ', $entry->action)) }}</p>
                                        @if($entry->ticket)
                                            <a href="{{ route('tickets.show', $entry->ticket_id) }}" class="text-xs text-indigo-600 hover:text-indigo-800">
                                                {{ $entry->ticket->ticket_number }} — {{ Str::limit($entry->ticket->subject, 40) }}
                                            </a>
                                        @endif
                                    </div>
                                    <span class="text-xs text-gray-400 shrink-0" title="{{ $entry->created_at->format('M j, Y g:i A') }}">
                                        {{ $entry->created_at->diffForHumans() }}
                                    </span>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <div class="border-t border-gray-200 p-4 sm:px-6">
                        {{ $myActivity->links() }}
                    </div>
                @else
                    <p class="p-6 text-sm text-gray-500">{{ __('No recent activity.') }}</p>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>