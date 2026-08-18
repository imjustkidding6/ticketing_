                    {{-- Status --}}
                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ __('Status') }}</h4>
                        @php
                            $currentTenant = \App\Models\Tenant::find(session('current_tenant_id'));
                            $isOwner = $currentTenant && auth()->user()?->isOwnerOf($currentTenant);
                            $canReopen = app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::TicketReopening)
                                && ($isOwner || auth()->user()?->can('reopen tickets'));
                            $statusLocked = $ticket->status === 'closed' && ! $canReopen;
                        @endphp
                        <div class="mt-4">
                            @if($ticket->status === 'closed')
                                {{-- Closed status badge --}}
                                <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                    <span class="inline-flex items-center gap-2">
                                        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                        </svg>
                                        {{ __('Closed') }}
                                    </span>
                                </div>
                                @if($ticket->closing_remarks)
                                    <div class="mt-2 rounded-md bg-gray-50 border border-gray-200 px-3 py-2">
                                        <p class="text-xs font-medium text-gray-500">{{ __('Final Remarks') }}</p>
                                        <p class="mt-1 text-sm text-gray-700">{{ $ticket->closing_remarks }}</p>
                                    </div>
                                @endif
                                @if($statusLocked)
                                    <p class="mt-2 text-xs text-gray-500">{{ __('Reopening closed tickets requires the Enterprise plan.') }}</p>
                                @endif
                            @else
                                {{-- Quick status update (one click; close/cancel stay in the dropdown below) --}}
                                @php
                                    $quickStatuses = ['open', 'assigned', 'in_progress'];
                                    if (app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::SlaManagement)) {
                                        $quickStatuses[] = 'on_hold';
                                    }
                                    $quickStatusStyles = [
                                        'open' => 'bg-slate-100 text-slate-700 hover:bg-slate-200',
                                        'assigned' => 'bg-blue-100 text-blue-700 hover:bg-blue-200',
                                        'in_progress' => 'bg-amber-100 text-amber-800 hover:bg-amber-200',
                                        'on_hold' => 'bg-purple-100 text-purple-700 hover:bg-purple-200',
                                    ];
                                @endphp
                                <div class="mb-3">
                                    <p class="mb-1.5 text-xs font-medium text-gray-500">{{ __('Quick update') }}</p>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($quickStatuses as $qs)
                                            @if($ticket->status === $qs)
                                                <span class="inline-flex items-center gap-1 rounded-md px-2.5 py-1.5 text-xs font-semibold ring-2 ring-indigo-400 {{ $quickStatusStyles[$qs] }}">
                                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                                    {{ ucfirst(str_replace('_', ' ', $qs)) }}
                                                </span>
                                            @else
                                                <form method="POST" action="{{ route('tickets.change-status', $ticket) }}">
                                                    @csrf
                                                    <input type="hidden" name="status" value="{{ $qs }}">
                                                    <button type="submit" class="rounded-md px-2.5 py-1.5 text-xs font-medium {{ $quickStatusStyles[$qs] }}">{{ ucfirst(str_replace('_', ' ', $qs)) }}</button>
                                                </form>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>

                                <form method="POST" action="{{ route('tickets.change-status', $ticket) }}" x-data="{ status: '{{ $ticket->status }}' }">
                                    @csrf
                                    <select name="status" id="sidebar_status" x-model="status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        @php
                                            $availableStatuses = ['open', 'assigned', 'in_progress', 'closed', 'cancelled'];
                                            if (app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::SlaManagement)) {
                                                $availableStatuses = ['open', 'assigned', 'in_progress', 'on_hold', 'closed', 'cancelled'];
                                            }
                                        @endphp
                                        @foreach($availableStatuses as $status)
                                            <option value="{{ $status }}" {{ $ticket->status === $status ? 'selected' : '' }}>
                                                {{ ucfirst(str_replace('_', ' ', $status)) }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <div x-show="status === 'closed'" x-cloak class="mt-3">
                                        <label for="closing_remarks" class="block text-xs font-medium text-gray-700">{{ __('Final Remarks') }} <span class="text-red-500">*</span></label>
                                        <textarea name="closing_remarks" id="closing_remarks" rows="3" :required="status === 'closed'" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="{{ __('Summarize the resolution. Shown on the service report PDF.') }}">{{ old('closing_remarks', $ticket->closing_remarks) }}</textarea>
                                        @error('closing_remarks') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>

                                    <button type="submit" class="mt-2 w-full rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Update Status') }}</button>
                                </form>
                            @endif

                            {{-- Reopen Ticket --}}
                            @if($canReopen && $ticket->status === 'closed')
                                <div x-data="{ showReopenModal: false }" class="mt-3 border-t border-gray-100 pt-3">
                                    <button type="button" @click="showReopenModal = true" class="w-full inline-flex items-center justify-center gap-2 rounded-md bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700 hover:bg-amber-100">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                        </svg>
                                        {{ __('Reopen Ticket') }}
                                    </button>
                                    @if($ticket->reopened_count > 0)
                                        <p class="mt-1 text-center text-xs text-gray-400">{{ __('Reopened') }} {{ $ticket->reopened_count }} {{ __('time(s)') }}</p>
                                    @endif

                                    {{-- Modal --}}
                                    <div x-show="showReopenModal" x-cloak
                                        x-transition.opacity
                                        class="fixed inset-0 z-50 flex items-center justify-center p-4"
                                        style="background-color: rgba(17,24,39,0.5);"
                                        @keydown.escape.window="showReopenModal = false"
                                        @click.self="showReopenModal = false">
                                        <div x-show="showReopenModal"
                                            x-transition:enter="ease-out duration-200"
                                            x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                                            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                            class="w-full max-w-md rounded-xl bg-white shadow-xl">
                                            <form method="POST" action="{{ route('tickets.reopen', $ticket) }}">
                                                @csrf
                                                <div class="p-6">
                                                    <div class="flex items-start gap-4">
                                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100">
                                                            <svg class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                                            </svg>
                                                        </div>
                                                        <div class="min-w-0 flex-1">
                                                            <h3 class="text-base font-semibold text-gray-900">{{ __('Reopen this ticket?') }}</h3>
                                                            <p class="mt-2 text-sm text-gray-600">
                                                                {{ __('Reopening moves the ticket back to') }} <span class="font-medium text-gray-900">{{ __('Open') }}</span>.
                                                                @if($ticket->reopened_count > 0)
                                                                    {{ __('Already reopened') }} <span class="font-medium">{{ $ticket->reopened_count }}</span> {{ __('time(s)') }}.
                                                                @endif
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="mt-4">
                                                        <label for="reopen_reason" class="block text-xs font-medium text-gray-700">{{ __('Reason for reopening') }} <span class="text-gray-400 font-normal">({{ __('optional but recommended') }})</span></label>
                                                        <textarea name="reason" id="reopen_reason" rows="2" maxlength="500" placeholder="{{ __('e.g. Issue not fully resolved, user reports recurrence, verification failed') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"></textarea>
                                                        <p class="mt-1 text-xs text-gray-500">{{ __('Shown on the ticket history and used in Reopen Analysis reports.') }}</p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center justify-end gap-2 rounded-b-xl border-t border-gray-100 bg-gray-50 px-6 py-3">
                                                    <button type="button" @click="showReopenModal = false" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                                                        {{ __('Cancel') }}
                                                    </button>
                                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-md bg-amber-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-amber-500">
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                                        </svg>
                                                        {{ __('Yes, Reopen Ticket') }}
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
