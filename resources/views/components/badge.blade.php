@props(['type' => 'default'])
@php
    $colors = [
        // Ticket statuses
        'open' => 'bg-blue-100 text-blue-800',
        'assigned' => 'bg-blue-100 text-blue-800',
        'in_progress' => 'bg-purple-100 text-purple-800',
        'on_hold' => 'bg-yellow-100 text-yellow-800',
        'closed' => 'bg-gray-100 text-gray-800',
        'cancelled' => 'bg-red-100 text-red-800',
        // Priorities
        'low' => 'bg-green-100 text-green-800',
        'medium' => 'bg-yellow-100 text-yellow-800',
        'high' => 'bg-orange-100 text-orange-800',
        'critical' => 'bg-red-100 text-red-800',
        // Task statuses
        'pending' => 'bg-gray-100 text-gray-800',
        'completed' => 'bg-green-100 text-green-800',
        // Boolean states
        'active' => 'bg-green-100 text-green-800',
        'inactive' => 'bg-gray-100 text-gray-800',
        'default_tag' => 'bg-blue-100 text-blue-800',
        // Client tiers
        'basic' => 'bg-gray-100 text-gray-800',
        'premium' => 'bg-blue-100 text-blue-800',
        'enterprise' => 'bg-purple-100 text-purple-800',
        // Severity
        'overdue' => 'bg-red-100 text-red-800',
        'success' => 'bg-green-100 text-green-800',
        'warning' => 'bg-yellow-100 text-yellow-800',
        'info' => 'bg-blue-100 text-blue-800',
        'default' => 'bg-gray-100 text-gray-800',
    ];
    $colorClass = $colors[$type] ?? $colors['default'];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {$colorClass}"]) }}>
    {{ $slot }}
</span>
