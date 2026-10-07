@php
    // Every control's target and permission, computed here exactly as $analyzeCanComment /
    // $analyzeCanTasks are in the Analyze panel. A control whose target is not on the page
    // or whose endpoint would 403 must not render: the verdict is written when the copy
    // happens, before the owning form submits, so a dead control banks minutes_saved for
    // work that never happened — a false row in the table the pilot's metric comes from.
    $resTenant = $ticket->tenant;
    $resUser = Auth::user();
    $resOwner = $resTenant !== null && ($resUser?->isOwnerOf($resTenant) ?? false);
    $resCan = fn (string $permission) => $resOwner || ($resUser?->can($permission) ?? false);

    // The comment form renders only on a ticket that is neither closed nor cancelled, and
    // this panel deliberately renders on a closed ticket too: writing one up read-only is
    // exactly what you want after the fact.
    $resCommentForm = ! in_array($ticket->status, ['closed', 'cancelled'], true);
    $resCanComment = $resCommentForm && $resCan('create ticket comments');
    $resCanInternal = $resCanComment && $resCan('create internal notes');
    // A public reply to a customer needs the Enterprise client_comments feature, which is
    // what the comment routes themselves carry.
    $resCanPublic = $resCanComment
        && app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::ClientComments);
    // #closing_remarks only exists while the ticket is not already closed, and
    // TicketController::changeStatus() checks `update tickets`.
    $resCanRemarks = $ticket->status !== 'closed' && $resCan('update tickets');

    // NOTE: the design's amber "submitted through the public portal" warning is NOT here.
    // `tickets.client_id` is NOT NULL (create_tickets_table:24) and every write path supplies
    // a client, so `client_id === null` can never render — and unreachable UI is exactly what
    // the rest of this panel refuses to emit. The *prompt* half of that branch is kept and is
    // pinned directly in Tests\Unit\ResolutionWriterServiceTest; see follow-up 34.

    // "Pull in my notes" is CLIENT-SIDE and is the engineer's own act: this is material
    // already rendered on their screen, which they may edit or delete before pressing the
    // button. The SERVER never reads non-public comments — see ResolutionWriterService.
    // $ticket->comments is loaded only for a role holding `view ticket comments`, so this
    // inherits that grant rather than widening it, and nothing is re-queried here.
    $resLocalNotes = $ticket->comments
        ->where('type', 'internal')
        ->pluck('content')
        ->filter()
        ->values()
        ->merge(
            $ticket->tasks
                ->where('status', 'completed')
                ->pluck('description')
                ->filter()
                ->map(fn ($d) => __('Done: ').$d)
        )
        ->values();
