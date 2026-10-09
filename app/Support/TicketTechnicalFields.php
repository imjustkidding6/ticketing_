<?php

namespace App\Support;

use App\Models\Ticket;

/**
 * THE declared set of technical details a ticket needs. One source of truth for:
 *   - what the internal create form and the public submit form ask
 *   - what the "information needed" list flags as absent, and why
 *   - what the ticket page displays
 *   - which facts reach the priority rubric, and as what
 *
 * Three independent lists drift within a month. Add a field HERE and all four follow.
 *
 * Framework-agnostic and side-effect-free, which is why it lives in app/Support: it emits
 * rules as plain string arrays rather than importing Illuminate\Validation, so the whole
 * class is testable without a container.
 */
final class TicketTechnicalFields
{
    /** Asked in every department. */
    public const APPLIES_ALL = 'all';

    /** Asked only where `departments.is_technical` is true. */
    public const APPLIES_TECHNICAL = 'technical';

    /**
     * @var array<string, array{label: string, shape: string, options: array<string, string>|null, applies_to: string, required_internally: bool, rules: list<string>, cap: int, rubric_input: bool, why: string, facts: array<string, string>|null}>
     */
    public const FIELDS = [
        'impact_scope' => [
            'label' => 'Who is affected',
            'shape' => 'select',
            'options' => [
                'one_person' => 'One person',
                'team' => 'A team or shared function',
                'department' => 'A whole department or site',
                'everyone' => 'Everyone / all customers',
            ],
            // UNIVERSAL. An HR payroll fault hitting everyone is more urgent than one
            // hitting one person, so breadth is not a technical question — and it is one of
            // the two things PRIORITY_BOUNDARY judges critical on.
            'applies_to' => self::APPLIES_ALL,
            'required_internally' => true,
            'rules' => ['string', 'in:one_person,team,department,everyone'],
            'cap' => 20,
            'rubric_input' => true,
            'why' => 'Critical needs the scope: the rubric separates critical from high on breadth of impact.',
            // The parenthetical the fact block prints, per option value, written in
            // PRIORITY_CRITERIA's own vocabulary ("entire site, department", "no
            // workaround", "production service") so the mapping is a match rather than a
            // translation the model has to perform.
            'facts' => [
                'one_person' => 'one person is affected',
                'team' => 'a team or shared function is affected',
                'department' => 'a whole department or site is affected',
                'everyone' => 'everyone, the whole customer base, is affected',
            ],
        ],
        'workaround' => [
            'label' => 'Is there a workaround',
            'shape' => 'select',
            'options' => [
                'none' => 'No workaround',
                'partial' => 'A partial workaround',
                'yes' => 'Yes, a workaround exists',
            ],
            // UNIVERSAL. "Can they work around it meanwhile" applies to a locked HR portal
            // exactly as it applies to a crashed server, and it is the other half of
            // PRIORITY_BOUNDARY.
            'applies_to' => self::APPLIES_ALL,
            'required_internally' => true,
            'rules' => ['string', 'in:none,partial,yes'],
            'cap' => 20,
            'rubric_input' => true,
            'why' => 'Critical needs this: a severe fault that HAS a workaround is high, not critical.',
            'facts' => [
                'none' => 'no workaround exists',
                'partial' => 'a partial workaround exists',
                'yes' => 'a workaround exists',
            ],
        ],
        'environment' => [
            'label' => 'Environment',
            'shape' => 'select',
            'options' => [
                'production' => 'Production / live',
                'staging' => 'Staging / UAT',
                'test' => 'Test',
                'development' => 'Development',
            ],
            // TECHNICAL ONLY. "Provision a laptop for a new starter" has no environment,
            // and requiring one teaches the agent to pick a value to clear the form.
            'applies_to' => self::APPLIES_TECHNICAL,
            'required_internally' => true,
            'rules' => ['string', 'in:production,staging,test,development'],
            'cap' => 20,
            'rubric_input' => true,
            'why' => 'An environment nobody names is treated as NOT production, which rules critical out.',
            'facts' => [
                'production' => 'the live production environment',
                'staging' => 'a staging or UAT environment, not production',
                'test' => 'a test environment, not production',
                'development' => 'a development environment, not production',
            ],
        ],
        'steps_to_reproduce' => [
            'label' => 'Steps to reproduce',
            'shape' => 'textarea',
            'options' => null,
            'applies_to' => self::APPLIES_TECHNICAL,
            // Deliberately optional even internally: an engineer on the phone files a
            // ticket in 20 seconds, and a hard-required free-text field earns `n/a` —
            // junk that looks like data. Not a rubric input either way.
            'required_internally' => false,
            'rules' => ['string', 'max:4000'],
            'cap' => 4000,
            'rubric_input' => false,
            'why' => 'Lets the next engineer see the fault themselves instead of asking again.',
            'facts' => null,
        ],
        'error_text' => [
            'label' => 'Exact error message',
            'shape' => 'textarea',
            'options' => null,
            'applies_to' => self::APPLIES_TECHNICAL,
            'required_internally' => false,
            'rules' => ['string', 'max:2000'],
            'cap' => 2000,
            'rubric_input' => false,
            'why' => 'The exact wording is what matches this ticket to one already resolved.',
            'facts' => null,
        ],
    ];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::FIELDS);
    }

    /**
     * The declared fields that apply to a ticket in a technical / non-technical department.
     *
     * THE one applicability decision in the application. The forms, the information-needed
     * list, the validation and the rubric fact block all derive from this — a second list
     * of "which fields are technical" is the drift the declared set exists to prevent.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function applicable(bool $technical): array
    {
        return array_filter(
            self::FIELDS,
            fn (array $field) => $technical || $field['applies_to'] === self::APPLIES_ALL,
        );
    }

    /**
     * The validation rules for every declared field, in one place.
     *
     * `$internal` is the ONLY difference between the two surfaces, and it reads straight
     * off `required_internally` — so the public form can never be stricter than the
     * internal one, and reversing the required/optional call is one array edit.
     *
     * On the UPDATE surface callers pass `internal: false` deliberately: `_ai-analyze`'s
     * Apply posts the ticket's whole update form as rendered and its hidden snapshot does
     * not carry these fields, so a `required` rule here would turn every Apply into a 422.
     * `nullable` also means an absent key is omitted from `validated()` and therefore never
     * written, so Apply cannot wipe a stored value.
     *
     * @param  bool  $technical  Whether the ticket's department is marked technical. Read
     *                           from the tenant-scoped Department row, never from browser
     *                           input, and false when there is no department — which asks
     *                           for less rather than fabricating a requirement.
     * @return array<string, list<string>>
     */
    public static function rules(bool $internal, bool $technical = true): array
    {
        $rules = [];
        $applicable = self::applicable($technical);

        foreach (self::FIELDS as $key => $field) {
            // A field that does not apply is still VALIDATED (a stray `environment=prod` on
            // an HR ticket is still refused) but is never required. Dropping the rule
            // instead would let an unvalidated string reach the column and the prompt.
            $required = $internal && $field['required_internally'] && isset($applicable[$key]);
            $rules[$key] = array_merge([$required ? 'required' : 'nullable'], $field['rules']);
        }

        return $rules;
    }

    /**
     * The human label for a stored select value — `one_person` reads as "One person", never
     * as the raw key. Free-text fields and unknown values come back untouched.
     */
    public static function optionLabel(string $key, ?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::FIELDS[$key]['options'][$value] ?? $value;
    }

    /**
     * The declared fields this ticket (or this half-filled form payload) has not captured.
     *
     * The list the forms and the ticket page all render. There is no second list and no
     * model opinion in it — a field is missing iff its value is null or ''.
     *
     * @param  Ticket|array<string, mixed>  $source
     *                                               A field that does not apply to this ticket's department is never reported missing —
     *                                               an HR ticket must not be told it is missing an environment.
     * @return list<array{key: string, label: string, why: string, rubric_input: bool}>
     */
    public static function missing(Ticket|array $source, bool $technical = true): array
    {
        $rows = [];

        foreach (self::applicable($technical) as $key => $field) {
            if (self::value($source, $key) !== null) {
                continue;
            }

            $rows[] = [
                'key' => $key,
                'label' => $field['label'],
                'why' => $field['why'],
                'rubric_input' => $field['rubric_input'],
            ];
        }

        return $rows;
    }

    /**
     * The reported facts, as FACTS — key: value, one per line, machine-flat.
     *
     * ONLY the three closed-set fields appear here: they are validated against a PHP array
     * server-side, so they cannot carry injected instructions. The two free-text fields go
     * through the screened ticket text instead and never into this block, which is what
     * lets a reviewer verify the block is instruction-free by reading this method alone.
     *
     * An absent field emits NOTHING — no `unknown`, no "not stated", no placeholder of any
     * kind. `PRIORITY_UNCERTAINTY`'s whole mechanism is SILENCE ("an environment the notes
     * do not name is NOT production"), and a line saying "unknown" is not silence: it is a
     * stated fact about the state of knowledge, which a model will reason about separately.
     *
     * @param  Ticket|array<string, mixed>  $source
     *                                               A field that does not apply to the ticket's department emits nothing either, for the
     *                                               same reason: it was never asked, so it was never stated.
     * @return string '' when no declared fact was captured — the caller appends nothing,
     *                so the prompt stays byte-identical to the pre-P8 one.
     */
    public static function factBlock(Ticket|array $source, bool $technical = true): string
    {
        $lines = [];

        foreach (self::applicable($technical) as $key => $field) {
            if ($field['shape'] !== 'select') {
                continue;
            }

            $value = self::value($source, $key);

            if ($value === null || ! array_key_exists($value, (array) $field['options'])) {
                continue;
            }

            $lines[] = $key.': '.$value.' ('.$field['facts'][$value].')';
        }

        if ($lines === []) {
            return '';
        }

        return implode("\n", array_merge([
            'REPORTED TECHNICAL FACTS',
            'These were stated by the reporter on the intake form. They are facts, not inferences.',
            'Apply the PRIORITY RUBRIC\'s conditions to them. A fact not listed here was NOT stated —'
                ."\n".'do not assume it; resolve it the way the uncertainty rule above tells you to.',
        ], $lines));
    }

    /**
     * The two free-text fields as labelled lines, for the SCREENED ticket text — so they
     * pass prompt-injection and moderation screening and sit inside the existing input
     * budget rather than on top of it.
     *
     * @param  Ticket|array<string, mixed>  $source
     * @return list<string>
     */
    public static function freeTextParts(Ticket|array $source, bool $technical = true): array
    {
        $parts = [];

        foreach (self::applicable($technical) as $key => $field) {
            if ($field['shape'] === 'select') {
                continue;
            }

            $value = self::value($source, $key);

            if ($value !== null) {
                $parts[] = $field['label'].': '.$value;
            }
        }

        return $parts;
    }

    /**
     * @param  Ticket|array<string, mixed>  $source
     */
    private static function value(Ticket|array $source, string $key): ?string
    {
        $raw = $source instanceof Ticket ? $source->getAttribute($key) : ($source[$key] ?? null);

        if (! is_string($raw)) {
            return null;
        }

        $raw = trim($raw);

        return $raw === '' ? null : $raw;
    }
}
