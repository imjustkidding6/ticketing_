@php
    // The categories the AI itself chose from — the ticket's own department, tenant-scoped
    // by BelongsToTenant. Rendered so "Edit & apply" can offer a different one without a
    // second endpoint and without the AI's opaque refs leaving the service: the engineer's
    // choice is an ordinary id that UpdateTicketRequest re-validates with tenantExists().
    $analyzeCategories = $ticket->department_id === null
        ? collect()
        : $ticket->department->categories()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

    // The comment form only renders on a ticket that is neither closed nor cancelled, and
    // this panel deliberately renders on closed tickets too. A control whose target is not
    // on the page must not exist, or clicking it banks a verdict for work that never
    // happened — which is a false row in the table the pilot's metric comes from.
    $analyzeCanComment = ! in_array($ticket->status, ['closed', 'cancelled'], true);

    // Suggestion 4 applies through the EXISTING tickets.tasks.polish-apply endpoint, which
    // requires `manage ticket tasks`. A control whose endpoint would 403 must not render —
    // the verdict is written before the form submits, so an Apply that cannot land would
    // bank minutes_saved for work that never happened.
    $analyzeTaskRole = optional(Auth::user())->roleInTenant($ticket->tenant);
    $analyzeCanTasks = $analyzeCanComment
        && ($analyzeTaskRole === 'owner' || (Auth::user()?->can('manage ticket tasks') ?? false));

    // polish-apply REPLACES every open task, so the ticket's current open tasks ride along
    // as untouched hidden rows. They are deliberately NOT part of the verdict's
    // applied_value: the metric asks what the engineer did with the AI's checks, and
    // folding pre-existing tasks in would report every apply as an edit.
    // P6b: the panel is HYDRATED from the newest stored analysis, so an agent opening a
    // publicly submitted ticket finds the suggestions already there without pressing
    // Analyze. Only a succeeded run is hydrated — a refusal or a failure has no
    // suggestions to show, and an empty panel is the honest render for it.
    $analyzeHydrated = ($latestAnalysis ?? null) !== null
        && $latestAnalysis->status === \App\Models\AiTicketAnalysis::STATUS_SUCCEEDED
            ? $latestAnalysis->toPanelArray()
            : null;

    $analyzeOpenTasks = $ticket->tasks
        ->whereIn('status', ['pending', 'in_progress'])
        ->pluck('description')
        ->filter()
        ->values();
