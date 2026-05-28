<div class="prose prose-sm max-w-none text-gray-700">
    <h2 class="text-lg font-semibold text-gray-900">{{ __('Settings & Configuration') }}</h2>
    <p>{{ __('TechDesk settings are organized into tabs. Access them from the Settings page in the sidebar.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('General Settings') }}</h3>
    <p>{{ __('Configure your company name, description, timezone, and date format. These settings affect how dates and times are displayed throughout the application for all users in your workspace.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Ticket Settings') }}</h3>
    <p>{{ __('Control default ticket behavior:') }}</p>
    <ul class="mt-2 list-disc pl-5 space-y-1">
        <li>{{ __('Default priority for new tickets.') }}</li>
        <li>{{ __('Whether agents can self-assign tickets.') }}</li>
        <li>{{ __('Ticket number prefix and format.') }}</li>
        <li>{{ __('Auto-close settings for stale tickets.') }}</li>
    </ul>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Notification Settings') }}</h3>
    <p>{{ __('Configure email notifications for ticket events: creation, assignment, status changes, and SLA breach warnings. You can set up per-tenant SMTP credentials or use the system default.') }}</p>
    <p class="text-xs text-amber-600">{{ __('Available on Business and Enterprise plans.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Branding') }}</h3>
    <p>{{ __('Upload your company logo and set primary/accent colors. These are used in the sidebar, public portal, and service report PDFs. You can also set separate colors for dark mode.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Roles & Permissions') }}</h3>
    <p>{{ __('On the Enterprise plan, you can create custom roles beyond the default Agent, Manager, and Admin roles. Each role can be configured with specific permissions like "view tickets", "manage clients", "manage settings", and more.') }}</p>
    <p class="text-xs text-amber-600">{{ __('Custom roles are available on the Enterprise plan.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Service Report Settings') }}</h3>
    <p>{{ __('Customize the look of generated PDF service reports: upload a separate logo, set header/footer text, and configure which fields appear on the report.') }}</p>
    <p class="text-xs text-amber-600">{{ __('Available on Business and Enterprise plans.') }}</p>
</div>