@endphp
{{--
    GENERATE RESOLUTION (A2-1) — the proposal's second AI action.

    The engineer types rough notes, presses the button, and gets two drafts from one call:
    a client-friendly resolution and a standardised internal technical note. Nothing is
    applied and nothing is submitted by this panel.

    **The client-facing boundary is the point of this panel.** The fetch fills nothing. A
    public reply takes TWO deliberate human actions: the Use-as-public-reply click reveals
    a confirm line, and only the confirm click fills the comment box and sets its type
    select to Public. It never submits — the engineer's own Add Comment press is the second
    action, and it is the only thing that creates a customer-visible row. The internal copy
    forces the type select to Internal, byte-identically to the Analyze panel's summary
    copy. The remarks copy fills the close form's box and never submits, so closing the
    ticket stays the engineer's own Update Status press.

    Both drafts are editable in place, so an edit produces a genuine `edited` verdict from
    the server's hash comparison rather than a tautological `accepted`.

    A closed tab is not a rejection: only Dismiss records `rejected`. A draft read and
    never used records no verdict at all — see follow-up 23 on the four populations.

    Styling follows the Analyze panel above it. This page carries no dark-mode variants, so
    neither does this.
--}}
<div x-data="ticketAiResolution()" class="mb-4 rounded-lg border border-indigo-100 bg-indigo-50/60 p-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <span class="text-xs font-semibold text-indigo-700">{{ __('Generate resolution') }}</span>
            <p class="mt-0.5 text-[11px] text-indigo-600/80">{{ __('Write what you did in rough. The AI writes it up twice — for the client, and for the record. You decide what is sent.') }}</p>
        </div>
        <button type="button" @click="generate()" :disabled="loading || notes.trim().length < minNotes"
                class="inline-flex items-center gap-1 rounded-md bg-white px-2.5 py-1 text-xs font-medium text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-50 disabled:opacity-50">
            <span x-show="!loading">{{ __('Generate') }}</span>
            <span x-show="loading" x-cloak>{{ __('Writing...') }}</span>
        </button>
    </div>

    <div class="mt-2">
        <label for="ai_resolution_notes" class="sr-only">{{ __('Your rough notes') }}</label>
        <textarea id="ai_resolution_notes" x-model="notes" rows="3" maxlength="{{ (int) config('ai.resolution.notes_chars', 4000) }}"
                  class="block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  placeholder="{{ __('e.g. Replaced the faulty network cable at the wall port, reinstalled the printer driver, printed a test page with the client watching.') }}"></textarea>
        <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
            <p class="text-[11px] text-indigo-600/80" x-show="notes.trim().length < minNotes" x-cloak>
                {{ __('Write a few lines about what you did — the AI rewrites your notes, it cannot invent the work.') }}
            </p>
            @if($resLocalNotes->isNotEmpty())
                <button type="button" @click="pullNotes()" class="rounded-md px-2 py-0.5 text-[11px] font-medium text-indigo-700 hover:underline">
                    {{ __('Pull in my notes') }}
                </button>
            @endif
        </div>
    </div>

    <p x-show="error" x-cloak class="mt-2 rounded-md bg-white p-2 text-xs text-red-600 ring-1 ring-red-100" x-text="error"></p>

    <div x-show="result" x-cloak class="mt-3 flex flex-col gap-2">
        {{-- The client-facing draft. Read-only until the engineer edits it; never sent from here. --}}
        <template x-if="result?.client_resolution">
            <div class="rounded-md bg-white p-2.5 ring-1 ring-gray-200">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ __('For the client') }} &middot; {{ __('not sent') }}</span>
                    <div class="flex items-center gap-1.5" x-show="!decided.client_resolution">
                        <button type="button" @click="startEdit('client_resolution')" x-show="!editing.client_resolution" class="rounded-md px-2 py-0.5 text-[11px] font-medium text-indigo-700 hover:underline">{{ __('Edit') }}</button>
                        <button type="button" @click="dismiss('client_resolution')" x-show="!editing.client_resolution" class="rounded-md px-2 py-0.5 text-[11px] font-medium text-gray-500 hover:underline">{{ __('Dismiss') }}</button>
                        <button type="button" @click="cancelEdit('client_resolution')" x-show="editing.client_resolution" x-cloak class="rounded-md px-2 py-0.5 text-[11px] font-medium text-gray-500 hover:underline">{{ __('Cancel') }}</button>
                    </div>
                    <span x-show="decided.client_resolution" x-cloak class="text-[11px] font-medium text-gray-500" x-text="decidedLabel('client_resolution')"></span>
                </div>
                <p x-show="!editing.client_resolution" class="mt-1.5 whitespace-pre-line text-xs text-gray-700" x-text="result.client_resolution"></p>
                <textarea x-show="editing.client_resolution" x-cloak x-model="drafts.client_resolution" rows="6" maxlength="{{ (int) config('ai.resolution.client_chars', 2000) }}"
                          class="mt-1.5 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>

                <div class="mt-2 flex flex-wrap items-center gap-1.5" x-show="!decided.client_resolution">
                    @if($resCanPublic)
                        {{--
                            TWO deliberate human actions. The first click only reveals the
                            confirm line below; the confirm click fills the box and sets the
                            type select to Public, and NEVER submits the form.
                        --}}
                        <button type="button" @click="confirmingPublic = true" x-show="!confirmingPublic"
                                class="rounded-md bg-white px-2 py-0.5 text-[11px] font-medium text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-50">{{ __('Use as public reply') }}</button>
                    @endif
                    @if($resCanRemarks)
                        <button type="button" @click="useAsRemarks()" class="rounded-md bg-white px-2 py-0.5 text-[11px] font-medium text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-50">{{ __('Copy to final remarks') }}</button>
                    @endif
                </div>

                @if($resCanPublic)
                    <div x-show="confirmingPublic" x-cloak class="mt-2 rounded-md bg-amber-50 p-2 ring-1 ring-amber-200">
                        <p class="text-[11px] text-amber-800">{{ __('This will be visible to the customer once you press Add Comment. Nothing is sent until you do.') }}</p>
                        <div class="mt-1.5 flex items-center gap-1.5">
                            <button type="button" @click="useAsPublicReply()" class="rounded-md bg-white px-2 py-0.5 text-[11px] font-medium text-amber-800 ring-1 ring-amber-300 hover:bg-amber-100">{{ __('Put it in the reply box') }}</button>
                            <button type="button" @click="confirmingPublic = false" class="rounded-md px-2 py-0.5 text-[11px] font-medium text-gray-500 hover:underline">{{ __('Cancel') }}</button>
                        </div>
                    </div>
                @endif
            </div>
        </template>

        {{-- The internal technical note: four fixed headings, so the resolution history is consistent. --}}
        <template x-if="result?.internal_note">
            <div class="rounded-md bg-white p-2.5 ring-1 ring-gray-200">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ __('For the record') }} &middot; {{ __('internal') }}</span>
                    <div class="flex items-center gap-1.5" x-show="!decided.internal_note">
                        <button type="button" @click="startEdit('internal_note')" x-show="!editing.internal_note" class="rounded-md px-2 py-0.5 text-[11px] font-medium text-indigo-700 hover:underline">{{ __('Edit') }}</button>
                        <button type="button" @click="dismiss('internal_note')" x-show="!editing.internal_note" class="rounded-md px-2 py-0.5 text-[11px] font-medium text-gray-500 hover:underline">{{ __('Dismiss') }}</button>
                        <button type="button" @click="cancelEdit('internal_note')" x-show="editing.internal_note" x-cloak class="rounded-md px-2 py-0.5 text-[11px] font-medium text-gray-500 hover:underline">{{ __('Cancel') }}</button>
                    </div>
                    <span x-show="decided.internal_note" x-cloak class="text-[11px] font-medium text-gray-500" x-text="decidedLabel('internal_note')"></span>
                </div>
                <p x-show="!editing.internal_note" class="mt-1.5 whitespace-pre-line text-xs text-gray-700" x-text="result.internal_note"></p>
                <textarea x-show="editing.internal_note" x-cloak x-model="drafts.internal_note" rows="8" maxlength="{{ (int) config('ai.resolution.internal_chars', 2500) }}"
                          class="mt-1.5 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>

                @if($resCanInternal)
                    <div class="mt-2" x-show="!decided.internal_note">
                        <button type="button" @click="useAsInternalNote()" class="rounded-md bg-white px-2 py-0.5 text-[11px] font-medium text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-50">{{ __('Copy to internal note') }}</button>
                    </div>
                @endif
            </div>
        </template>

        <p x-show="result && !result.client_resolution && !result.internal_note" x-cloak class="text-[11px] text-gray-500">
            {{ __('The AI returned nothing usable for this write-up.') }}
        </p>
    </div>
