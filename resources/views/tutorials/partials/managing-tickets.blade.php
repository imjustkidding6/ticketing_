<div class="prose prose-sm max-w-none text-gray-700">
    <h2 class="text-lg font-semibold text-gray-900">{{ __('Managing Tickets') }}</h2>
    <p>{{ __('Tickets are the core of TechDesk. Here\'s how to manage them effectively throughout their lifecycle.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Ticket Lifecycle') }}</h3>
    <p>{{ __('Every ticket moves through a series of statuses:') }}</p>
    <ul class="mt-2 list-disc pl-5 space-y-1">
        <li><strong>{{ __('Open') }}</strong> — {{ __('Newly created, waiting for assignment or action.') }}</li>
        <li><strong>{{ __('Assigned') }}</strong> — {{ __('An agent has been assigned to handle the ticket.') }}</li>
        <li><strong>{{ __('In Progress') }}</strong> — {{ __('The agent is actively working on the ticket.') }}</li>
        <li><strong>{{ __('On Hold') }}</strong> — {{ __('Paused, usually waiting for client response or external input. Hold time is excluded from SLA calculations.') }}</li>
        <li><strong>{{ __('Closed') }}</strong> — {{ __('The issue has been resolved.') }}</li>
        <li><strong>{{ __('Cancelled') }}</strong> — {{ __('The ticket was cancelled (e.g., duplicate or false alarm).') }}</li>
    </ul>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Assigning Tickets') }}</h3>
    <p>{{ __('Managers and admins can assign tickets to agents from the ticket detail page. Agents can also self-assign open tickets. When a ticket is assigned, the agent receives a notification.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Ticket Tasks') }}</h3>
    <p>{{ __('Break complex tickets into smaller tasks. Each task can be assigned to a different agent and tracked independently. Tasks have their own status (pending, in progress, completed) and can be managed from the ticket detail page.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Ticket Merging') }}</h3>
    <p>{{ __('When duplicate tickets come in for the same issue, merge them into one. The original ticket is archived and linked to the target ticket. The merge can be undone if needed.') }}</p>
    <p class="text-xs text-amber-600">{{ __('Available on the Enterprise plan.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Escalation') }}</h3>
    <p>{{ __('For complex issues, escalate tickets to higher-tier agents. The system supports 3 tiers (Tier 1, Tier 2, Tier 3). Tickets can only be escalated upward, and the assigned agent must have the required tier level.') }}</p>
    <p class="text-xs text-amber-600">{{ __('Available on the Enterprise plan.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Priority Levels') }}</h3>
    <p>{{ __('Set ticket priority to Low, Medium, High, or Critical. Priority affects SLA response and resolution targets, and helps your team focus on what matters most.') }}</p>
</div>