@endphp
{{--
    ANALYZE TICKET (P6a-1).

    Every row is a suggestion with an explicit decision: **Apply**, **Edit & apply** or
    **Dismiss**. Nothing is applied by the AI — Apply records the verdict and then submits
    the ticket's OWN update form, so guardSlaPolicy() and the SlaService::assignSla()
    recompute happen exactly as they do for a manual edit. There is no AI write path to a
    ticket.

    **Edit & apply edits in place.** It was a bare link to the edit form, which posted no
    verdict at all — so an engineer who rewrote a suggestion and saved produced zero rows,
    and every Apply recorded `accepted` by construction. "Accept vs edit rate" is the
    pilot's headline metric, so the affordance has to send what was actually applied and
    let the server's hash comparison decide. Editing in place rather than carrying state
    across a navigation keeps that in one request and keeps the decision server-side.

    **The summary goes to an INTERNAL note, never the customer-visible reply.** Suggestion 6
    (draft acknowledgement) was excluded from this package precisely because it was the only
    client-facing output; a triage summary one click from a customer would reintroduce it.
    The button fills the note box and forces the type select to Internal.

    A closed tab is not a rejection: only Dismiss records `rejected`.

    Styling follows the copilot block below it (indigo-tinted card, xs type, white pill
    buttons). This page carries no dark-mode variants, so neither does this.
--}}
<div x-data="ticketAiAnalyze()" class="mb-4 rounded-lg border border-indigo-100 bg-indigo-50/60 p-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <span class="text-xs font-semibold text-indigo-700">{{ __('Analyze ticket') }}</span>
            <p class="mt-0.5 text-[11px] text-indigo-600/80">{{ __('Suggestions from this department\'s AI agents. You decide what is applied.') }}</p>
            {{--
                Who caused this analysis. An agent must never be unsure whether a human
                asked for it: an automatic run happened on submission, before anyone had
                read the ticket.
            --}}
            @if($analyzeHydrated === null)
                {{--
                    The honest empty state. The intake analysis runs on a QUEUE, so with no
                    worker on the `ai` queue this panel stays empty and nothing errors —
                    silence that reads as "the AI did nothing" when the truth is "nobody is
                    listening". It names the next action and the thing to check. The whole
                    panel is already gated on the plan, both switches and `use ai assistant`
                    by its include site, so this line cannot appear where it is noise.
                --}}
                <p class="mt-1 text-[11px] text-indigo-600/70" x-show="!result" x-cloak>
                    {{ __('No analysis yet. Automatic analysis on creation runs on a queue worker — if this stays empty, press Analyze, and check that a worker is running on the :queue queue.', ['queue' => 'ai']) }}
                </p>
            @endif
            {{-- Which agent judged it, read off the `agents` array the payload has carried
                 since P7 and that nothing rendered. NAMES ONLY — no score, no matched
                 skills, no role type, and `system_prompt` is not in the payload to leak. An
                 empty array renders nothing, which is the honest state: no agent judged
                 it. --}}
            <p x-show="result?.agents?.length" x-cloak class="mt-1 text-[11px] text-indigo-700/80">
                {{ __('Judged by:') }} <span x-text="result.agents.map((a) => a.name).filter(Boolean).join(', ')"></span>
            </p>
            @if($analyzeHydrated !== null)
                <p class="mt-1 inline-flex items-center gap-1 rounded-full bg-white px-2 py-0.5 text-[10px] font-medium text-indigo-700 ring-1 ring-indigo-200" x-show="!reanalyzed" x-cloak>
                    @if($latestAnalysis->trigger === \App\Models\AiTicketAnalysis::TRIGGER_AUTO)
                        {{ __('Analyzed automatically on submission') }} &middot; @localdt($latestAnalysis->created_at)
                    @elseif($latestAnalysis->trigger === \App\Models\AiTicketAnalysis::TRIGGER_INTAKE)
                        {{ __('Analyzed automatically on creation') }} &middot; @localdt($latestAnalysis->created_at)
                    @else
                        {{ __('Analyzed by :name', ['name' => $latestAnalysis->requestedBy?->name ?? __('a colleague')]) }} &middot; @localdt($latestAnalysis->created_at)
                    @endif
                </p>
            @endif
        </div>
        <button type="button" @click="analyze()" :disabled="loading"
                class="inline-flex items-center gap-1 rounded-md bg-white px-2.5 py-1 text-xs font-medium text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-50 disabled:opacity-50">
            <span x-show="!loading">{{ __('Analyze') }}</span>
            <span x-show="loading" x-cloak>{{ __('Analyzing...') }}</span>
        </button>
    </div>

    <p x-show="error" x-cloak class="mt-2 rounded-md bg-white p-2 text-xs text-red-600 ring-1 ring-red-100" x-text="error"></p>

    <div x-show="result" x-cloak class="mt-3 flex flex-col gap-2">
        {{--
            POLISHED TICKET (P6b) — the customer's words and the AI's rewrite, side by
            side, the original labelled and listed first.

            Both panes are ALWAYS rendered together: there is no toggle that could show the
            rewrite alone and no "replace" affordance. `tickets.subject` and
            `tickets.description` are never written by any automatic path, so the left pane
            is the byte-exact record of what the customer submitted — the evidence a
            dispute, a security incident or a billing argument turns on. "not applied" is in
            the label, not a tooltip.

            After an Apply the page re-renders from the database and the left pane shows the
            applied text. The original is not lost: TicketService::addHistory() recorded the
            field change, so applying a polish is recoverable.
        --}}
        <template x-if="result?.polished_subject">
            <div class="rounded-md bg-white p-2.5 ring-1 ring-gray-200">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ __('Subject') }}</span>
                    <div class="flex items-center gap-1.5" x-show="!decided.subject">
                        <button type="button" @click="applySuggested('subject')" x-show="!editing.subject" class="rounded-md bg-white px-2 py-0.5 text-[11px] font-medium text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-50">{{ __('Apply') }}</button>
                        <button type="button" @click="startEdit('subject')" x-show="!editing.subject" class="rounded-md px-2 py-0.5 text-[11px] font-medium text-indigo-700 hover:underline">{{ __('Edit & apply') }}</button>
                        <button type="button" @click="dismiss('subject')" x-show="!editing.subject" class="rounded-md px-2 py-0.5 text-[11px] font-medium text-gray-500 hover:text-gray-700">{{ __('Dismiss') }}</button>
                    </div>
                    <span x-show="decided.subject" x-cloak class="text-[11px] font-medium text-gray-400" x-text="decidedLabel('subject')"></span>
                </div>

                <div class="mt-1.5 grid gap-2 sm:grid-cols-2">
                    <div class="rounded-md bg-gray-50 p-2 ring-1 ring-gray-200">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">{{ __('Customer\'s words') }}</p>
                        <p class="mt-1 text-xs text-gray-800">{{ $ticket->subject }}</p>
                        <p class="mt-1 text-[10px] text-gray-400">{{ __('Stored exactly as submitted. This is the ticket.') }}</p>
                    </div>
                    <div class="rounded-md bg-indigo-50/70 p-2 ring-1 ring-indigo-100">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-indigo-700">{{ __('AI suggestion — not applied') }}</p>
                        <p x-show="!editing.subject" class="mt-1 text-xs text-gray-800" x-text="result.polished_subject"></p>
                        <div x-show="editing.subject" x-cloak class="mt-1">
                            <input type="text" x-model="drafts.subject" maxlength="255" class="block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <div class="mt-1.5 flex items-center gap-1.5">
                                <button type="button" @click="applyEdited('subject')" class="rounded-md bg-white px-2 py-0.5 text-[11px] font-medium text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-50">{{ __('Apply edited') }}</button>
                                <button type="button" @click="cancelEdit('subject')" class="rounded-md px-2 py-0.5 text-[11px] font-medium text-gray-500 hover:text-gray-700">{{ __('Cancel') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <template x-if="result?.polished_description">
            <div class="rounded-md bg-white p-2.5 ring-1 ring-gray-200">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ __('Description') }}</span>
                    <div class="flex items-center gap-1.5" x-show="!decided.description">
                        <button type="button" @click="applySuggested('description')" x-show="!editing.description" class="rounded-md bg-white px-2 py-0.5 text-[11px] font-medium text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-50">{{ __('Apply') }}</button>
                        <button type="button" @click="startEdit('description')" x-show="!editing.description" class="rounded-md px-2 py-0.5 text-[11px] font-medium text-indigo-700 hover:underline">{{ __('Edit & apply') }}</button>
                        <button type="button" @click="dismiss('description')" x-show="!editing.description" class="rounded-md px-2 py-0.5 text-[11px] font-medium text-gray-500 hover:text-gray-700">{{ __('Dismiss') }}</button>
                    </div>
                    <span x-show="decided.description" x-cloak class="text-[11px] font-medium text-gray-400" x-text="decidedLabel('description')"></span>
                </div>

                <div class="mt-1.5 grid gap-2 sm:grid-cols-2">
                    <div class="rounded-md bg-gray-50 p-2 ring-1 ring-gray-200">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">{{ __('Customer\'s words') }}</p>
                        <div class="mt-1 max-h-48 overflow-y-auto whitespace-pre-line text-xs text-gray-800">{{ $ticket->description }}</div>
                        <p class="mt-1 text-[10px] text-gray-400">{{ __('Stored exactly as submitted. This is the ticket.') }}</p>
                    </div>
                    <div class="rounded-md bg-indigo-50/70 p-2 ring-1 ring-indigo-100">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-indigo-700">{{ __('AI suggestion — not applied') }}</p>
                        <div x-show="!editing.description" class="mt-1 max-h-48 overflow-y-auto whitespace-pre-line text-xs text-gray-800" x-text="result.polished_description"></div>
                        <div x-show="editing.description" x-cloak class="mt-1">
                            <textarea x-model="drafts.description" rows="8" maxlength="8000" class="block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                            <div class="mt-1.5 flex items-center gap-1.5">
                                <button type="button" @click="applyEdited('description')" class="rounded-md bg-white px-2 py-0.5 text-[11px] font-medium text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-50">{{ __('Apply edited') }}</button>
                                <button type="button" @click="cancelEdit('description')" class="rounded-md px-2 py-0.5 text-[11px] font-medium text-gray-500 hover:text-gray-700">{{ __('Cancel') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        {{-- Summary --}}
        <template x-if="result?.summary">
            <div class="rounded-md bg-white p-2.5 ring-1 ring-gray-200">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ __('Summary') }}</span>
                    <div class="flex items-center gap-1.5" x-show="!decided.summary">
                        @if($analyzeCanComment)
                            <button type="button" @click="useSummary()" class="rounded-md bg-white px-2 py-0.5 text-[11px] font-medium text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-50">{{ __('Use as internal note') }}</button>
                            <button type="button" @click="startEdit('summary')" x-show="!editing.summary" class="rounded-md px-2 py-0.5 text-[11px] font-medium text-indigo-700 hover:underline">{{ __('Edit & apply') }}</button>
                        @endif
                        <button type="button" @click="dismiss('summary')" class="rounded-md px-2 py-0.5 text-[11px] font-medium text-gray-500 hover:text-gray-700">{{ __('Dismiss') }}</button>
                    </div>
                    <span x-show="decided.summary" x-cloak class="text-[11px] font-medium text-gray-400" x-text="decidedLabel('summary')"></span>
                </div>

                <div x-show="!editing.summary" class="mt-1.5 whitespace-pre-line text-xs text-gray-700" x-text="result.summary"></div>

                @if($analyzeCanComment)
                    <div x-show="editing.summary" x-cloak class="mt-1.5">
                        <textarea x-model="drafts.summary" rows="4" class="block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        <div class="mt-1.5 flex items-center gap-1.5">
                            <button type="button" @click="useSummary(drafts.summary)" class="rounded-md bg-white px-2 py-0.5 text-[11px] font-medium text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-50">{{ __('Use edited note') }}</button>
                            <button type="button" @click="cancelEdit('summary')" class="rounded-md px-2 py-0.5 text-[11px] font-medium text-gray-500 hover:text-gray-700">{{ __('Cancel') }}</button>
                        </div>
                    </div>
                @endif
            </div>
        </template>

        {{-- Category --}}
        <template x-if="result?.category">
            <div class="rounded-md bg-white p-2.5 ring-1 ring-gray-200">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="min-w-0">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ __('Category') }}</span>
                        <p x-show="!editing.category" class="mt-0.5 truncate text-xs font-medium text-gray-800" x-text="result.category.name"></p>
                    </div>
                    <div class="flex items-center gap-1.5" x-show="!decided.category">
                        <button type="button" @click="applySuggested('category')" x-show="!editing.category" class="rounded-md bg-white px-2 py-0.5 text-[11px] font-medium text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-50">{{ __('Apply') }}</button>
                        @if($analyzeCategories->count() > 1)
                            <button type="button" @click="startEdit('category')" x-show="!editing.category" class="rounded-md px-2 py-0.5 text-[11px] font-medium text-indigo-700 hover:underline">{{ __('Edit & apply') }}</button>
                        @endif
                        <button type="button" @click="dismiss('category')" x-show="!editing.category" class="rounded-md px-2 py-0.5 text-[11px] font-medium text-gray-500 hover:text-gray-700">{{ __('Dismiss') }}</button>
                    </div>
                    <span x-show="decided.category" x-cloak class="text-[11px] font-medium text-gray-400" x-text="decidedLabel('category')"></span>
                </div>

                @if($analyzeCategories->count() > 1)
                    <div x-show="editing.category" x-cloak class="mt-1.5 flex flex-wrap items-center gap-1.5">
                        <select x-model="drafts.category" class="rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach($analyzeCategories as $analyzeCategory)
                                <option value="{{ $analyzeCategory->id }}">{{ $analyzeCategory->name }}</option>
                            @endforeach
                        </select>
                        <button type="button" @click="applyEdited('category')" class="rounded-md bg-white px-2 py-0.5 text-[11px] font-medium text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-50">{{ __('Apply edited') }}</button>
                        <button type="button" @click="cancelEdit('category')" class="rounded-md px-2 py-0.5 text-[11px] font-medium text-gray-500 hover:text-gray-700">{{ __('Cancel') }}</button>
                    </div>
                @endif
            </div>
        </template>

        {{-- Priority --}}
        <template x-if="result?.priority">
            <div class="rounded-md bg-white p-2.5 ring-1 ring-gray-200">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="min-w-0">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ __('Priority') }}</span>
                        <p x-show="!editing.priority" class="mt-0.5 text-xs font-medium capitalize text-gray-800" x-text="result.priority"></p>
                        {{-- The rubric condition the model says this ticket meets. DISPLAY
                             ONLY: there is nothing on the ticket to apply a sentence to, so
                             it carries no Apply/Edit/Dismiss and is not an
                             AiSuggestionVerdict type — a verdict row for an inapplicable
                             suggestion would be a false row in the pilot's accept-vs-edit
                             metric. --}}
                        <p x-show="!editing.priority && result.priority_reason" x-cloak class="mt-0.5 text-[11px] text-gray-500" x-text="result.priority_reason"></p>
                    </div>
                    <div class="flex items-center gap-1.5" x-show="!decided.priority">
                        <button type="button" @click="applySuggested('priority')" x-show="!editing.priority" class="rounded-md bg-white px-2 py-0.5 text-[11px] font-medium text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-50">{{ __('Apply') }}</button>
                        <button type="button" @click="startEdit('priority')" x-show="!editing.priority" class="rounded-md px-2 py-0.5 text-[11px] font-medium text-indigo-700 hover:underline">{{ __('Edit & apply') }}</button>
                        <button type="button" @click="dismiss('priority')" x-show="!editing.priority" class="rounded-md px-2 py-0.5 text-[11px] font-medium text-gray-500 hover:text-gray-700">{{ __('Dismiss') }}</button>
                    </div>
                    <span x-show="decided.priority" x-cloak class="text-[11px] font-medium text-gray-400" x-text="decidedLabel('priority')"></span>
                </div>

                <div x-show="editing.priority" x-cloak class="mt-1.5 flex flex-wrap items-center gap-1.5">
                    <select x-model="drafts.priority" class="rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach(\App\Services\AI\AnalyzeTicketService::PRIORITIES as $analyzePriority)
                            <option value="{{ $analyzePriority }}">{{ __(ucfirst($analyzePriority)) }}</option>
                        @endforeach
                    </select>
                    <button type="button" @click="applyEdited('priority')" class="rounded-md bg-white px-2 py-0.5 text-[11px] font-medium text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-50">{{ __('Apply edited') }}</button>
                    <button type="button" @click="cancelEdit('priority')" class="rounded-md px-2 py-0.5 text-[11px] font-medium text-gray-500 hover:text-gray-700">{{ __('Cancel') }}</button>
                </div>
            </div>
        </template>

        {{-- Proposed troubleshooting checks (suggestion 4) --}}
        <template x-if="result?.checks?.length">
            <div class="rounded-md bg-white p-2.5 ring-1 ring-gray-200">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ __('Proposed checks') }}</span>
                    <div class="flex items-center gap-1.5" x-show="!decided.checks">
                        @if($analyzeCanTasks)
                            <button type="button" @click="applyChecks()" class="rounded-md bg-white px-2 py-0.5 text-[11px] font-medium text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-50">{{ __('Add to checklist') }}</button>
                        @endif
                        <button type="button" @click="dismiss('checks')" class="rounded-md px-2 py-0.5 text-[11px] font-medium text-gray-500 hover:text-gray-700">{{ __('Dismiss') }}</button>
                    </div>
                    <span x-show="decided.checks" x-cloak class="text-[11px] font-medium text-gray-400" x-text="decidedLabel('checks')"></span>
                </div>

                @if($analyzeCanTasks)
                    {{--
                        The ticket's own task endpoint, posted by "Add to checklist". Each
                        proposed check is an editable row, so an engineer who rewrites one
                        produces a genuine `edited` verdict from the server's hash
                        comparison rather than a claim the browser made.
                    --}}
                    <form x-ref="checksForm" method="POST" action="{{ route('tickets.tasks.polish-apply', $ticket) }}" class="mt-1.5">
                        @csrf
                        @foreach($analyzeOpenTasks as $analyzeOpenTask)
                            <input type="hidden" name="tasks[]" value="{{ $analyzeOpenTask }}">
                        @endforeach
                        <div class="flex flex-col gap-1">
                            <template x-for="(check, i) in drafts.checks" :key="i">
                                <div class="flex items-center gap-1.5">
                                    <input type="text" name="tasks[]" x-model="drafts.checks[i]" class="block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <button type="button" @click="drafts.checks.splice(i, 1)" title="{{ __('Remove') }}" class="shrink-0 rounded-md px-1.5 py-0.5 text-[11px] text-gray-400 hover:text-red-600">&times;</button>
                                </div>
                            </template>
                        </div>
                        <p class="mt-1 text-[11px] text-gray-400">{{ __('Open tasks already on this ticket are kept.') }}</p>
                    </form>
                @else
                    <ul class="mt-1.5 list-disc space-y-0.5 pl-4 text-xs text-gray-700">
                        <template x-for="(check, i) in result.checks" :key="i">
                            <li x-text="check"></li>
                        </template>
                    </ul>
                @endif
            </div>
        </template>

        {{-- Suggested engineer (suggestion 7) --}}
        <template x-if="result?.engineer">
            <div class="rounded-md bg-white p-2.5 ring-1 ring-gray-200">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="min-w-0">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ __('Suggested engineer') }}</span>
                        <p x-show="!editing.engineer" class="mt-0.5 truncate text-xs font-medium text-gray-800" x-text="result.engineer.name"></p>
                        <p x-show="!editing.engineer && result.engineer.reason" x-cloak class="mt-0.5 text-[11px] text-gray-500" x-text="result.engineer.reason"></p>
                    </div>
                    <div class="flex items-center gap-1.5" x-show="!decided.engineer">
                        <button type="button" @click="applySuggested('engineer')" x-show="!editing.engineer" class="rounded-md bg-white px-2 py-0.5 text-[11px] font-medium text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-50">{{ __('Assign') }}</button>
                        <button type="button" @click="startEdit('engineer')" x-show="!editing.engineer && result.engineer_candidates?.length > 1" class="rounded-md px-2 py-0.5 text-[11px] font-medium text-indigo-700 hover:underline">{{ __('Edit & apply') }}</button>
                        <button type="button" @click="dismiss('engineer')" x-show="!editing.engineer" class="rounded-md px-2 py-0.5 text-[11px] font-medium text-gray-500 hover:text-gray-700">{{ __('Dismiss') }}</button>
                    </div>
                    <span x-show="decided.engineer" x-cloak class="text-[11px] font-medium text-gray-400" x-text="decidedLabel('engineer')"></span>
                </div>

                {{--
                    The picker lists only this ticket's department, from the analysis
                    response — not a Blade-rendered roster. The id it posts is re-validated
                    by UpdateTicketRequest's tenantMemberRules(); the name is only hashed.
                --}}
                <div x-show="editing.engineer" x-cloak class="mt-1.5 flex flex-wrap items-center gap-1.5">
                    <select x-model="drafts.engineer" class="rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <template x-for="candidate in (result.engineer_candidates || [])" :key="candidate.id">
                            <option :value="String(candidate.id)" x-text="candidate.name"></option>
                        </template>
                    </select>
                    <button type="button" @click="applyEdited('engineer')" class="rounded-md bg-white px-2 py-0.5 text-[11px] font-medium text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-50">{{ __('Apply edited') }}</button>
                    <button type="button" @click="cancelEdit('engineer')" class="rounded-md px-2 py-0.5 text-[11px] font-medium text-gray-500 hover:text-gray-700">{{ __('Cancel') }}</button>
                </div>
            </div>
        </template>

        {{-- Similar resolved tickets (suggestion 5) --}}
        <template x-if="result?.context_tickets?.length">
            <div class="rounded-md bg-white p-2.5 ring-1 ring-gray-200">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ __('Similar resolved tickets') }}</span>
                    <div class="flex items-center gap-1.5" x-show="!decided.context">
                        <button type="button" @click="decide('context', 'accepted', null)" class="rounded-md bg-white px-2 py-0.5 text-[11px] font-medium text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-50">{{ __('Useful') }}</button>
                        <button type="button" @click="dismiss('context')" class="rounded-md px-2 py-0.5 text-[11px] font-medium text-gray-500 hover:text-gray-700">{{ __('Dismiss') }}</button>
                    </div>
                    <span x-show="decided.context" x-cloak class="text-[11px] font-medium text-gray-400" x-text="decidedLabel('context')"></span>
                </div>
                <ul class="mt-1.5 flex flex-col gap-1">
                    <template x-for="row in result.context_tickets" :key="row.id">
                        <li class="flex items-baseline gap-2 text-xs">
                            <a :href="ticketUrl(row.id)" class="font-mono text-[11px] text-indigo-700 hover:underline" x-text="row.ticket_number"></a>
                            <span class="min-w-0 flex-1 truncate text-gray-700" x-text="row.subject"></span>
                        </li>
                    </template>
                </ul>
            </div>
        </template>

        <p x-show="result?.rejected_fields?.length" x-cloak class="text-[11px] text-amber-700">
            {{ __('Some suggestions were discarded because the AI returned values this workspace does not have:') }}
            <span x-text="result?.rejected_fields?.join(', ')"></span>
        </p>
    </div>

    {{--
        The ticket's own update form, posted by Apply. The hidden fields are the ticket's
        current values, so UpdateTicketRequest sees a complete, tenant-scoped payload and
        only the one applied field differs.
    --}}
    <form x-ref="applyForm" method="POST" action="{{ route('tickets.update', $ticket) }}" class="hidden">
        @csrf
        @method('PUT')
        <input type="hidden" name="subject" value="{{ $ticket->subject }}" x-ref="field_subject">
        <textarea name="description" class="hidden" x-ref="field_description">{{ $ticket->description }}</textarea>
        <input type="hidden" name="status" value="{{ $ticket->status }}">
        <input type="hidden" name="client_id" value="{{ $ticket->client_id }}">
        <input type="hidden" name="department_id" value="{{ $ticket->department_id }}">
        <input type="hidden" name="assigned_to" value="{{ $ticket->assigned_to }}" x-ref="field_engineer">
        <input type="hidden" name="incident_date" value="{{ optional($ticket->incident_date)->format('Y-m-d') }}">
        @foreach($ticket->products as $product)
            <input type="hidden" name="product_ids[]" value="{{ $product->id }}">
        @endforeach
        <input type="hidden" name="priority" value="{{ $ticket->priority }}" x-ref="field_priority">
        <input type="hidden" name="category_id" value="{{ $ticket->category_id }}" x-ref="field_category_id">
    </form>