</div>

<script>
    function ticketAiResolution() {
        return {
            loading: false,
            error: '',
            notes: '',
            result: null,
            decided: {},
            editing: {},
            drafts: {},
            // The confirm step for the one client-facing control. False again after every
            // generate, so a fresh draft never arrives mid-confirmation.
            confirmingPublic: false,
            minNotes: {{ (int) config('ai.resolution.notes_min_chars', 40) }},
            canComment: @js($resCanComment),
            canInternal: @js($resCanInternal),
            canPublic: @js($resCanPublic),
            canRemarks: @js($resCanRemarks),
            localNotes: @js($resLocalNotes),
            draftUrl: '{{ route('tickets.ai.resolution', $ticket) }}',
            // Built from the named route with a placeholder id, so the tenant prefix comes
            // from SetTenantUrlDefaults rather than being re-assembled by hand.
            verdictUrlTemplate: '{{ route('tickets.ai.analysis-verdict', ['analysis' => '__ID__']) }}',
            csrf: document.querySelector('meta[name="csrf-token"]')?.content || '',
            decidedLabel(type) {
                return { accepted: @js(__('Copied')), edited: @js(__('Edited')), rejected: @js(__('Dismissed')) }[this.decided[type]] || '';
            },
            // The engineer's own act: material already on their screen, moved into a box
            // they can edit before they press Generate. The server never reads it.
            pullNotes() {
                const extra = (this.localNotes || []).join('\n');
                if (extra === '') return;
                this.notes = this.notes.trim() === '' ? extra : (this.notes.trim() + '\n' + extra);
            },
            async generate() {
                this.error = ''; this.loading = true; this.result = null;
                this.decided = {}; this.editing = {}; this.drafts = {}; this.confirmingPublic = false;
                // The comment-type select is SHARED state and this panel is the only control
                // that writes it. Reset it here so an abandoned confirm cannot leave the form
                // on Public for whatever the engineer does next — the panel fails safe on its
                // own rather than relying on the next control to tidy up. (The select's own
                // default is pre-existing and is A2-2's to change, not A2-1's.)
                const sharedType = document.querySelector('#add-comment-form select[name=\"type\"]');
                if (sharedType) sharedType.value = 'internal';
                const body = new FormData();
                body.append('notes', this.notes);
                try {
                    const res = await fetch(this.draftUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                        body,
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) throw new Error(data.error || (data.errors?.notes?.[0]) || @js(__('The AI request failed.')));
                    this.result = data;
                } catch (e) {
                    this.error = e.message;
                } finally {
                    this.loading = false;
                }
            },
            startEdit(type) { this.drafts[type] = this.result[type]; this.editing[type] = true; },
            cancelEdit(type) { this.editing[type] = false; },
            // What was copied, edited or not — that is the whole input to the accept-vs-edit
            // metric. The verdict WORD is not ours to choose between accepted and edited:
            // the server hashes what we copied against what it stored.
            textFor(type) {
                return this.editing[type] ? (this.drafts[type] ?? '') : (this.result[type] ?? '');
            },
            async decide(type, verdict, appliedValue) {
                if (!this.result?.analysis_id) return false;
                const body = new FormData();
                body.append('suggestion_type', type);
                body.append('verdict', verdict);
                if (appliedValue !== null && appliedValue !== undefined) body.append('applied_value', appliedValue);
                try {
                    const res = await fetch(this.verdictUrlTemplate.replace('__ID__', this.result.analysis_id), {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                        body,
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) throw new Error(data.error || @js(__('The AI request failed.')));
                    this.decided[type] = data.verdict;
                    return true;
                } catch (e) {
                    this.error = e.message;
                    return false;
                }
            },
            dismiss(type) { return this.decide(type, 'rejected', null); },
            // The SECOND half of the two-step confirm, reachable only from the confirm line.
            // It fills the box and sets the type select to Public, and it does NOT submit:
            // the engineer's own Add Comment press is the second deliberate action and the
            // only thing that creates a customer-visible row.
            // Records NOTHING when the box is absent — banking minutes_saved for a fill that
            // did not happen puts a false row in the table the pilot's metric is read from.
            async useAsPublicReply() {
                const ta = document.getElementById('comment_content');
                if (!this.canPublic || !ta) {
                    this.error = @js(__('A public reply is unavailable on this ticket, so nothing was copied.'));

                    return false;
                }
                const text = this.textFor('client_resolution');
                ta.value = text;
                const type = document.querySelector('#add-comment-form select[name="type"]');
                if (type) type.value = 'public';
                ta.focus();
                this.confirmingPublic = false;
                this.editing.client_resolution = false;

                return this.decide('client_resolution', 'accepted', text);
            },
            // Forces the type select to Internal, byte-identically to the Analyze panel's
            // summary copy.
            async useAsInternalNote() {
                const ta = document.getElementById('comment_content');
                if (!this.canInternal || !ta) {
                    this.error = @js(__('Add a comment is unavailable on this ticket, so the note was not copied.'));

                    return false;
                }
                const text = this.textFor('internal_note');
                ta.value = text;
                const type = document.querySelector('#add-comment-form select[name="type"]');
                if (type) type.value = 'internal';
                ta.focus();
                this.editing.internal_note = false;

                return this.decide('internal_note', 'accepted', text);
            },
            // Fills the close form's remarks box and does NOT submit: closing the ticket
            // stays the engineer's own Update Status press. The 2,000-character cap on the
            // draft IS that field's own max:2000, so the copy can never 422.
            async useAsRemarks() {
                const ta = document.getElementById('closing_remarks');
                if (!this.canRemarks || !ta) {
                    this.error = @js(__('The close form is unavailable on this ticket, so nothing was copied.'));

                    return false;
                }
                const text = this.textFor('client_resolution');
                ta.value = text;
                ta.dispatchEvent(new Event('input'));
                ta.focus();
                this.editing.client_resolution = false;

                return this.decide('client_resolution', 'accepted', text);
            },
        };
    }
</script>
