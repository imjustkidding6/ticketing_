{{--
    SLA panel. Read-only: the deadlines it prints are computed, stored, judged for
    compliance and used to fire breach warnings elsewhere — this only shows them.

    Two rows, not one combined block, because response and resolution breach
    separately ("two clocks, not one", the language the SLA tour already uses) and
    because commit 3 will give each row its own clock label.

    No date arithmetic lives here. Absolute instants go through TenantTime::format();
    durations are differences between two instants; the paused projection comes from
    the model accessors so it stays pinned to SlaService::extendForHold().
--}}
@if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::SlaManagement))
    @php
        $policy = $ticket->slaPolicy;

        // Each target names its own clock, from the policy. Said out loud beside the
        // deadline because the loudest complaint about this feature elsewhere is an
        // admin who believed one mode and got the other.
        $responseClock = $policy?->response_hours_mode ?? 'calendar';
        $resolutionClock = $policy?->resolution_hours_mode ?? 'calendar';

        // The guard is on the DUE DATES, per row — not on the policy id. A policy
        // with null deadlines is a real older-row state, and letting it reach the
        // countdown branch prints "in -55 years".
        $hasResponseDue = $policy && $ticket->response_due_at;
        $hasResolutionDue = $policy && $ticket->resolution_due_at;

        $isPaused = in_array($ticket->status, \App\Models\Ticket::PAUSED_STATUSES, true);
        $holdMinutes = $ticket->getTotalHoldTimeMinutes();

        // Verdicts mirror SlaService::getComplianceReport() exactly, per clock:
        // response judged RAW (created_at -> first_response_at), resolution judged
        // on the hold-excluding effective figure. Judging one way and printing the
        // other is the "10h / 4h target beside a green MET badge" defect.
        // ...and raw in the clock the target is stated in, matching getComplianceReport()
        // exactly. Printing wall-clock hours beside a business-hours target is the same
        // "10h / 4h target, green MET badge" defect wearing a different hat.
        $responseHours = $ticket->first_response_at
            ? ($responseClock === 'business'
                ? app(\App\Services\SlaService::class)->clockFor('business')->businessHoursBetween($ticket->created_at, $ticket->first_response_at)
                : round($ticket->created_at->diffInHours($ticket->first_response_at, false), 2))
            : null;
        $resolutionHours = $ticket->getEffectiveResolutionTimeHours();
    @endphp

    <div class="rounded-xl bg-white p-6 shadow-sm">
        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('SLA') }}</h4>

        @if(! $hasResponseDue && ! $hasResolutionDue)
            <p class="mt-4 text-sm text-gray-500">
                <span class="font-medium text-gray-700">{{ __('No SLA policy applied.') }}</span>
                {{ __('This ticket had no matching policy for its priority and client tier when it was created, so it has no response or resolution target and is excluded from SLA compliance.') }}
            </p>
            @can('update sla policies')
                <a href="{{ route('sla.index') }}" class="mt-3 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-500">{{ __('Set targets for this tier') }}</a>
            @endcan
        @else
            <dl class="mt-4 space-y-4 text-sm">
                @foreach([
                    ['key' => 'response', 'label' => __('Response'), 'has' => $hasResponseDue, 'due' => $ticket->response_due_at, 'projected' => $ticket->projectedResponseDueAt(), 'done_at' => $ticket->first_response_at, 'done_label' => __('Responded'), 'hours' => $responseHours, 'target' => $policy?->response_time_hours, 'clock' => $responseClock],
                    ['key' => 'resolution', 'label' => __('Resolution'), 'has' => $hasResolutionDue, 'due' => $ticket->resolution_due_at, 'projected' => $ticket->projectedResolutionDueAt(), 'done_at' => $ticket->closed_at, 'done_label' => __('Closed'), 'hours' => $resolutionHours, 'target' => $policy?->resolution_time_hours, 'clock' => $resolutionClock],
                ] as $row)
                    <div>
                        <dt class="flex items-center gap-2 font-medium text-gray-700">
                            {{ $row['label'] }}
                            @if($row['has'] && ! $row['done_at'] && $isPaused)
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ __('Paused') }}</span>
                            @endif
                        </dt>
                        <dd class="mt-1 text-gray-600">
                            @if(! $row['has'])
                                {{-- Per-row null: one clock may have a deadline while the other does not. --}}
                                <span class="text-gray-500">{{ __('No target recorded for this clock.') }}</span>
                            @elseif($row['done_at'])
                                <span title="{{ \App\Support\TenantTime::format($row['done_at']) }}">{{ $row['done_label'] }} {{ \App\Support\TenantTime::format($row['done_at']) }}</span>
                                @if($row['hours'] !== null && $row['target'] !== null)
                                    @php $met = $row['hours'] <= $row['target']; @endphp
                                    <span class="ml-1 rounded-full px-2 py-0.5 text-xs font-medium {{ $met ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $met ? __('Met') : __('Missed') }}
                                    </span>
                                    <span class="block text-xs text-gray-500">{{ \App\Models\Ticket::formatHours($row['hours']) }} {{ __('of') }} {{ $row['target'] }}h</span>
                                @endif
                            @elseif($isPaused && $row['projected'] && ! $row['projected']->eq($row['due']))
                                {{-- Paused with a running hold: no countdown at all. The stored deadline is
                                     knowingly early by the running segment, and a number wrong by a growing
                                     amount is worse than no number. --}}
                                <span title="{{ \App\Support\TenantTime::format($row['projected']) }}">{{ __('Projected:') }} {{ \App\Support\TenantTime::format($row['projected']) }}</span>
                                <span class="block text-xs text-gray-500">{{ __('moves out for as long as this ticket stays paused') }}</span>
                            @elseif($isPaused)
                                {{-- Paused but nothing to project from (no hold_started_at): show the stored
                                     deadline rather than invent one. Still no countdown. --}}
                                <span title="{{ \App\Support\TenantTime::format($row['due']) }}">{{ __('Due:') }} {{ \App\Support\TenantTime::format($row['due']) }}</span>
                            @else
                                @php
                                    $remaining = (int) round(now()->diffInMinutes($row['due'], false));
                                @endphp
                                <span title="{{ \App\Support\TenantTime::format($row['due']) }}">{{ \App\Support\TenantTime::format($row['due']) }}</span>
                                <span class="block text-xs {{ $remaining < 0 ? 'text-red-600' : 'text-gray-500' }}">
                                    @if($remaining < 0)
                                        {{ __('overdue by') }} {{ \App\Models\Ticket::formatHours(abs($remaining) / 60) }}
                                    @else
                                        {{ __('in') }} {{ \App\Models\Ticket::formatHours($remaining / 60) }}
                                    @endif
                                </span>
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>

            @if($isPaused)
                <p class="mt-4 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    @if($holdMinutes > 0)
                        {{ __('On hold for') }} {{ \App\Models\Ticket::formatHours($holdMinutes / 60) }} {{ __('so far.') }}
                    @endif
                    {{ __('The clock is paused. This ticket is excluded from breach alerts until the hold ends.') }}
                </p>
            @endif

            @if($policy)
                <p class="mt-4 border-t border-gray-100 pt-3 text-xs text-gray-500">
                    {{ $policy->name }}
                    <span class="block">
                        {{ $policy->response_time_hours }}h {{ __('response') }} ({{ __($responseClock.' hours') }})
                        / {{ $policy->resolution_time_hours }}h {{ __('resolution') }} ({{ __($resolutionClock.' hours') }})
                    </span>
                </p>
            @endif
        @endif
    </div>
@endif
