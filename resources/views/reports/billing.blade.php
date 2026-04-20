<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Billing Report') }}</h2>
            <a href="{{ route('reports.export.billing', $filters) }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                {{ __('Export CSV') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
            {{-- Filters --}}
            <div class="mb-6 rounded-xl bg-white p-4 shadow-sm">
                <form method="GET" action="{{ route('reports.billing') }}">
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                        <div>
                            <label for="from" class="block text-xs font-medium text-gray-500">{{ __('From') }}</label>
                            <input type="date" name="from" id="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label for="to" class="block text-xs font-medium text-gray-500">{{ __('To') }}</label>
                            <input type="date" name="to" id="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label for="billing_status" class="block text-xs font-medium text-gray-500">{{ __('Billing Status') }}</label>
                            <select name="billing_status" id="billing_status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('All') }}</option>
                                <option value="billed" {{ ($filters['billing_status'] ?? '') === 'billed' ? 'selected' : '' }}>{{ __('Billed') }}</option>
                                <option value="unbilled" {{ ($filters['billing_status'] ?? '') === 'unbilled' ? 'selected' : '' }}>{{ __('Unbilled') }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="client_id" class="block text-xs font-medium text-gray-500">{{ __('Client') }}</label>
                            <select name="client_id" id="client_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('All Clients') }}</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}" {{ ($filters['client_id'] ?? '') == $client->id ? 'selected' : '' }}>{{ $client->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="department_id" class="block text-xs font-medium text-gray-500">{{ __('Department') }}</label>
                            <select name="department_id" id="department_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('All Departments') }}</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" {{ ($filters['department_id'] ?? '') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="assigned_to" class="block text-xs font-medium text-gray-500">{{ __('Agent') }}</label>
                            <select name="assigned_to" id="assigned_to" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('All Agents') }}</option>
                                @foreach($agents as $agent)
                                    <option value="{{ $agent->id }}" {{ ($filters['assigned_to'] ?? '') == $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-2">
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Apply Filters') }}</button>
                        <a href="{{ route('reports.billing') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">{{ __('Reset') }}</a>
                    </div>
                </form>
            </div>

            {{-- Summary Cards --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 mb-6">
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="text-sm font-medium text-gray-500">{{ __('Total Billable') }}</div>
                    <div class="mt-2 text-3xl font-semibold text-gray-900">{{ $summary['total_billable'] }}</div>
                    <div class="mt-1 text-sm text-gray-500">{{ \App\Models\AppSetting::formatCurrency($summary['total_amount']) }}</div>
                </div>
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="text-sm font-medium text-gray-500">{{ __('Billed') }}</div>
                    <div class="mt-2 text-3xl font-semibold text-green-600">{{ $summary['total_billed'] }}</div>
                    <div class="mt-1 text-sm text-green-600">{{ \App\Models\AppSetting::formatCurrency($summary['billed_amount']) }}</div>
                </div>
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="text-sm font-medium text-gray-500">{{ __('Unbilled') }}</div>
                    <div class="mt-2 text-3xl font-semibold text-orange-600">{{ $summary['total_unbilled'] }}</div>
                    <div class="mt-1 text-sm text-orange-600">{{ \App\Models\AppSetting::formatCurrency($summary['unbilled_amount']) }}</div>
                </div>
            </div>

            {{-- Table --}}
            <div class="overflow-hidden rounded-xl bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Ticket') }}</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Client') }}</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Department') }}</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Agent') }}</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Amount') }}</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Status') }}</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Billed Date') }}</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Notes') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse($billingTickets as $ticket)
                                <tr>
                                    <td class="px-4 py-3 text-sm">
                                        <a href="{{ route('tickets.show', $ticket) }}" class="font-medium text-indigo-600 hover:text-indigo-900">{{ $ticket->ticket_number }}</a>
                                        <div class="text-xs text-gray-500 truncate max-w-[150px]">{{ $ticket->subject }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ $ticket->client?->name ?? '-' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ $ticket->department?->name ?? '-' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ $ticket->assignee?->name ?? '-' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-gray-900">{{ \App\Models\AppSetting::formatCurrency($ticket->billable_amount) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        @if($ticket->billed_at)
                                            <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">{{ __('Billed') }}</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-medium text-orange-800">{{ __('Unbilled') }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-500">{{ $ticket->billed_at?->format('m/d/Y') ?? '-' }}</td>
                                    <td class="px-4 py-3 text-xs text-gray-500 truncate max-w-[150px]">{{ $ticket->billable_description ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-12 text-center text-sm text-gray-500">{{ __('No billable tickets found.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
