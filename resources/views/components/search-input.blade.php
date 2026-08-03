@props([
    'placeholder' => 'Search...',
    'model' => null,
    'name' => null,
    'value' => null,
    'id' => null,
    'readonly' => false,
    'wrapperClass' => '',
    'inputClass' => '',
])

<div class="relative flex-1 {{ $wrapperClass }}">
    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-[var(--text-secondary)] pointer-events-none z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
    </svg>
    <input 
        type="text" 
        @if($model) x-model="{{ $model }}" @endif
        @if($name) name="{{ $name }}" @endif
        @if($value !== null) value="{{ $value }}" @endif
        @if($id) id="{{ $id }}" @endif
        @if($readonly) readonly @endif
        placeholder="{{ $placeholder }}" 
        {{ $attributes->merge(['class' => 'global-search-input w-full h-10.5 pl-11 pr-4 rounded-xl text-sm bg-[var(--bg-input)] border border-[var(--border-soft)] text-[var(--text-primary)] placeholder-[var(--text-secondary)] focus:outline-none focus:border-[var(--primary)] focus:ring-1 focus:ring-[var(--primary)] transition-all ' . $inputClass]) }}
    />
</div>
