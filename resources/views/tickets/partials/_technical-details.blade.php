{{--
    The declared detail fieldset, rendered by LOOPING
    App\Support\TicketTechnicalFields::applicable() — so a field added to the declaration
    appears on every form with no view edit, the required ones are required because the
    declaration says so, and APPLICABILITY ('all' vs 'technical') is decided in one place.

    One partial, three forms (ticket create, ticket edit, public submit). The only
    differences are the parameters.

    Params:
      $values      array<string, string|null>  current values, already old()-merged
      $required    bool    honour `required_internally` (internal create form only)
      $emptyLabel  string  the placeholder option's label
      $group       'all' | 'technical' | null  render only this applicability group
      $idPrefix    string  prepended to element ids, '' on the forms _ai-assist reads by id
--}}
@php
    use App\Support\TicketTechnicalFields;

    $values = $values ?? [];
    $required = $required ?? false;
    $emptyLabel = $emptyLabel ?? __('Select…');
    $group = $group ?? null;
    $requiredWhen = $requiredWhen ?? null;
    $idPrefix = $idPrefix ?? '';
    $fields = $group === null
        ? TicketTechnicalFields::FIELDS
        : array_filter(TicketTechnicalFields::FIELDS, fn ($f) => $f['applies_to'] === $group);
@endphp
<div class="space-y-4">
    @php $selects = array_filter($fields, fn ($f) => $f['shape'] === 'select'); @endphp
    @if($selects !== [])
        {{-- A literal class, not an interpolated one: Tailwind's JIT scanner cannot see a
             column count built at render time. --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            @foreach($selects as $key => $field)
                @php $isRequired = $required && $field['required_internally']; @endphp
                <div>
                    <label for="{{ $idPrefix.$key }}" class="block text-sm font-medium text-gray-700">
                        {{ __($field['label']) }}
                        @if($isRequired)
                            {{-- Bound, not static, when the enclosing block can be hidden:
                                 a hidden `required` select makes the browser refuse the
                                 whole submit with an unfocusable-control error, and the
                                 server has already dropped the rule for a group the
                                 department does not ask. --}}
                            <span class="text-red-500" @if($requiredWhen) x-show="{{ $requiredWhen }}" @endif>*</span>
                        @endif
                    </label>
                    <select name="{{ $key }}" id="{{ $idPrefix.$key }}"
                            @if($isRequired && $requiredWhen) x-bind:required="{{ $requiredWhen }}" @elseif($isRequired) required @endif
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        {{-- A real, named choice rather than a dash, and never an `unknown`
                             option VALUE: blank is unknown, it validates as `nullable`, and
                             it emits no line to the priority rubric at all. A stored
                             `unknown` would be a stated fact about the state of knowledge,
                             which a model reasons about separately and which breaks
                             PRIORITY_UNCERTAINTY's downward resolution. --}}
                        <option value="">{{ $emptyLabel }}</option>
                        @foreach($field['options'] as $value => $label)
                            <option value="{{ $value }}" {{ ($values[$key] ?? null) === $value ? 'selected' : '' }}>{{ __($label) }}</option>
                        @endforeach
                    </select>
                    @error($key) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>
    @endif

    @foreach($fields as $key => $field)
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
