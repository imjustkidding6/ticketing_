@props([
    'column',
    'label',
    'align' => 'left',
])

@php
    $activeSort = request('sort');
    $activeDir = strtolower(request('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
    $isActive = $activeSort === $column;
    $nextDir = ($isActive && $activeDir === 'asc') ? 'desc' : 'asc';

    $queryString = array_merge(
        request()->except(['page', 'sort', 'direction']),
        ['sort' => $column, 'direction' => $nextDir]
    );
    $url = request()->url().'?'.http_build_query($queryString);

    $alignClass = match ($align) {
        'right' => 'text-right',
        'center' => 'text-center',
        default => 'text-left',
    };
@endphp

<th scope="col" {{ $attributes->merge(['class' => 'px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500 '.$alignClass]) }}>
    <a href="{{ $url }}" class="group inline-flex items-center gap-1 hover:text-gray-700 {{ $isActive ? 'text-gray-900' : '' }}">
        <span>{{ $label }}</span>
        <span class="flex flex-col leading-[0.4]">
            <svg class="h-2.5 w-2.5 {{ $isActive && $activeDir === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 10 10"><path d="M5 2L9 7H1z"/></svg>
            <svg class="h-2.5 w-2.5 -mt-0.5 {{ $isActive && $activeDir === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 10 10"><path d="M5 8L1 3h8z"/></svg>
        </span>
    </a>
</th>
