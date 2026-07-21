<div class="prose prose-sm max-w-none text-gray-700 dark:text-gray-300 dark:prose-invert">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Getting Started') }}</h2>
    <p>{{ __('Welcome to CliqueHA Nexus! This guide walks you through setting up your workspace from scratch so your team can start receiving and resolving support tickets. Work through the steps in order — each one builds on the last. Most of these are also tracked on the onboarding checklist on your dashboard.') }}</p>

    <div class="mt-3 rounded-md border-l-4 border-indigo-400 bg-indigo-50 p-3 text-xs text-indigo-900 dark:border-indigo-500 dark:bg-indigo-500/10 dark:text-indigo-300">
        {{ __('Tip: The dashboard checklist mirrors these steps and marks each one complete automatically as you go. You can dismiss it once you are set up and re-open it any time from this Tutorials section.') }}
    </div>
    @include('tutorials.partials._figure', ['img' => 'dashboard.png', 'alt' => __('Dashboard with onboarding checklist'), 'caption' => __('Your dashboard, with the onboarding checklist and at-a-glance performance stats.')])

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('1. Customize Your Workspace') }}</h3>
    <p>{{ __('Open Settings > General to set your company name, description, timezone, and date format. The timezone and date format control how every timestamp is displayed across the app for your whole team, so set these first.') }}</p>
    <p>{{ __('Then open Settings > Branding to upload your logo and choose your primary and accent colors, including separate dark-mode colors. Your branding appears in the sidebar, on the public portal, and on generated service-report PDFs.') }}</p>
    @include('tutorials.partials._figure', ['img' => 'settings-general.png', 'alt' => __('General settings page'), 'caption' => __('Settings > General — set your company details, timezone, and date format.')])

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('2. Review Your Departments') }}</h3>
    <p>{{ __('We created a set of default departments for you: Human Resource, Procurement, Technical Software, Technical Hardware, Sales, Customer Service, and Others. Each department groups related tickets, has its own categories, and can have agents assigned to it.') }}</p>
    <p>{{ __('Visit the Departments page to rename, add, or remove departments so they match how your organization actually works. Keep the list focused — a handful of clear departments is far easier to route than dozens of overlapping ones.') }}</p>
    <p class="text-xs text-amber-600 dark:text-amber-400">{{ __('Note: Adding, renaming, and removing departments is an Enterprise feature. Starter and Business workspaces use the default department set.') }}</p>
    @include('tutorials.partials._figure', ['img' => 'departments.png', 'alt' => __('Departments page'), 'caption' => __('The Departments page, showing the default departments and their codes.')])

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('3. Create Ticket Categories') }}</h3>
    <p>{{ __('Categories classify the type of request within a department. Go to Categories and add entries such as "Bug Report", "Feature Request", "Account Access", or "General Inquiry". Assign each category to a department and give it a sort order to control how it appears in dropdowns.') }}</p>
    <p>{{ __('Thoughtful categories make reporting far more useful later — your Category report is only as insightful as the categories you define here.') }}</p>
    @include('tutorials.partials._figure', ['img' => 'categories.png', 'alt' => __('Categories page'), 'caption' => __('Categories classify requests within each department.')])

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('4. Add Products (Optional)') }}</h3>
    <p>{{ __('If you support specific products or services, add them under Products. Tickets can then be linked to one or more products, which powers the Product report and helps you spot which offerings generate the most support load.') }}</p>
    @include('tutorials.partials._figure', ['img' => 'products.png', 'alt' => __('Products and services page'), 'caption' => __('Products & Services you support, which tickets can be linked to.')])

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('5. Invite Your Team') }}</h3>
    <p>{{ __('Go to User Management > Create to add your agents, managers, and admins. Every user gets a role that determines what they can do:') }}</p>
    <ul class="mt-2 list-disc pl-5 space-y-1">
        <li><strong class="dark:text-gray-100">{{ __('Agent') }}</strong> — {{ __('Works assigned tickets: updates status, adds replies and internal notes, and completes tasks.') }}</li>
        <li><strong class="dark:text-gray-100">{{ __('Manager') }}</strong> — {{ __('Everything an agent can do, plus assigning tickets to others, managing clients, and viewing reports.') }}</li>
        <li><strong class="dark:text-gray-100">{{ __('Admin') }}</strong> — {{ __('Full access to settings, roles, departments, SLA policies, and every management feature.') }}</li>
    </ul>
    <p class="text-xs text-amber-600 dark:text-amber-400">{{ __('Note: The number of users you can add is limited by your license seat count. On the Enterprise plan you can also define custom roles with their own permission sets.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('6. Define Your SLA Policies') }}</h3>
    <p>{{ __('If your plan includes SLA management, set up your response and resolution targets before you start prioritizing tickets. The quickest way is to seed the default policies from the SLA page and then fine-tune them. See the SLA Management guide for the full walkthrough.') }}</p>
    <p class="text-xs text-amber-600 dark:text-amber-400">{{ __('Note: When SLA management is enabled, a matching SLA policy must exist for a ticket\'s client tier and priority. Seeding the defaults covers every combination so ticket creation is never blocked.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('7. Create Your First Ticket') }}</h3>
    <p>{{ __('From the Tickets page, click "Create Ticket". Choose a client, enter a subject and description, pick a department, category, and priority, and optionally assign it to an agent. Walking one ticket through from creation to resolution is the fastest way to learn the workflow — the Managing Tickets guide covers every step.') }}</p>
    @include('tutorials.partials._figure', ['img' => 'ticket-create.png', 'alt' => __('Create ticket form'), 'caption' => __('The Create Ticket form, where you choose the client, department, category, and priority.')])

    <h3 class="mt-6 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('8. Share Your Public Portal') }}</h3>
    <p>{{ __('On Business and Enterprise plans, your workspace has a public portal where clients can submit and track tickets without an account. Its address is your workspace slug. Share that link, or add it to your website and email signatures, so requests flow straight into CliqueHA Nexus.') }}</p>
    <p class="text-xs text-amber-600 dark:text-amber-400">{{ __('Note: The public portal is available on Business and Enterprise plans.') }}</p>

    <div class="mt-6 rounded-md border-l-4 border-emerald-400 bg-emerald-50 p-3 text-xs text-emerald-900 dark:border-emerald-500 dark:bg-emerald-500/10 dark:text-emerald-300">
        {{ __('You are set up! Next, read Managing Tickets to master the day-to-day workflow, or SLA Management to keep your team accountable to response and resolution targets.') }}
    </div>
</div>