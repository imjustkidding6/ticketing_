<?php

namespace App\Http\Requests;

use App\Models\SlaPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The whole week and the whole holiday list arrive in one POST and replace both child
 * sets — the same replace-all shape the tier editor already uses for its four policies.
 *
 * No foreign key is accepted. There is one calendar per workspace and the controller
 * resolves it server-side; if a later commit adds a picker, the id must be validated
 * with $this->tenantExists('sla_calendars') from ScopesRulesToTenant, because a plain
 * exists:sla_calendars,id queries the raw table and would accept another workspace's row.
 */
class UpdateSlaCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission is enforced in the controller via checkPermission(), matching every
        // other SLA action, so it is checked in one place for both GET and POST.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],

            // windows[{isoDay}][] = ['start' => '09:00', 'end' => '17:00'].
            // A day with no rows is a closed day; multiple rows on one day are a split
            // shift, which is why the schema has no start/end pair on a day row.
            // days[{isoDay}] = 1 when support is available that day. Its own control, so
            // "we are closed at the weekend" is a visible state rather than an inference
            // from two empty time fields.
            'days' => ['nullable', 'array'],

            'windows' => ['nullable', 'array'],
            'windows.*' => ['nullable', 'array'],
            // Not date_format:H:i — the end of a window may be 24:00, i.e. the calendar
            // stays open until midnight (end_minute 1440). H rejects hour 24.
            'windows.*.*.start' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'windows.*.*.end' => ['required', 'regex:/^(([01]\d|2[0-3]):[0-5]\d|24:00)$/'],

            'holidays' => ['nullable', 'array'],
            'holidays.*.name' => ['required', 'string', 'max:255'],
            'holidays.*.month' => ['required', 'integer', 'between:1,12'],
            'holidays.*.day' => ['required', 'integer', 'between:1,31'],
            'holidays.*.year' => ['nullable', 'integer', 'between:1970,2999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateWindows($validator);
            $this->validateHolidays($validator);
            $this->validateNotStrandingLivePolicies($validator);
        });
    }

    private function validateWindows(Validator $validator): void
    {
        $openDays = (array) $this->input('days', []);

        foreach ($openDays as $day => $on) {
            if (! $on) {
                continue;
            }

            $rows = (array) ($this->input('windows', [])[$day] ?? []);
            $usable = collect($rows)->contains(
                fn ($row) => is_array($row) && ! blank($row['start'] ?? null) && ! blank($row['end'] ?? null)
            );

            if (! $usable) {
                $validator->errors()->add(
                    "windows.{$day}",
                    __('A day marked as available needs an opening and a closing time. Switch it off instead if you are closed.')
                );
            }
        }

        foreach ((array) $this->input('windows', []) as $day => $rows) {
            // Times on a day that is switched off are inert, so they are not worth an
            // error message the admin cannot act on.
            if (! is_array($rows) || empty($openDays[$day])) {
                continue;
            }

            foreach ($rows as $index => $row) {
                $start = $this->minuteOf($row['start'] ?? null);
                $end = $this->minuteOf($row['end'] ?? null);

                if ($start === null || $end === null) {
                    continue;
                }

                // A window crossing midnight is two rows on two days, not one inverted
                // row — the arithmetic in SlaClock drops anything it cannot read as a
                // forward span, so refusing it here is what stops hours vanishing quietly.
                if ($end <= $start) {
                    $validator->errors()->add(
                        "windows.{$day}.{$index}.end",
                        __('The closing time must be later than the opening time. A shift that runs past midnight is two shifts on two days.')
                    );
                }
            }
        }
    }

    private function validateHolidays(Validator $validator): void
    {
        foreach ((array) $this->input('holidays', []) as $index => $row) {
            $month = (int) ($row['month'] ?? 0);
            $day = (int) ($row['day'] ?? 0);
            $recurs = (bool) ($row['recurring'] ?? false);
            $year = $row['year'] ?? null;

            if ($recurs) {
                // A leap year, so 29 February survives as a recurring holiday.
                if ($month >= 1 && $month <= 12 && $day >= 1 && ! checkdate($month, $day, 2024)) {
                    $validator->errors()->add("holidays.{$index}.day", __('That day does not exist in that month.'));
                }

                continue;
            }

            if ($year === null || $year === '') {
                $validator->errors()->add("holidays.{$index}.year", __('Give a year, or tick "repeats every year".'));

                continue;
            }

            if ($month >= 1 && $month <= 12 && $day >= 1 && ! checkdate($month, $day, (int) $year)) {
                $validator->errors()->add("holidays.{$index}.day", __('That day does not exist in that month.'));
            }
        }
    }

    /**
     * Saving a calendar with no open windows would strand every policy already set to
     * business hours: SlaService falls back to calendar hours rather than throwing, so
     * the targets would silently start meaning something else. Refuse at configuration
     * time, which is the only place this fails safely.
     */
    private function validateNotStrandingLivePolicies(Validator $validator): void
    {
        $openDays = (array) $this->input('days', []);

        $hasWindow = collect((array) $this->input('windows', []))
            ->filter(fn ($rows, $day) => ! empty($openDays[$day]))
            ->flatten(1)
            ->contains(fn ($row) => is_array($row) && ! blank($row['start'] ?? null) && ! blank($row['end'] ?? null));

        if ($hasWindow) {
            return;
        }

        // Targets, not policies: one row can have a business response clock and a
        // calendar resolution clock, and the admin needs the number they will have to
        // go and change.
        $inUse = SlaPolicy::query()->where('response_hours_mode', 'business')->count()
            + SlaPolicy::query()->where('resolution_hours_mode', 'business')->count();

        if ($inUse > 0) {
            $validator->errors()->add('windows', __(
                'Removing every open window would leave :count SLA targets with no working hours. Set those targets back to calendar hours first.',
                ['count' => $inUse]
            ));
        }
    }

    private function minuteOf(?string $time): ?int
    {
        if (! $time || ! preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m)) {
            return null;
        }

        return ((int) $m[1]) * 60 + (int) $m[2];
    }
}
