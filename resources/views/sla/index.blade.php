<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('SLA Policies') }}</h2>
            @if(!$hasAny)
                <form method="POST" action="{{ route('sla.seed-defaults') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        {{ __('Seed standard policies') }}
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('sla.seed-defaults') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                        {{ __('Fill missing with defaults') }}
                    </button>
                </form>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800">{{ session('success') }}</div>
            @endif

            <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900 space-y-1">
                <p>{{ __('Policies are grouped by client tier. Each tier has one row per priority.') }}</p>
                <p><span class="font-semibold">{{ __('Response hours') }}:</span> {{ __('target time from ticket creation until it is first moved to In Progress.') }}</p>
                <p><span class="font-semibold">{{ __('Resolution hours') }}:</span> {{ __('target time from ticket creation until it is Closed.') }}</p>
            </div>

            @foreach($tiers as $tier)
                @php $tierRows = $grouped[$tier]; $hasRows = collect($tierRows)->filter()->isNotEmpty(); @endphp
                <div class="rounded-xl bg-white shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ ucfirst($tier) }}</h3>
                            <p class="text-xs text-gray-500">{{ __('Applies to clients tagged as :tier.', ['tier' => $tier]) }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('sla.edit-tier', $tier) }}" class="inline-flex items-center gap-1.5 rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">
                                @if($hasRows)
                                    {{ __('Edit policy') }}
                                @else
                                    {{ __('Create policy') }}
                                @endif
                            </a>
                            @if($hasRows)
                                <form method="POST" action="{{ route('sla.destroy-tier', $tier) }}" onsubmit="return confirm('{{ __('Remove all :tier policies?', ['tier' => ucfirst($tier)]) }}')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                                        {{ __('Remove') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    @if(!$hasRows)
                        <div class="px-6 py-8 text-center text-sm text-gray-500">
                            {{ __('No policy yet for :tier. Create one to define response + resolution times per priority.', ['tier' => $tier]) }}
                        </div>
                    @else
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 text-xs font-medium uppercase tracking-wider text-gray-500">
                                <tr>
                                    <th class="px-6 py-2 text-left">{{ __('Priority') }}</th>
                                    <th class="px-6 py-2 text-right">{{ __('Response') }}</th>
                                    <th class="px-6 py-2 text-right">{{ __('Resolution') }}</th>
                                    <th class="px-6 py-2 text-center">{{ __('Active') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-sm">
                                @foreach($priorities as $priority)
                                    @php $p = $tierRows[$priority]; @endphp
                                    <tr>
                                        <td class="px-6 py-2">
                                            <x-badge :type="$priority">{{ ucfirst($priority) }}</x-badge>
                                        </td>
                                        <td class="px-6 py-2 text-right {{ $p ? 'text-gray-900' : 'text-gray-400' }}">
                                            {{ $p ? $p->response_time_hours.'h' : '—' }}
                                        </td>
                                        <td class="px-6 py-2 text-right {{ $p ? 'text-gray-900' : 'text-gray-400' }}">
                                            {{ $p ? $p->resolution_time_hours.'h' : '—' }}
                                        </td>
                                        <td class="px-6 py-2 text-center">
                                            @if($p && $p->is_active)
                                                <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-medium text-green-800">{{ __('Active') }}</span>
                                            @elseif($p)
                                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600">{{ __('Paused') }}</span>
                                            @else
                                                <span class="text-[10px] text-gray-400">{{ __('Not set') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
