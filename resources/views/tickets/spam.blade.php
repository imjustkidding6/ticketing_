<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Spam Tickets') }}</h2>
                <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">{{ $tickets->total() }}</span>
            </div>
            <a href="{{ route('tickets.index') }}" class="inline-flex items-center rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
                <svg class="-ml-0.5 mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                </svg>
                {{ __('Back to Tickets') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6"
             x-data="{ selected: [], get allChecked() { return this.selected.length > 0 && this.selected.length === document.querySelectorAll('[data-row-check]').length; } }">

            <div class="mb-4 rounded-xl bg-white p-4 shadow-sm">
                <form method="GET" action="{{ route('tickets.spam') }}" class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[240px]">
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Search') }}</label>
                        <input type="search" name="search" value="{{ request('search') }}" placeholder="{{ __('Ticket #, subject, or description') }}"
                               class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <button type="submit" class="rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">{{ __('Filter') }}</button>
                    @if(request('search'))
                        <a href="{{ route('tickets.spam') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Clear') }}</a>
                    @endif
                </form>
            </div>

            <form method="POST" action="{{ route('tickets.spam.destroy') }}"
                  onsubmit="return confirm('{{ __('Permanently delete the selected spam tickets? This cannot be undone.') }}');">
                @csrf
                @method('DELETE')

                <div class="mb-3 flex items-center justify-between rounded-xl bg-white p-3 shadow-sm">
                    <div class="text-sm text-gray-600">
                        <span x-text="selected.length === 0 ? '{{ __('No tickets selected') }}' : selected.length + ' {{ __('selected') }}'"></span>
                    </div>
                    @can('delete tickets')
                    <button type="submit"
                            :disabled="selected.length === 0"
                            :class="selected.length === 0 ? 'opacity-40 cursor-not-allowed' : ''"
                            class="inline-flex items-center gap-1.5 rounded-md bg-red-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-red-500">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165" />
                        </svg>
                        {{ __('Delete Selected') }}
                    </button>
                    @endcan
                </div>

                <x-data-table>
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left">
                                <input type="checkbox"
                                       class="h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500"
                                       @change="selected = $event.target.checked ? Array.from(document.querySelectorAll('[data-row-check]')).map(c => c.value) : []"
                                       :checked="allChecked">
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Ticket #') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Subject') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Client') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Reason') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Marked By') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Marked At') }}</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse($tickets as $ticket)
                            <tr>
                                <td class="px-4 py-3">
                                    <input type="checkbox" name="ticket_ids[]" value="{{ $ticket->id }}" data-row-check
                                           x-model="selected"
                                           class="h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <a href="{{ route('tickets.show', $ticket) }}" class="font-mono text-sm font-semibold text-indigo-600 hover:text-indigo-800">{{ $ticket->ticket_number }}</a>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ Str::limit($ticket->subject, 60) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ $ticket->client?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ Str::limit($ticket->spam_reason ?? '-', 40) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ $ticket->markedSpamBy?->name ?? '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ $ticket->marked_spam_at ? \App\Support\TenantTime::format($ticket->marked_spam_at, 'm/d/Y g:i A') : '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                                    <form method="POST" action="{{ route('tickets.unmark-spam', $ticket) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center rounded-md bg-green-50 px-2.5 py-1.5 text-xs font-medium text-green-700 hover:bg-green-100" onclick="event.stopPropagation();">
                                            {{ __('Not Spam') }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <x-empty-state :colspan="8" :message="__('No spam tickets.')" :action-url="route('tickets.index')" :action-label="__('Back to tickets')">
                                <x-slot name="icon">
                                    <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                    </svg>
                                </x-slot>
                            </x-empty-state>
                        @endforelse
                    </tbody>
                </x-data-table>
            </form>

            @if($tickets->hasPages())
                <div class="mt-4">
                    {{ $tickets->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
