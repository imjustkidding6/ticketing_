<div class="prose prose-sm max-w-none text-gray-700 dark:text-gray-300 dark:prose-invert">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
        {{ __('Settings & Configuration') }}
    </h2>

    <p>
        {{ __('Settings are organized into tabs, reachable from the Settings page in the sidebar. Which tabs you see depends on your plan and your role — settings management is restricted to admins and owners.') }}
    </p>

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">
        {{ __('General') }}
    </h3>

    <p>
        {{ __('Set your company name and description, your timezone, and your date format. Timezone and date format govern how every date and time is shown across the app for everyone in your workspace, so it is worth getting these right early.') }}
    </p>

    @include('tutorials.partials._figure', [
        'img' => 'settings-general.png',
        'alt' => __('General settings'),
        'caption' => __('The Settings page, organized into tabs across the top.')
    ])

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">
        {{ __('Tickets') }}
    </h3>

    <p>
        {{ __('Control default ticket behavior, including:') }}
    </p>

    <ul class="mt-2 list-disc pl-5 space-y-1">
        <li>{{ __('The default priority applied to new tickets.') }}</li>
        <li>{{ __('Whether agents are allowed to self-assign open tickets.') }}</li>
        <li>{{ __('The ticket number prefix and format.') }}</li>
        <li>{{ __('Auto-close behavior for stale tickets.') }}</li>
    </ul>

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">
        {{ __('Notifications') }}
    </h3>

    <p>
        {{ __('Choose which events send email — ticket creation, assignment, status changes, and SLA breach warnings — for both your agents and your clients. You can use the system mail server or configure your own per-workspace SMTP credentials, and send a test email to confirm they work before going live.') }}
    </p>

    <p class="text-xs text-amber-600 dark:text-amber-400">
        {{ __('Available on Business and Enterprise plans.') }}
    </p>

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">
        {{ __('Branding') }}
    </h3>

    <p>
        {{ __('Upload your logo and set primary and accent colors, with separate choices for dark mode. Your branding flows through to the sidebar, the public portal, and service-report PDFs, giving clients a consistent experience.') }}
    </p>

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">
        {{ __('Service Report') }}
    </h3>

    <p>
        {{ __('Tailor the generated PDF service reports: upload a dedicated report logo, set header and footer text, and choose which fields appear. These reports document the work done on a ticket for your client.') }}
    </p>

    <p class="text-xs text-amber-600 dark:text-amber-400">
        {{ __('Available on Business and Enterprise plans.') }}
    </p>

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">
        {{ __('SLA Policies') }}
    </h3>

    <p>
        {{ __('Define the response and resolution targets that hold your team accountable. Policies are managed per client tier, and you can seed a full set of sensible defaults in one click. The SLA Management guide covers this in depth.') }}
    </p>

    <p class="text-xs text-amber-600 dark:text-amber-400">
        {{ __('Available on Business and Enterprise plans.') }}
    </p>

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">
        {{ __('Roles & Permissions') }}
    </h3>

    <p>
        {{ __('Every workspace ships with three default roles — Agent, Manager, and Admin — each with a sensible set of permissions. On the Enterprise plan you can create custom roles and grant exactly the permissions you want, such as "view tickets", "manage clients", or "manage settings". Owners always have full access and bypass permission checks.') }}
    </p>

    <p class="text-xs text-amber-600 dark:text-amber-400">
        {{ __('Note: Custom roles are an Enterprise feature; the default roles are available on every plan.') }}
    </p>

    @include('tutorials.partials._figure', [
        'img' => 'roles.png',
        'alt' => __('Roles and permissions'),
        'caption' => __('The Roles page, where Enterprise workspaces define custom roles and their permissions.')
    ])

    <div class="mt-3 rounded-md border-l-4 border-indigo-400 bg-indigo-50 p-3 text-xs text-indigo-900 dark:border-indigo-500 dark:bg-indigo-500/10 dark:text-indigo-300">
        {{ __('Tip: After changing your plan or permissions, give it a moment — feature access is briefly cached, so a change may take a few minutes to appear for everyone.') }}
    </div>
</div>