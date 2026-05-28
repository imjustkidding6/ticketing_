<div class="prose prose-sm max-w-none text-gray-700">
    <h2 class="text-lg font-semibold text-gray-900">{{ __('SLA Management') }}</h2>
    <p>{{ __('Service Level Agreements (SLAs) define response and resolution time targets for your support tickets. TechDesk tracks compliance automatically and alerts your team when deadlines are approaching.') }}</p>
    <p class="text-xs text-amber-600">{{ __('SLA management is available on Business and Enterprise plans.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('How SLA Policies Work') }}</h3>
    <p>{{ __('SLA policies are defined per client tier (Standard, Premium, VIP) and per priority level (Low, Medium, High, Critical). Each policy specifies:') }}</p>
    <ul class="mt-2 list-disc pl-5 space-y-1">
        <li><strong>{{ __('Response Time') }}</strong> — {{ __('How quickly the first response must be sent after ticket creation.') }}</li>
        <li><strong>{{ __('Resolution Time') }}</strong> — {{ __('How quickly the ticket must be fully resolved.') }}</li>
    </ul>
    <p>{{ __('For example, a VIP client\'s Critical ticket might require a 1-hour response and 4-hour resolution, while a Standard client\'s Low priority ticket might allow 24-hour response and 72-hour resolution.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Setting Up SLA Policies') }}</h3>
    <p>{{ __('Navigate to SLA Policies from the sidebar. You can seed default policies to start from, then customize the response and resolution targets for each tier/priority combination. Each tier (Standard, Premium, VIP) is configured independently.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Hold Time & SLA') }}</h3>
    <p>{{ __('When a ticket is placed on hold (e.g., waiting for client response), the hold time is excluded from SLA calculations. This ensures agents aren\'t penalized for delays outside their control. Hold time tracking starts when the ticket status is set to "On Hold" and stops when it\'s resumed.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Breach Warnings') }}</h3>
    <p>{{ __('TechDesk checks for SLA breaches every 15 minutes. When a ticket is approaching its response or resolution deadline, the assigned agent receives an email warning. This gives your team time to act before the SLA is breached.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('SLA Compliance Report') }}</h3>
    <p>{{ __('The SLA Compliance report shows how well your team is meeting SLA targets. Filter by date range to see compliance rates, average response/resolution times, and identify tickets that breached their SLA.') }}</p>
</div>
