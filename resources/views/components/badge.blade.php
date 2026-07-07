@props(['type' => 'default'])
@php
    $colors = [
        // Ticket statuses
        'open' => 'bg-blue-500 text-white dark:bg-blue-500 dark:text-white',
        'assigned' => 'bg-indigo-500 text-white dark:bg-indigo-500 dark:text-white',
        'in_progress' => 'bg-purple-500 text-white dark:bg-purple-500 dark:text-white',
        'on_hold' => 'bg-amber-500 text-white dark:bg-amber-500 dark:text-white',
        'closed' => 'bg-emerald-500 text-white dark:bg-emerald-500 dark:text-white',
        'cancelled' => 'bg-red-500 text-white dark:bg-red-500 dark:text-white',

        // Priorities
        'low'      => 'bg-green-500 text-white dark:bg-green-500 dark:text-white',
        'medium'   => 'bg-yellow-500 text-white dark:bg-yellow-500 dark:text-white',
        'high' => 'bg-orange-500 text-white dark:bg-orange-500 dark:text-white',
        'critical' => 'bg-red-500 text-white dark:bg-red-500 dark:text-white',

        // Task statuses
        'pending' => 'bg-gray-500 text-white dark:bg-gray-500 dark:text-white',
        'completed' => 'bg-green-500 text-white dark:bg-green-500 dark:text-white',

        // Boolean states
        'active' => 'bg-green-500 text-white dark:bg-green-500 dark:text-white',
        'inactive' => 'bg-gray-500 text-white dark:bg-gray-500 dark:text-white',
        'default_tag' => 'bg-blue-500 text-white dark:bg-blue-500 dark:text-white',

        // Client tiers
        'basic' => 'bg-gray-500 text-white dark:bg-gray-500 dark:text-white',
        'premium' => 'bg-blue-500 text-white dark:bg-blue-500 dark:text-white',
        'enterprise' => 'bg-purple-500 text-white dark:bg-purple-500 dark:text-white',

        // Severity
        'overdue' => 'bg-red-500 text-white dark:bg-red-500 dark:text-white',
        'success' => 'bg-green-500 text-white dark:bg-green-500 dark:text-white',
        'warning' => 'bg-yellow-500 text-white dark:bg-yellow-500 dark:text-white',
        'info' => 'bg-blue-500 text-white dark:bg-blue-500 dark:text-white',
        'default' => 'bg-gray-500 text-white dark:bg-gray-500 dark:text-white',
    ];
    $colorClass = $colors[$type] ?? $colors['default'];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {$colorClass}"]) }}>
    {{ $slot }}
</span>
