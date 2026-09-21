<?php

namespace Database\Factories;

use App\Models\SlaCalendar;
use App\Models\SlaCalendarHoliday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SlaCalendarHoliday>
 */
class SlaCalendarHolidayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sla_calendar_id' => SlaCalendar::factory(),
            'tenant_id' => fn (array $attributes) => SlaCalendar::withoutGlobalScopes()
                ->find($attributes['sla_calendar_id'])?->tenant_id,
            'name' => 'Christmas Day',
            'month' => 12,
            'day' => 25,
            // Null by default: recurring is the safe shape, because the failure mode is a
            // calendar nobody re-populates in January.
            'year' => null,
        ];
    }

    public function inYear(int $year): static
    {
        return $this->state(fn () => ['year' => $year]);
    }
}
