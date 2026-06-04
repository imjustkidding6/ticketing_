<div class="prose prose-sm max-w-none text-gray-700">
    <h2 class="text-lg font-semibold text-gray-900">{{ __('SLA Management') }}</h2>
    <p>{{ __('Service Level Agreements (SLAs) define the response and resolution time targets your team commits to. TechDesk applies the right target to every ticket, tracks compliance automatically, and warns your team before a deadline is missed.') }}</p>
    <p class="text-xs text-amber-600">{{ __('SLA management is available on Business and Enterprise plans.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('How SLA Policies Work') }}</h3>
    <p>{{ __('A policy is matched to a ticket by its client tier (Basic, Premium, Enterprise) and its priority (Low, Medium, High, Critical). Each policy sets two targets:') }}</p>
    <ul class="mt-2 list-disc pl-5 space-y-1">
        <li><strong>{{ __('Response Time') }}</strong> — {{ __('How quickly the first response must go out after the ticket is created.') }}</li>
        <li><strong>{{ __('Resolution Time') }}</strong> — {{ __('How quickly the ticket must be fully resolved.') }}</li>
    </ul>
    <p>{{ __('For example, an Enterprise client\'s Critical ticket might require a 1-hour response and a 4-hour resolution, while a Basic client\'s Low priority ticket might allow 24 hours to respond and 72 hours to resolve.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Setting Up Your Policies') }}</h3>
    <p>{{ __('Open the SLA page from the sidebar and seed the default policies to get a complete starting set covering every tier and priority. Then edit each tier to fine-tune its response and resolution targets to match the commitments you have made. Each tier is configured independently.') }}</p>
    <div class="mt-3 rounded-md border-l-4 border-indigo-400 bg-indigo-50 p-3 text-xs text-indigo-900">
        {{ __('Important: While SLA management is enabled, a ticket can only be given a priority if a matching policy exists for its client tier and priority. Seeding the defaults guarantees full coverage — if you ever see a "no SLA policy defined" message, seed or add the missing policy.') }}
    </div>
    @include('tutorials.partials._figure', ['img' => 'sla-policies.png', 'alt' => __('SLA policies page'), 'caption' => __('The SLA Policies page, where you seed defaults and tune response and resolution targets per tier.')])

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Hold Time & Fair Measurement') }}</h3>
    <p>{{ __('When a ticket is set to On Hold — typically while waiting on the client or a third party — the SLA clock pauses, and it resumes when you take the ticket off hold. Because hold time is excluded, your agents are never penalized for delays outside their control, and the response and resolution figures in reports reflect actual working time.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Breach Warnings') }}</h3>
    <p>{{ __('TechDesk checks open tickets for approaching deadlines every 15 minutes. When a ticket nears its response or resolution target, the assigned agent gets an email warning, giving the team a chance to act before the SLA is actually breached.') }}</p>
    <p class="text-xs text-amber-600">{{ __('Note: Breach warning emails require email notifications to be enabled for your workspace.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Monitoring Compliance') }}</h3>
    <p>{{ __('The SLA Compliance report shows how well you are meeting your targets: overall compliance rates, average response and resolution times (hold time excluded), and a breakdown by priority and client tier so you can see exactly where you are slipping. Review it regularly and adjust either your staffing or your targets accordingly.') }}</p>
    @include('tutorials.partials._figure', ['img' => 'sla-compliance.png', 'alt' => __('SLA compliance report'), 'caption' => __('Track met versus missed response and resolution targets over any date range.')])

    <div class="mt-6 rounded-md border-l-4 border-emerald-400 bg-emerald-50 p-3 text-xs text-emerald-900">
        {{ __('Best practice: Start from the seeded defaults, tighten targets only for the tiers and priorities where you can realistically deliver, and revisit them each quarter as your team and volume change.') }}
    </div>
</div>
