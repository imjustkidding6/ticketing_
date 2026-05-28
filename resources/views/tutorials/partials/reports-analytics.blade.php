<div class="prose prose-sm max-w-none text-gray-700">
    <h2 class="text-lg font-semibold text-gray-900">{{ __('Reports & Analytics') }}</h2>
    <p>{{ __('TechDesk provides comprehensive reporting to help you understand ticket volume, team performance, and service quality.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Report Overview') }}</h3>
    <p>{{ __('The Reports Overview page shows a high-level summary of ticket activity, including total tickets, tickets by status, and volume trends. Use the date range filter to focus on specific periods.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Available Reports') }}</h3>
    <ul class="mt-2 list-disc pl-5 space-y-1">
        <li><strong>{{ __('Ticket Report') }}</strong> — {{ __('Detailed breakdown of all tickets with filters for status, priority, department, and date range.') }}</li>
        <li><strong>{{ __('Department Report') }}</strong> — {{ __('Ticket volume and resolution metrics per department.') }}</li>
        <li><strong>{{ __('Category Report') }}</strong> — {{ __('See which types of issues are most common.') }}</li>
        <li><strong>{{ __('Client Report') }}</strong> — {{ __('Ticket activity per client, useful for identifying frequent requesters.') }}</li>
        <li><strong>{{ __('Agent Report') }}</strong> — {{ __('Individual agent performance: tickets handled, average resolution time, workload.') }}</li>
        <li><strong>{{ __('Product Report') }}</strong> — {{ __('Tickets by product/service, helps identify problematic areas.') }}</li>
    </ul>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Billing Report') }}</h3>
    <p>{{ __('Track billable hours and costs for tickets that have billing enabled. View billed vs. unbilled tickets and export for invoicing.') }}</p>
    <p class="text-xs text-amber-600">{{ __('Available on Business and Enterprise plans.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('CSV Export') }}</h3>
    <p>{{ __('All reports can be exported to CSV for further analysis in spreadsheets or BI tools. Look for the "Export" button on each report page.') }}</p>
    <p class="text-xs text-amber-600">{{ __('CSV export requires the Detailed Reporting feature (Business and Enterprise plans).') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Service Reports') }}</h3>
    <p>{{ __('Generate PDF service reports for individual tickets, useful for documenting work performed for clients. These can be downloaded and shared externally.') }}</p>
    <p class="text-xs text-amber-600">{{ __('Available on Business and Enterprise plans.') }}</p>
</div>
