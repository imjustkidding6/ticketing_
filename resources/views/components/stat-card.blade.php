@props(['label', 'value', 'color' => 'text-gray-900'])
<div class="rounded-xl bg-white p-6 shadow-sm">
    <div class="flex items-center justify-between">
        <div class="text-sm font-medium text-gray-500">{{ $label }}</div>
        @if(isset($icon))
            {{ $icon }}
        @endif
    </div>
    <div {{ $attributes->merge(['class' => "mt-2 text-3xl font-semibold {$color}"]) }}>{{ $value }}</div>
</div>
