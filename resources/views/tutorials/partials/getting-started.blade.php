<div class="prose prose-sm max-w-none text-gray-700">
    <h2 class="text-lg font-semibold text-gray-900">{{ __('Getting Started') }}</h2>
    <p>{{ __('Welcome to TechDesk! Follow these steps to set up your workspace and start managing support tickets.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('1. Customize Your Workspace') }}</h3>
    <p>{{ __('Head to Settings > General to update your company name and description. Then visit Settings > Branding to upload your company logo and set your brand colors. Your logo will appear in the sidebar and on the public portal.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('2. Review Departments') }}</h3>
    <p>{{ __('We created default departments for you (Human Resource, Procurement, Technical Software, Technical Hardware, Sales, Customer Service, and Others). Navigate to the Departments page to rename, add, or remove departments to match your organization. Each department can have its own ticket categories and assigned agents.') }}</p>
    <p class="text-xs text-amber-600">{{ __('Note: Department management is available on the Enterprise plan. Starter and Business plan users work with the default departments.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('3. Create Ticket Categories') }}</h3>
    <p>{{ __('Categories help classify the type of issue or request within each department. Go to Categories and create categories like "Bug Report", "Feature Request", or "General Inquiry". Assign each category to a department and set a sort order for display.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('4. Invite Team Members') }}</h3>
    <p>{{ __('Go to User Management > Create to add agents and managers. Each user is assigned a role:') }}</p>
    <ul class="mt-2 list-disc pl-5 space-y-1">
        <li><strong>{{ __('Agent') }}</strong> — {{ __('Handles assigned tickets, updates statuses, adds comments.') }}</li>
        <li><strong>{{ __('Manager') }}</strong> — {{ __('Everything an agent can do, plus assign tickets, manage clients, and view reports.') }}</li>
        <li><strong>{{ __('Admin') }}</strong> — {{ __('Full access to all settings, roles, and management features.') }}</li>
    </ul>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('5. Create Your First Ticket') }}</h3>
    <p>{{ __('Click "Create Ticket" from the Tickets page. Fill in the subject, description, select a department, category, priority, and optionally assign it to an agent. This lets you experience the full ticket workflow — from creation through assignment to resolution.') }}</p>
</div>
