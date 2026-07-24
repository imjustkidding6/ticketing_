<div class="prose prose-sm max-w-none text-gray-700 dark:text-gray-300 dark:prose-invert">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Managing Tickets') }}</h2>
    <p>{{ __('Tickets are the heart of CliqueHA Nexus. This guide covers the full lifecycle — creating a ticket, working it, and bringing it to a clean close — along with the power features your plan unlocks.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Creating a Ticket') }}</h3>
    <p>{{ __('Click "Create Ticket" from the Tickets page. Pick the client (or create one on the fly), write a clear subject and description, and choose the department, category, and priority. You can assign an agent now or leave it open for triage. If you use products, link the relevant ones so the ticket shows up in product reporting.') }}</p>
    <p>{{ __('Tickets also arrive automatically from the public portal and, where configured, from email — those land as Open and unassigned, ready for your team to pick up.') }}</p>
    @include('tutorials.partials._figure', ['img' => 'tickets-list.png', 'alt' => __('Tickets list'), 'caption' => __('The Tickets list, where you can filter, search, and open any ticket.')])

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Ticket Lifecycle') }}</h3>
    <p>{{ __('Every ticket moves through a series of statuses:') }}</p>
    <ul class="mt-2 list-disc pl-5 space-y-1">
        <li><strong class="dark:text-gray-100">{{ __('Open') }}</strong> — {{ __('Newly created, waiting for assignment or action.') }}</li>
        <li><strong class="dark:text-gray-100">{{ __('Assigned') }}</strong> — {{ __('An agent has been assigned to handle the ticket.') }}</li>
        <li><strong class="dark:text-gray-100">{{ __('In Progress') }}</strong> — {{ __('The agent is actively working on it. Moving to this status records the first-response time used for SLA.') }}</li>
        <li><strong class="dark:text-gray-100">{{ __('On Hold') }}</strong> — {{ __('Paused, usually waiting on the client or a third party. Hold time is excluded from SLA calculations.') }}</li>
        <li><strong class="dark:text-gray-100">{{ __('Closed') }}</strong> — {{ __('The issue has been resolved and the ticket is complete.') }}</li>
        <li><strong class="dark:text-gray-100">{{ __('Cancelled') }}</strong> — {{ __('Closed without resolution, for example a duplicate or a false alarm.') }}</li>
    </ul>

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Assigning & Self-Assigning') }}</h3>
    <p>{{ __('Managers and admins assign tickets to agents from the ticket detail page. Agents can self-assign open tickets when self-assignment is enabled in Ticket settings. The assignee receives a notification (on plans with email notifications) so nothing slips through the cracks.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Replies, Internal Notes & Attachments') }}</h3>
    <p>{{ __('Use the ticket timeline to add replies the client can see and internal notes that stay private to your team. Every action — status changes, assignments, edits — is recorded on the timeline so anyone picking up the ticket has the full history.') }}</p>
    <p>{{ __('Attach screenshots, logs, or documents to a ticket or reply to give context.') }}</p>
    <p class="text-xs text-amber-600 dark:text-amber-400">{{ __('Note: Attachments are available on Business and Enterprise plans. Canned responses (saved reply templates) are an Enterprise feature.') }}</p>
    @include('tutorials.partials._figure', ['img' => 'ticket-detail.png', 'alt' => __('Ticket detail page'), 'caption' => __('The ticket detail page brings together status, assignment, tasks, service reports, attachments, and the timeline.')])

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Ticket Tasks') }}</h3>
    <p>{{ __('Break complex tickets into smaller tasks, each with its own status (pending, in progress, completed, or cancelled) and optional assignee. Tasks are managed from the ticket detail page and give you a clear checklist of what is left to do.') }}</p>
    <div class="mt-3 rounded-md border-l-4 border-indigo-400 bg-indigo-50 p-3 text-xs text-indigo-900 dark:border-indigo-500 dark:bg-indigo-500/10 dark:text-indigo-300">
        {{ __('Important: A ticket must have at least one task before it can be closed, and any open tasks must be completed or cancelled first. This ensures the work done is documented — it also feeds the service report. If closing is blocked, add and finish a task describing the resolution.') }}
    </div>

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Hold Time') }}</h3>
    <p>{{ __('When you are waiting on the client or an external party, set the ticket to On Hold. The clock for SLA pauses while it is on hold and resumes when you take it off hold, so your team is never penalized for delays outside its control. Resolution and response figures in reports already exclude hold time.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Closing, Cancelling & False Alarms') }}</h3>
    <p>{{ __('Close a ticket once the issue is resolved, adding closing remarks to summarize the outcome. If a ticket turns out to be a non-issue, mark it as a false alarm — it is flagged and closed in one step. Use Cancel for duplicates or requests that should not count toward your metrics.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Reopening Tickets') }}</h3>
    <p>{{ __('If a closed issue comes back, reopen the ticket instead of starting over. The reopen count is tracked and surfaced in the Reopen Analysis report, helping you spot issues that are not being fully resolved the first time.') }}</p>
    <p class="text-xs text-amber-600 dark:text-amber-400">{{ __('Available on the Enterprise plan.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Merging Duplicates') }}</h3>
    <p>{{ __('When several tickets describe the same issue, merge them into one. The source ticket is archived and linked to the target so its history is preserved, and it is excluded from your counts to avoid double-reporting. A merge can be undone if you change your mind.') }}</p>
    <p class="text-xs text-amber-600 dark:text-amber-400">{{ __('Available on the Enterprise plan.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Escalation') }}</h3>
    <p>{{ __('For issues that need a more senior agent, escalate the ticket up the support tiers (Tier 1, Tier 2, Tier 3). Tickets can only move upward, and the agent you assign must have a support tier equal to or higher than the target tier. Owners can escalate regardless of tier.') }}</p>
    <p class="text-xs text-amber-600 dark:text-amber-400">{{ __('Available on the Enterprise plan.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Priority Levels') }}</h3>
    <p>{{ __('Set priority to Low, Medium, High, or Critical. Priority — together with the client tier — selects which SLA policy applies, driving the response and resolution targets, and it helps your team focus on what matters most.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Spam & Activity History') }}</h3>
    <p>{{ __('Mark unwanted public-portal submissions as spam to keep them out of your active queue and reports. Every change to a ticket is captured in its activity history, giving you a complete, timestamped audit trail of who did what and when.') }}</p>
    <p class="text-xs text-amber-600 dark:text-amber-400">{{ __('Note: Mark as spam and ticket activity history (audit logs) are available on Business and Enterprise plans.') }}</p>
</div>