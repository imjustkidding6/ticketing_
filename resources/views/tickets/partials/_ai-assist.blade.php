{{-- AI "Polish with Nexus AI" card for the ticket create/edit forms.
     Reads #subject and #description, rewrites them in place, and (when $withTasks)
     fills the task checklist via an `ai-fill-tasks` event.

     On the create form it additionally pre-fills #priority with a suggestion the
     workspace's SLA policies can actually honour, and explains in words any priority it
     could not offer; and, when a department is selected, names the engineer best placed
     to take the ticket — pre-filling #assigned_to only when the assignment guards would
     admit them, and otherwise saying so in words rather than offering a dead-end click.
     Nothing auto-applies — the engineer still submits the form, and
     TicketService::createTicket() still runs every guard it runs today.

     Params: $withTasks (default true), $context ('create' | 'edit', default 'create'). --}}
@php
    $withTasks = $withTasks ?? true;
    $aiContext = ($context ?? 'create') === 'edit' ? 'edit' : 'create';
    $aiAssist = app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::AiChatbot)
        && (bool) \App\Models\AppSetting::get('ai_enabled', false)
        && (bool) \App\Models\AppSetting::get('ai_agent_copilot_enabled', false);
@endphp
@if($aiAssist)
<div x-data="ticketAiAssist({{ $withTasks ? 'true' : 'false' }}, @js($aiContext))" x-init="init()" class="rounded-xl border border-indigo-100 bg-gradient-to-br from-indigo-50 to-violet-50 p-4">
    <div class="flex items-start gap-2.5">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-500 text-white shadow-sm">
            <svg class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" /></svg>
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-indigo-900">{{ __('Polish with Nexus AI') }}</p>
            <p class="mt-0.5 text-xs text-indigo-700">
                @if($withTasks)
                    {{ __('Jot rough notes in the Subject and Description below, then let AI rewrite them clearly and suggest resolution tasks. Clean, consistent tickets help the assistant learn from how your team resolves them.') }}
                @else
                    {{ __('Let AI rewrite the Subject and Description into a cleaner, more consistent form — without changing the facts. Tidy records keep the assistant\'s learning accurate.') }}
                @endif
            </p>
            <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2">
                <button type="button" @click="polish()" :disabled="loading"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-br from-indigo-600 to-violet-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:from-indigo-500 hover:to-violet-500 disabled:opacity-50">
                    <svg x-show="!loading" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" /></svg>
                    <svg x-show="loading" x-cloak class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span x-text="loading ? @js(__('Polishing…')) : @js(__('Polish details with AI'))"></span>
                </button>
                <button type="button" x-show="canRevert" x-cloak @click="revert()" class="text-xs font-medium text-gray-500 underline hover:text-gray-700">{{ __('Revert changes') }}</button>
                <span x-show="note" x-cloak class="text-xs font-medium" :class="error ? 'text-red-600' : 'text-emerald-600'" x-text="note"></span>
            </div>
            <p x-show="priorityNote" x-cloak class="mt-2 text-xs font-medium text-indigo-800" x-text="priorityNote"></p>
            <p x-show="priorityGap" x-cloak class="mt-1 text-xs text-amber-700" x-text="priorityGap"></p>
            <p x-show="engineerNote" x-cloak class="mt-1 text-xs font-medium text-indigo-800" x-text="engineerNote"></p>
            <p x-show="engineerBlocked" x-cloak class="mt-1 text-xs text-amber-700" x-text="engineerBlocked"></p>
            @if($aiContext === 'create')
                {{-- An opaque cache key, so the Accept / Edit / Reject capture is computed
                     from what the server suggested rather than from anything the browser
                     can author. Empty until a draft has been polished. --}}
                <input type="hidden" name="ai_draft_token" x-model="draftToken">
            @endif
        </div>
    </div>
