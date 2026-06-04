{{-- Reusable screenshot figure for tutorials. Params: $img (filename in public/images/tutorials), $alt, optional $caption --}}
<figure class="my-4 overflow-hidden rounded-lg border border-gray-200 shadow-sm">
    <img src="{{ asset('images/tutorials/' . $img) }}" alt="{{ $alt }}" class="block w-full" loading="lazy">
    @isset($caption)
        <figcaption class="border-t border-gray-100 bg-gray-50 px-3 py-2 text-xs text-gray-500">{{ $caption }}</figcaption>
    @endisset
</figure>