</div>

<script>
    function ticketAiAnalyze() {
        return {
            loading: false,
            error: '',
            // Hydrated from the newest stored analysis, so an automatically analysed
            // ticket arrives with its suggestions already on screen. Null when there is
            // none, which is exactly the pre-P6b state.
            result: @js($analyzeHydrated),
            // True once Analyze has been pressed in this page view, so the "analyzed
            // automatically" badge stops describing a result it is no longer about.
            reanalyzed: false,
            decided: {},
            editing: {},
            drafts: { checks: @js(is_array($analyzeHydrated['checks'] ?? null) ? $analyzeHydrated['checks'] : []) },
            // id -> name for the department's categories, so an edited category can be
            // reported to the verdict endpoint by NAME: that is what the stored suggestion
            // holds, and the server hashes the two names to decide accepted vs edited.
            categoryNames: @js($analyzeCategories->pluck('name', 'id')),
            canComment: @js($analyzeCanComment),
            canTasks: @js($analyzeCanTasks),
            analyzeUrl: '{{ route('tickets.ai.analyze', $ticket) }}',
            // Built from the named routes with a placeholder id, so the tenant prefix
            // comes from SetTenantUrlDefaults rather than being re-assembled by hand.
            verdictUrlTemplate: '{{ route('tickets.ai.analysis-verdict', ['analysis' => '__ID__']) }}',
            ticketUrlTemplate: '{{ route('tickets.show', ['ticket' => '__ID__']) }}',
            csrf: document.querySelector('meta[name="csrf-token"]')?.content || '',
            ticketUrl(id) { return this.ticketUrlTemplate.replace('__ID__', id); },
            decidedLabel(type) {
                return { accepted: @js(__('Accepted')), edited: @js(__('Edited')), rejected: @js(__('Dismissed')) }[this.decided[type]] || '';
            },
            async analyze() {
                this.error = ''; this.loading = true; this.result = null; this.reanalyzed = true;
                this.decided = {}; this.editing = {}; this.drafts = { checks: [] };
                try {
                    const res = await fetch(this.analyzeUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) throw new Error(data.error || @js(__('The AI request failed.')));
                    this.result = data;
                    // Editable copy: the proposed checks are rows the engineer may rewrite
                    // or remove before applying, and what is applied is what gets hashed.
                    this.drafts.checks = Array.isArray(data.checks) ? data.checks.slice() : [];
                } catch (e) {
                    this.error = e.message;
                } finally {
                    this.loading = false;
                }
            },
            startEdit(type) {
                if (type === 'subject' || type === 'description') {
                    this.drafts[type] = this.result['polished_' + type];
                } else if (type === 'category') {
                    this.drafts[type] = String(this.result.category.id);
                } else if (type === 'engineer') {
                    this.drafts[type] = String(this.result.engineer.id);
                } else {
                    this.drafts[type] = this.result[type];
                }
                this.editing[type] = true;
            },
            cancelEdit(type) { this.editing[type] = false; },
            // The verdict word is NOT ours to choose between accepted and edited: the
            // server compares a hash of what we applied against a hash of what it stored,
            // so the metric cannot be dictated from here. We only ever claim 'accepted'
            // (meaning "applied") or 'rejected' (an explicit Dismiss).
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
            // The name of a record-valued suggestion (category, engineer) is what the
            // stored suggestion holds, so the name is what the verdict endpoint compares.
            // The id is only ever the form value, re-validated by UpdateTicketRequest.
            nameFor(type, id) {
                if (type === 'category') return this.categoryNames[id] ?? '';
                if (type === 'engineer') {
                    const match = (this.result.engineer_candidates || []).find(c => String(c.id) === String(id));

                    return match ? match.name : '';
                }

                return id;
            },
            applySuggested(type) {
                const id = type === 'category'
                    ? this.result.category.id
                    : (type === 'engineer'
                        ? this.result.engineer.id
                        : (type === 'subject' || type === 'description'
                            ? this.result['polished_' + type]
                            : this.result[type]));

                return this.apply(type, id, this.nameFor(type, id));
            },
            applyEdited(type) {
                const value = this.drafts[type];

                return this.apply(type, value, this.nameFor(type, value));
            },
            // Record the verdict BEFORE navigating: the form submit unloads the page.
            // appliedValue is what the engineer actually applied, edited or not — that is
            // the whole input to the accept-vs-edit metric.
            async apply(type, formValue, appliedValue) {
                const ok = await this.decide(type, 'accepted', appliedValue);
                if (!ok) return;
                const input = this.$refs['field_' + (type === 'category' ? 'category_id' : type)];
                if (input) input.value = formValue;
                this.$refs.applyForm.submit();
            },
            // Suggestion 4 applies through the ticket's OWN task endpoint
            // (tickets.tasks.polish-apply) — there is no second apply path. The verdict
            // carries the applied rows only, so the server's hash comparison reports
            // `accepted` for an untouched list and `edited` for a rewritten one.
            // Records NOTHING when the form is absent (no permission, or a closed ticket).
            async applyChecks() {
                const form = this.$refs.checksForm;
                if (!this.canTasks || !form) {
                    this.error = @js(__('You cannot change this ticket\'s checklist, so nothing was applied.'));

                    return false;
                }

                const applied = (this.drafts.checks || []).map(c => String(c).trim()).filter(c => c !== '');
                if (applied.length === 0) {
                    this.error = @js(__('There are no checks left to apply.'));

                    return false;
                }

                const ok = await this.decide('checks', 'accepted', applied.join('\n'));
                if (!ok) return false;
                form.submit();

                return true;
            },
            // Fills the note box and forces the type select to Internal. A triage summary
            // is internal: the public reply box would make this the client-facing draft
            // that suggestion 6 was deliberately held back from this package.
            // Records NOTHING when the box is absent (a closed or cancelled ticket has no
            // comment form) — banking minutes_saved for a fill that did not happen puts a
            // false row in the table the pilot's metric is read from.
            async useSummary(edited) {
                const ta = document.getElementById('comment_content');
                if (!this.canComment || !ta) {
                    this.error = @js(__('Add a comment is unavailable on this ticket, so the summary was not copied.'));

                    return false;
                }

                const text = (edited ?? this.result.summary);
                ta.value = text;
                const type = document.querySelector('#add-comment-form select[name="type"]');
                if (type) type.value = 'internal';
                ta.focus();
                this.editing.summary = false;

                return this.decide('summary', 'accepted', text);
            },
        };
    }
</script>
