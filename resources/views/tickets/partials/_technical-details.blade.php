{{--
    The declared technical detail fieldset, rendered by LOOPING
    App\Support\TicketTechnicalFields::FIELDS — so a field added to the declaration appears
    on every form with no view edit, and the three required selects are required because the
    declaration says so and not because this template remembered to say `required`.

    One partial, three forms (ticket create, ticket edit, public submit). The only
    differences are the parameters.

    Params:
      $values     array<string, string|null>  current values, already old()-merged
      $required   bool  honour `required_internally` (internal create form only)
      $emptyLabel string  the placeholder option's label
      $idPrefix   string  prepended to element ids, '' on the forms _ai-assist reads by id
--}}
@php
    $values = $values ?? [];
    $required = $required ?? false;
    $emptyLabel = $emptyLabel ?? __('Select…');
    $idPrefix = $idPrefix ?? '';
@endphp
<div class="space-y-4">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        @foreach(\App\Support\TicketTechnicalFields::FIELDS as $key => $field)
            @continue($field['shape'] !== 'select')
            @php $isRequired = $required && $field['required_internally']; @endphp
            <div>
                <label for="{{ $idPrefix.$key }}" class="block text-sm font-medium text-gray-700">
                    {{ __($field['label']) }}
                    @if($isRequired)
                        <span class="text-red-500">*</span>
                    @endif
                </label>
                <select name="{{ $key }}" id="{{ $idPrefix.$key }}" @if($isRequired) required @endif
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    {{-- A real, named choice rather than a dash, and never an `unknown`
                         option VALUE: blank is unknown, it validates as `nullable`, and it
                         emits no line to the priority rubric at all. A stored `unknown`
                         would be a stated fact about the state of knowledge, which a model
                         reasons about separately and which breaks PRIORITY_UNCERTAINTY's
                         downward resolution. --}}
                    <option value="">{{ $emptyLabel }}</option>
                    @foreach($field['options'] as $value => $label)
                        <option value="{{ $value }}" {{ ($values[$key] ?? null) === $value ? 'selected' : '' }}>{{ __($label) }}</option>
                    @endforeach
                </select>
                @error($key) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @endforeach
    </div>

    @foreach(\App\Support\TicketTechnicalFields::FIELDS as $key => $field)
        @continue($field['shape'] !== 'textarea')
        <div>
            <label for="{{ $idPrefix.$key }}" class="block text-sm font-medium text-gray-700">
                {{ __($field['label']) }} <span class="font-normal text-gray-400">({{ __('optional') }})</span>
            </label>
            <textarea name="{{ $key }}" id="{{ $idPrefix.$key }}" rows="3" maxlength="{{ $field['cap'] }}"
                      placeholder="{{ __($field['why']) }}"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ $values[$key] ?? '' }}</textarea>
            @error($key) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    @endforeach
</div>
