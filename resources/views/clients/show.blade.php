<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $client->name }}</h2>
            <a href="{{ route('clients.edit', $client) }}" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                {{ __('Edit Client') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Client Details -->
                <div class="lg:col-span-1">
                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">{{ __('Client Details') }}</h3>
                        <dl class="mt-4 space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('Email') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $client->email }}</dd>
                            </div>
                            @if($client->phone)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('Phone') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $client->phone }}</dd>
                            </div>
                            @endif
                            @if($client->contact_person)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('Contact Person') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $client->contact_person }}</dd>
                            </div>
                            @endif
                            @if($client->address)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('Address') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $client->address }}</dd>
                            </div>
                            @endif
                            @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::SlaManagement))
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('Tier') }}</dt>
                                <dd class="mt-1">
                                    <x-badge :type="$client->tier">{{ ucfirst($client->tier) }}</x-badge>
                                </dd>
                            </div>
                            @endif
                            @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::SlaManagement))
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('Portal Access') }}</dt>
                                <dd class="mt-1">
                                    <x-badge :type="$client->hasPortalAccess() ? 'active' : 'inactive'">{{ $client->hasPortalAccess() ? __('Enabled') : __('No access') }}</x-badge>
                                </dd>
                            </div>
                            @endif
                        </dl>
                    </div>
                </div>

                <!-- Client Tickets -->
                <div class="lg:col-span-2">
                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900">{{ __('Recent Tickets') }}</h3>
                            <a href="{{ route('tickets.create', ['client_id' => $client->id]) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">{{ __('Create Ticket') }}</a>
                        </div>
                        @php
                            $clientTickets = $client->tickets()->latest()->take(10)->get();
                        @endphp
                        @if($clientTickets->count() > 0)
                            <div class="mt-4 divide-y divide-gray-200">
                                @foreach($clientTickets as $ticket)
                                    <div class="py-3 flex items-center justify-between">
                                        <div>
                                            <a href="{{ route('tickets.show', $ticket) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">{{ $ticket->ticket_number }}</a>
                                            <p class="text-sm text-gray-900">{{ Str::limit($ticket->subject, 60) }}</p>
                                        </div>
                                        <x-badge :type="$ticket->status">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</x-badge>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="mt-4 text-sm text-gray-500">{{ __('No tickets yet for this client.') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
