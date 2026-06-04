<div class="prose prose-sm max-w-none text-gray-700">
    <h2 class="text-lg font-semibold text-gray-900">{{ __('Reports & Analytics') }}</h2>
    <p>{{ __('CliqueHA Nexus turns your ticket activity into insight — so you can see how much work is coming in, how your team is performing, and where service quality can improve. Every report supports a date-range filter; use it to compare this week to last, or to pull figures for a monthly review.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Reports Overview') }}</h3>
    <p>{{ __('The overview page is your starting point: total tickets, a breakdown by status, and volume trends over the selected period. Use it to take the pulse of your support operation at a glance, then drill into a specific report for detail.') }}</p>
    @include('tutorials.partials._figure', ['img' => 'reports.png', 'alt' => __('Reports overview'), 'caption' => __('The Reports overview, with totals, status breakdowns, and trends for the selected date range.')])

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Ticket Volume & Trends') }}</h3>
    <p>{{ __('See how many tickets are created versus resolved over time, split by status and priority. Rising volume with flat resolution is an early warning that your team needs more capacity or better deflection through the knowledge base.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Available Reports') }}</h3>
    <ul class="mt-2 list-disc pl-5 space-y-1">
        <li><strong>{{ __('Ticket Report') }}</strong> — {{ __('A detailed list of tickets with filters for status, priority, department, category, and date range.') }}</li>
        <li><strong>{{ __('Department Report') }}</strong> — {{ __('Volume and resolution metrics for each department, so you can see where the load sits.') }}</li>
        <li><strong>{{ __('Category Report') }}</strong> — {{ __('Which types of issue are most common — useful for prioritizing fixes and knowledge base articles.') }}</li>
        <li><strong>{{ __('Client Report') }}</strong> — {{ __('Ticket activity per client, to identify your most frequent or most demanding accounts.') }}</li>
        <li><strong>{{ __('Agent Report') }}</strong> — {{ __('Per-agent performance: tickets handled, average resolution time (hold time excluded), and current workload.') }}</li>
        <li><strong>{{ __('Product Report') }}</strong> — {{ __('Tickets by product or service, highlighting which offerings drive the most support.') }}</li>
        <li><strong>{{ __('Reopen Analysis') }}</strong> — {{ __('How often closed tickets are reopened, a key signal of first-time resolution quality. (Enterprise)') }}</li>
    </ul>
    @include('tutorials.partials._figure', ['img' => 'reports-agents.png', 'alt' => __('Agent performance report'), 'caption' => __('The Agent report — tickets handled, average resolution time, and workload per agent.')])

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('SLA Compliance Report') }}</h3>
    <p>{{ __('Measure how well your team meets its response and resolution targets. The report shows compliance rates, average response and resolution times (with hold time excluded), and the specific tickets that breached, broken down by priority and client tier. See the SLA Management guide for how targets are set.') }}</p>
    <p class="text-xs text-amber-600">{{ __('Available on Business and Enterprise plans.') }}</p>
    @include('tutorials.partials._figure', ['img' => 'sla-compliance.png', 'alt' => __('SLA compliance report'), 'caption' => __('The SLA Compliance report — met versus missed targets, broken down by priority and client tier.')])

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Billing Report') }}</h3>
    <p>{{ __('Track billable hours and costs for tickets with billing enabled, and review billed versus unbilled work ready for invoicing.') }}</p>
    <p class="text-xs text-amber-600">{{ __('Available on Business and Enterprise plans.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Service Reports (PDF)') }}</h3>
    <p>{{ __('Generate a polished PDF for an individual ticket that documents the work performed — including its tasks and resolution — to share with the client. Branding and layout come from your Service Report settings.') }}</p>
    <p class="text-xs text-amber-600">{{ __('Available on Business and Enterprise plans.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Exporting to CSV') }}</h3>
    <p>{{ __('Export any report to CSV to analyze it further in a spreadsheet or BI tool. Look for the "Export" button on each report page.') }}</p>
    <p class="text-xs text-amber-600">{{ __('Note: CSV export requires the Detailed Reporting feature (Business and Enterprise plans).') }}</p>

    <div class="mt-6 rounded-md border-l-4 border-indigo-400 bg-indigo-50 p-3 text-xs text-indigo-900">
        {{ __('Tip: Make reporting a habit. Review SLA compliance and agent workload weekly, and ticket volume and reopen rates monthly, to catch problems while they are still small.') }}
    </div>
</div>
