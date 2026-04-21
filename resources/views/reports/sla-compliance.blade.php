<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('SLA Compliance Report') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
            <!-- Date Filter -->
            <div class="mb-6 overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                <form method="GET" class="flex flex-wrap items-end gap-4">
                    <div>
                        <label for="from" class="block text-sm font-medium text-gray-700">{{ __('From') }}</label>
                        <input type="date" name="from" id="from" value="{{ $report['from'] }}" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <div>
                        <label for="to" class="block text-sm font-medium text-gray-700">{{ __('To') }}</label>
                        <input type="date" name="to" id="to" value="{{ $report['to'] }}" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Filter') }}</button>
                    @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::DetailedReporting))
                        <a href="{{ route('reports.export.sla-compliance', ['from' => $report['from'], 'to' => $report['to']]) }}" class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 8.25H7.5a2.25 2.25 0 0 0-2.25 2.25v9a2.25 2.25 0 0 0 2.25 2.25h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25H15M9 12l3 3m0 0 3-3m-3 3V2.25" />
                            </svg>
                            {{ __('Export CSV') }}
                        </a>
                    @endif
                </form>
            </div>

            {{-- SLA definitions --}}
            <div class="mb-6 rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                <div class="flex items-start gap-3">
                    <svg class="h-5 w-5 text-indigo-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                    </svg>
                    <div class="text-sm text-indigo-900 space-y-2">
                        <p><span class="font-semibold">{{ __('Response Time') }}:</span> {{ __('measured from ticket creation until the ticket is first moved to In Progress.') }}</p>
                        <p><span class="font-semibold">{{ __('Resolution Time') }}:</span> {{ __('measured from ticket creation until the ticket is Closed.') }}</p>
                        <p class="text-xs text-indigo-700">{{ __('A ticket is counted as "Met" when it hits the milestone within the policy\'s target hours for its priority + client tier.') }}</p>
                    </div>
                </div>
            </div>

            {{-- Averages --}}
            <div class="grid gap-6 sm:grid-cols-2 mb-6">
                <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-gray-500">{{ __('Average Response Time') }}</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">
                        {{ $report['avg_response_hours'] !== null ? $report['avg_response_hours'].'h' : '—' }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('From ticket creation to first In Progress transition.') }}</p>
                </div>
                <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-gray-500">{{ __('Average Resolution Time') }}</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">
                        {{ $report['avg_resolution_hours'] !== null ? $report['avg_resolution_hours'].'h' : '—' }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('From ticket creation to Closed.') }}</p>
                </div>
            </div>

            <!-- Response SLA -->
            <div class="grid gap-6 sm:grid-cols-2 mb-6">
                <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Response SLA') }}</h3>
                    <div class="mt-4 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">{{ __('Met') }}</span>
                            <span class="text-sm font-semibold text-green-600">{{ $report['response_met'] }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">{{ __('Missed') }}</span>
                            <span class="text-sm font-semibold text-red-600">{{ $report['response_missed'] }}</span>
                        </div>
                        @if($report['response_met'] + $report['response_missed'] > 0)
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="bg-green-500 h-3 rounded-full" style="width: {{ $report['response_compliance'] }}%"></div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Resolution SLA') }}</h3>
                    <div class="mt-4 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">{{ __('Met') }}</span>
                            <span class="text-sm font-semibold text-green-600">{{ $report['resolution_met'] }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">{{ __('Missed') }}</span>
                            <span class="text-sm font-semibold text-red-600">{{ $report['resolution_missed'] }}</span>
                        </div>
                        @if($report['resolution_met'] + $report['resolution_missed'] > 0)
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="bg-green-500 h-3 rounded-full" style="width: {{ $report['resolution_compliance'] }}%"></div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Breakdowns: by priority & by tier --}}
            <div class="grid gap-6 lg:grid-cols-2 mb-6">
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('Compliance by Priority') }}</h3>
                    @if($report['by_priority']->isEmpty())
                        <p class="mt-3 text-sm text-gray-500">{{ __('No tickets in range.') }}</p>
                    @else
                        <table class="mt-3 min-w-full text-sm">
                            <thead class="text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="py-1 text-left">{{ __('Priority') }}</th>
                                    <th class="py-1 text-right">{{ __('Tickets') }}</th>
                                    <th class="py-1 text-right">{{ __('Response %') }}</th>
                                    <th class="py-1 text-right">{{ __('Resolution %') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach(['critical','high','medium','low'] as $prio)
                                    @continue(! isset($report['by_priority'][$prio]))
                                    @php $row = $report['by_priority'][$prio]; @endphp
                                    <tr>
                                        <td class="py-1.5"><x-badge :type="$prio">{{ ucfirst($prio) }}</x-badge></td>
                                        <td class="py-1.5 text-right">{{ $row['count'] }}</td>
                                        <td class="py-1.5 text-right {{ ($row['response_rate'] ?? 100) >= 90 ? 'text-green-600' : (($row['response_rate'] ?? 0) >= 70 ? 'text-yellow-600' : 'text-red-600') }}">
                                            {{ $row['response_rate'] ?? '—' }}{{ $row['response_rate'] !== null ? '%' : '' }}
                                        </td>
                                        <td class="py-1.5 text-right {{ ($row['resolution_rate'] ?? 100) >= 90 ? 'text-green-600' : (($row['resolution_rate'] ?? 0) >= 70 ? 'text-yellow-600' : 'text-red-600') }}">
                                            {{ $row['resolution_rate'] ?? '—' }}{{ $row['resolution_rate'] !== null ? '%' : '' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('Compliance by Client Tier') }}</h3>
                    @if($report['by_tier']->isEmpty())
                        <p class="mt-3 text-sm text-gray-500">{{ __('No tickets in range.') }}</p>
                    @else
                        <table class="mt-3 min-w-full text-sm">
                            <thead class="text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="py-1 text-left">{{ __('Tier') }}</th>
                                    <th class="py-1 text-right">{{ __('Tickets') }}</th>
                                    <th class="py-1 text-right">{{ __('Response %') }}</th>
                                    <th class="py-1 text-right">{{ __('Resolution %') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach(['enterprise','premium','basic'] as $tier)
                                    @continue(! isset($report['by_tier'][$tier]))
                                    @php $row = $report['by_tier'][$tier]; @endphp
                                    <tr>
                                        <td class="py-1.5 capitalize">{{ $tier }}</td>
                                        <td class="py-1.5 text-right">{{ $row['count'] }}</td>
                                        <td class="py-1.5 text-right {{ ($row['response_rate'] ?? 100) >= 90 ? 'text-green-600' : (($row['response_rate'] ?? 0) >= 70 ? 'text-yellow-600' : 'text-red-600') }}">
                                            {{ $row['response_rate'] ?? '—' }}{{ $row['response_rate'] !== null ? '%' : '' }}
                                        </td>
                                        <td class="py-1.5 text-right {{ ($row['resolution_rate'] ?? 100) >= 90 ? 'text-green-600' : (($row['resolution_rate'] ?? 0) >= 70 ? 'text-yellow-600' : 'text-red-600') }}">
                                            {{ $row['resolution_rate'] ?? '—' }}{{ $row['resolution_rate'] !== null ? '%' : '' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            {{-- Per-ticket detail --}}
            <div class="overflow-hidden rounded-xl bg-white shadow-sm mb-6">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('Per-Ticket Detail') }}</h3>
                    <span class="text-xs text-gray-500">{{ $report['rows']->count() }} {{ __('tickets') }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs font-medium uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-left">{{ __('Ticket') }}</th>
                                <th class="px-4 py-2 text-left">{{ __('Client / Tier') }}</th>
                                <th class="px-4 py-2 text-left">{{ __('Priority') }}</th>
                                <th class="px-4 py-2 text-left">{{ __('Policy') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Response') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Resolution') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($report['rows'] as $row)
                                @php $t = $row['ticket']; @endphp
                                <tr>
                                    <td class="px-4 py-2 align-top">
                                        <a href="{{ route('tickets.show', $t) }}" class="font-medium text-indigo-600 hover:text-indigo-500">{{ $t->ticket_number }}</a>
                                        <div class="text-xs text-gray-500 truncate max-w-[220px]">{{ $t->subject }}</div>
                                    </td>
                                    <td class="px-4 py-2 align-top">
                                        <div class="text-gray-900">{{ $t->client?->name ?? '-' }}</div>
                                        <div class="text-[11px] uppercase text-gray-500">{{ $row['client_tier'] ?? '-' }}</div>
                                    </td>
                                    <td class="px-4 py-2 align-top"><x-badge :type="$row['priority']">{{ ucfirst($row['priority']) }}</x-badge></td>
                                    <td class="px-4 py-2 align-top text-xs text-gray-600">{{ $row['policy_name'] ?? '-' }}</td>
                                    <td class="px-4 py-2 align-top text-right">
                                        <div class="text-gray-900">{{ $row['response_hours'] !== null ? $row['response_hours'].'h' : '—' }} <span class="text-xs text-gray-500">/ {{ $row['response_target'] ?? '—' }}h</span></div>
                                        @if($row['response_status'] === 'met')
                                            <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-medium text-green-700">{{ __('Met') }}</span>
                                        @elseif($row['response_status'] === 'missed')
                                            <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-medium text-red-700">{{ __('Missed') }}</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600">{{ __('N/A') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 align-top text-right">
                                        <div class="text-gray-900">{{ $row['resolution_hours'] !== null ? $row['resolution_hours'].'h' : '—' }} <span class="text-xs text-gray-500">/ {{ $row['resolution_target'] ?? '—' }}h</span></div>
                                        @if($row['resolution_status'] === 'met')
                                            <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-medium text-green-700">{{ __('Met') }}</span>
                                        @elseif($row['resolution_status'] === 'missed')
                                            <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-medium text-red-700">{{ __('Missed') }}</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600">{{ __('N/A') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">{{ __('No closed tickets with SLA data in this range.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-end">
                <a href="{{ route('reports.overview') }}" class="text-sm text-indigo-600 hover:text-indigo-900">{{ __('Back to Reports') }}</a>
            </div>
        </div>
    </div>
</x-app-layout>
