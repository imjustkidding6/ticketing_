<div class="prose prose-sm max-w-none text-gray-700">
    <h2 class="text-lg font-semibold text-gray-900">{{ __('Client Management') }}</h2>
    <p>{{ __('Clients are the people who submit and track tickets. TechDesk supports both internal management and a public-facing portal.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Adding Clients') }}</h3>
    <p>{{ __('Navigate to Clients and click "Create Client". Enter the client\'s name, email, phone, company, and optionally assign them a tier (Standard, Premium, VIP) for SLA prioritization. Clients are automatically created when they submit tickets through the public portal.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Client Tiers') }}</h3>
    <p>{{ __('Assign tiers to clients to control SLA priorities:') }}</p>
    <ul class="mt-2 list-disc pl-5 space-y-1">
        <li><strong>{{ __('Standard') }}</strong> — {{ __('Default tier with standard response times.') }}</li>
        <li><strong>{{ __('Premium') }}</strong> — {{ __('Faster response and resolution targets.') }}</li>
        <li><strong>{{ __('VIP') }}</strong> — {{ __('Highest priority with the shortest SLA windows.') }}</li>
    </ul>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Public Portal') }}</h3>
    <p>{{ __('Your tenant has a public-facing portal where clients can:') }}</p>
    <ul class="mt-2 list-disc pl-5 space-y-1">
        <li>{{ __('Submit new tickets without logging in (guest submission).') }}</li>
        <li>{{ __('Track tickets using their ticket number and email, or via a unique tracking link.') }}</li>
        <li>{{ __('View knowledge base articles (Enterprise plan).') }}</li>
    </ul>
    <p>{{ __('The portal URL is your workspace slug — share it with clients so they can submit and track their requests.') }}</p>
    <p class="text-xs text-amber-600">{{ __('The public portal is available on Business and Enterprise plans.') }}</p>

    <h3 class="mt-6 text-base font-semibold text-gray-900">{{ __('Client Comments') }}</h3>
    <p>{{ __('On the Enterprise plan, clients can reply to their tickets through the public tracking page. These comments appear in the ticket timeline alongside agent notes, keeping all communication in one place.') }}</p>
    <p class="text-xs text-amber-600">{{ __('Available on the Enterprise plan.') }}</p>
</div>
