<div class="prose prose-sm max-w-none text-gray-700 dark:text-gray-300 dark:prose-invert">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
        {{ __('Client Management') }}
    </h2>

    <p>
        {{ __('Clients are the people and companies who submit and track tickets. CliqueHA Nexus lets you manage them internally and gives them a public-facing portal to self-serve.') }}
    </p>

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-white">
        {{ __('Adding Clients') }}
    </h3>

    <p>
        {{ __('Go to Clients and click "Create Client". Enter their name, email, phone, and company, and optionally set a tier for SLA prioritization. Clients are also created automatically the first time someone submits a ticket through the public portal, so your client list grows on its own.') }}
    </p>

    <p>
        {{ __('Each client has a profile showing their ticket history, making it easy to see context before you respond.') }}
    </p>

    @include('tutorials.partials._figure', [
        'img' => 'clients.png',
        'alt' => __('Clients list'),
        'caption' => __('The Clients page, where you add clients and filter them by tier.')
    ])

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-white">
        {{ __('Client Tiers') }}
    </h3>

    <p>
        {{ __("A client's tier, combined with the ticket priority, decides which SLA policy applies — so higher tiers can get faster response and resolution targets:") }}
    </p>

    <ul class="mt-2 list-disc pl-5 space-y-1">
        <li>
            <strong>{{ __('Basic') }}</strong> —
            {{ __('The default tier, with your standard response times.') }}
        </li>

        <li>
            <strong>{{ __('Premium') }}</strong> —
            {{ __('Faster response and resolution targets for important accounts.') }}
        </li>

        <li>
            <strong>{{ __('Enterprise') }}</strong> —
            {{ __('Your highest priority, with the shortest SLA windows.') }}
        </li>
    </ul>

    <p class="text-xs text-amber-600 dark:text-amber-400">
        {{ __('Note: Tiers only change behavior when SLA management is enabled and you have policies defined for each tier. See the SLA Management guide.') }}
    </p>

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-white">
        {{ __('Client Portal Accounts') }}
    </h3>

    <p>
        {{ __('A client can be linked to a portal user account so they can sign in to view their tickets. Linked clients receive client-facing email notifications — for example when their ticket is created or its status changes — on plans that include email notifications.') }}
    </p>

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-white">
        {{ __('The Public Portal') }}
    </h3>

    <p>
        {{ __('Your workspace has a public-facing portal, reachable at your workspace slug, where clients can:') }}
    </p>

    <ul class="mt-2 list-disc pl-5 space-y-1">
        <li>{{ __('Submit new tickets without logging in (guest submission).') }}</li>
        <li>{{ __('Track a ticket using its number and their email, or via the unique tracking link sent to them.') }}</li>
        <li>{{ __('Browse knowledge base articles to find answers before they ever open a ticket.') }}</li>
    </ul>

    <p>
        {{ __('Share the portal link on your website, in email signatures, and on invoices so requests flow straight into CliqueHA Nexus.') }}
    </p>

    <p class="text-xs text-amber-600 dark:text-amber-400">
        {{ __('Note: The public portal is available on Business and Enterprise plans. The knowledge base is an Enterprise feature.') }}
    </p>

    @include('tutorials.partials._figure', [
        'img' => 'portal-submit.png',
        'alt' => __('Public ticket submission form'),
        'caption' => __('The public portal submission form your clients use to raise a ticket without an account.')
    ])

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-white">
        {{ __('Client Replies') }}
    </h3>

    <p>
        {{ __("On the Enterprise plan, clients can reply to their tickets directly from the public tracking page. Their replies appear in the ticket timeline alongside your agents' notes, keeping every part of the conversation in one place.") }}
    </p>

    <p class="text-xs text-amber-600 dark:text-amber-400">
        {{ __('Available on the Enterprise plan.') }}
    </p>

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-white">
        {{ __('Knowledge Base') }}
    </h3>

    <p>
        {{ __('Publish help articles, organized into categories, on your portal so clients can solve common problems themselves. A strong knowledge base reduces repetitive tickets and frees your team for the issues that really need them.') }}
    </p>

    <p class="text-xs text-amber-600 dark:text-amber-400">
        {{ __('Available on the Enterprise plan.') }}
    </p>

    <div class="mt-6 rounded-md border-l-4 border-indigo-400 dark:border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 p-3 text-xs text-indigo-900 dark:text-indigo-200">
        {{ __('Tip: Keep client email addresses accurate — tracking links, notifications, and portal sign-in all depend on them.') }}
    </div>
</div>