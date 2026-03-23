@props(['message', 'actionUrl' => null, 'actionLabel' => null, 'colspan' => null])
@if($colspan)
<tr>
    <td colspan="{{ $colspan }}" class="px-6 py-12 text-center">
@else
<div class="px-6 py-12 text-center">
@endif
        @if(isset($icon))
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                {{ $icon }}
            </div>
        @endif
        <p class="text-sm text-gray-500">{{ $message }}</p>
        @if($actionUrl)
            <a href="{{ $actionUrl }}" class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ $actionLabel }}
            </a>
        @endif
@if($colspan)
    </td>
</tr>
@else
</div>
@endif
