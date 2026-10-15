{{--
    The "information needed" list. ONE partial, three surfaces (internal create form,
    public submit form, ticket page), so the wording cannot drift between them.

    It is DERIVED, never hand-maintained: `TicketTechnicalFields::missing()` decides what is
    absent and the declaration supplies the reason, so a field added to the declaration
    appears here with no view edit.

    Params:
      $missing            list from TicketTechnicalFields::missing() — [] means satisfied
      $editUrl            nullable; renders the "Add these details" link when present
      $departmentWarning  nullable string; follow-up 40's sentence, as a row
      $publicTone         bool; the stranger-facing wording — rubric inputs only, short
                          labels, never the word "rubric", never an error style
--}}
@php
    $editUrl = $editUrl ?? null;
    $departmentWarning = $departmentWarning ?? null;
    $publicTone = $publicTone ?? false;
    $rows = $publicTone
        ? array_values(array_filter($missing, fn ($row) => $row['rubric_input']))
        : $missing;
@endphp
{{-- On the public surface this is invitational and NEVER an error style: a stranger who
     left a question blank made a legitimate choice. --}}
<div class="rounded-lg border p-3 text-xs {{ $publicTone ? 'border-gray-200 bg-white' : 'border-amber-200 bg-amber-50' }}" data-information-needed>
    @if($rows === [] && $departmentWarning === null)
        {{-- A block that disappears when satisfied is indistinguishable from a block that
             broke, and the engineer who filled the fields gets no confirmation that it
             counted. So the satisfied state is one positive line, not nothing. --}}
        <p class="text-emerald-800">
            @if($publicTone)
                {{ __('Thanks — that\'s everything we need to prioritise this.') }}
            @else
                {{ __('All the technical details we use to judge priority are on file.') }}
            @endif
        </p>
    @else
        <p class="font-semibold {{ $publicTone ? 'text-gray-700' : 'text-amber-900' }}">
            @if($publicTone)
                {{ __('Still helpful if you know them') }}
            @else
                {{ __('Information still needed') }}
            @endif
        </p>
        <ul class="mt-1.5 space-y-1 {{ $publicTone ? 'text-gray-600' : 'text-amber-800' }}">
            @foreach($rows as $row)
                <li>
                    <span class="font-medium">{{ __($row['label']) }}</span>
                    @unless($publicTone)
                        — {{ __($row['why']) }}
                    @endunless
                </li>
            @endforeach
            @if($departmentWarning !== null)
                <li>{{ $departmentWarning }}</li>
            @endif
        </ul>
        @if($editUrl !== null)
            <a href="{{ $editUrl }}" class="mt-2 inline-block font-medium text-indigo-700 hover:text-indigo-900">{{ __('Add these details') }}</a>
        @endif
    @endif
</div>