</div>
<script>
    function ticketAiAssist(withTasks = true, context = 'create') {
        return {
            withTasks: withTasks,
            context: context,
            loading: false,
            canRevert: false,
            note: '',
            priorityNote: '',
            priorityGap: '',
            engineerNote: '',
            engineerBlocked: '',
            draftToken: '',
            error: false,
            backup: null,
            url: '{{ route('tickets.ai.structure') }}',
            csrf: document.querySelector('meta[name="csrf-token"]')?.content || '',
            init() {
                // Changing the client changes which SLA policies cover the draft, so a
                // priority suggested for the previous one stops being a statement about
                // this ticket. The value stays (the engineer may well want it) — the claim
                // that AI suggested it does not.
                document.getElementById('client_id')?.addEventListener('change', () => {
                    this.priorityNote = '';
                    this.priorityGap = '';
                });
                // Likewise the department: it is what decided who the candidates were, so
                // a suggestion made for the previous one is no longer a statement about
                // this ticket. The value stays; the claim that AI suggested it does not.
                document.getElementById('department_id')?.addEventListener('change', () => {
                    this.engineerNote = '';
                    this.engineerBlocked = '';
                });
            },
            currentTasks() {
                return Array.from(document.querySelectorAll('input[name^="tasks["]')).map((i) => i.value);
            },
            async polish() {
                if (this.loading) return;
                const subjectEl = document.getElementById('subject');
                const descEl = document.getElementById('description');
                const description = (descEl?.value || '').trim();
                if (!description) {
                    this.error = true;
                    this.note = @js(__('Add a description first.'));
                    return;
                }
                this.loading = true;
                this.note = '';
                this.error = false;
                const priorityEl = document.getElementById('priority');
                const clientEl = document.getElementById('client_id');
                const departmentEl = document.getElementById('department_id');
                const assigneeEl = document.getElementById('assigned_to');
                this.backup = {
                    subject: subjectEl?.value || '',
                    description: descEl?.value || '',
                    tasks: this.currentTasks(),
                    priority: priorityEl?.value || '',
                    assignedTo: assigneeEl?.value || '',
                };
                try {
                    const res = await fetch(this.url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                        body: JSON.stringify({
                            subject: subjectEl?.value || '',
                            description,
                            // The client decides which SLA policies cover the draft, so the
                            // suggestion is computed against the one that is selected right now.
                            client_id: clientEl?.value || null,
                            // No department means no engineer suggestion: the candidate
                            // set is never widened to the whole workspace.
                            department_id: departmentEl?.value || null,
                            context: this.context,
                            // The reporter's own declared technical facts, read by id the
                            // same way as the fields above. Validated nullable server-side,
                            // so a half-filled form polishes rather than 422-ing.
                            impact_scope: document.getElementById('impact_scope')?.value || null,
                            workaround: document.getElementById('workaround')?.value || null,
                            environment: document.getElementById('environment')?.value || null,
                            steps_to_reproduce: document.getElementById('steps_to_reproduce')?.value || null,
                            error_text: document.getElementById('error_text')?.value || null,
                        }),
                    });
                    if (!res.ok) throw new Error('request failed');
                    const data = await res.json();
                    if (subjectEl && data.subject) { subjectEl.value = data.subject; subjectEl.dispatchEvent(new Event('input')); }
                    if (descEl && data.description) { descEl.value = data.description; descEl.dispatchEvent(new Event('input')); }
                    if (this.withTasks && Array.isArray(data.tasks) && data.tasks.length) {
                        window.dispatchEvent(new CustomEvent('ai-fill-tasks', { detail: data.tasks }));
                    }
                    // Both keys are absent on the edit form, and the element may not exist
                    // at all — a missing suggestion leaves the form exactly as it was.
                    this.priorityNote = '';
                    if (priorityEl && data.priority) {
                        priorityEl.value = data.priority;
                        priorityEl.dispatchEvent(new Event('change'));
                        priorityEl.dispatchEvent(new Event('input'));
                        const label = data.priority.charAt(0).toUpperCase() + data.priority.slice(1);
                        this.priorityNote = @js(__('Priority set to')) + ' ' + label
                            + (data.priority_reason ? ' — ' + data.priority_reason : '');
                    }
                    this.priorityGap = data.priority_gap?.message || '';
                    // Absent on the edit form, and absent whenever no department was
                    // selected. `prefill` false is advisory: the engineer is named, the
                    // select is left alone, because a guard would refuse the assignment.
                    this.engineerNote = '';
                    this.engineerBlocked = '';
                    if (data.engineer && data.engineer.name) {
                        const reason = data.engineer.reason ? ' — ' + data.engineer.reason : '';
                        if (assigneeEl && data.engineer.prefill) {
                            assigneeEl.value = String(data.engineer.id);
                            assigneeEl.dispatchEvent(new Event('change'));
                            assigneeEl.dispatchEvent(new Event('input'));
                            this.engineerNote = @js(__('Assigned to')) + ' ' + data.engineer.name + reason;
                        } else {
                            this.engineerNote = @js(__('Nexus suggests')) + ' ' + data.engineer.name + reason;
                            this.engineerBlocked = data.engineer.blocked_reason || '';
                        }
                    }
                    this.draftToken = data.draft_token || '';
                    this.canRevert = true;
                    this.note = @js(__('Cleaned up by AI ✨'));
                } catch (e) {
                    this.error = true;
                    this.note = @js(__('Could not reach the AI. Please try again.'));
                } finally {
                    this.loading = false;
                }
            },
            revert() {
                if (!this.backup) return;
                const subjectEl = document.getElementById('subject');
                const descEl = document.getElementById('description');
                if (subjectEl) { subjectEl.value = this.backup.subject; subjectEl.dispatchEvent(new Event('input')); }
                if (descEl) { descEl.value = this.backup.description; descEl.dispatchEvent(new Event('input')); }
                if (this.withTasks) {
                    window.dispatchEvent(new CustomEvent('ai-fill-tasks', { detail: this.backup.tasks }));
                }
                const priorityEl = document.getElementById('priority');
                if (priorityEl && this.backup.priority) {
                    priorityEl.value = this.backup.priority;
                    priorityEl.dispatchEvent(new Event('change'));
                }
                // The assignee is restored even when the backup was empty: `Unassigned`
                // is a real previous state, unlike the priority select, which always has
                // a value.
                const assigneeEl = document.getElementById('assigned_to');
                if (assigneeEl) {
                    assigneeEl.value = this.backup.assignedTo;
                    assigneeEl.dispatchEvent(new Event('change'));
                }
                // The token goes with the suggestion: a reverted draft has nothing landed
                // to derive a verdict from, so it records nothing rather than a false reject.
                this.draftToken = '';
                this.priorityNote = '';
                this.priorityGap = '';
                this.engineerNote = '';
                this.engineerBlocked = '';
                this.canRevert = false;
                this.error = false;
                this.note = @js(__('Reverted.'));
            },
        };
    }
</script>
@endif
