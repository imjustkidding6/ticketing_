<?php

namespace Database\Factories;

use App\Models\SlaCalendar;
use App\Models\SlaCalendarHour;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SlaCalendar>
 */
class SlaCalendarFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'Support hours',
            'timezone' => 'UTC',
            'is_default' => true,
        ];
    }

    /** Mon-Fri 09:00-17:00, the JSM preset and the shape most workspaces start from. */
    public function nineToFive(): static
    {
        return $this->withWindows(range(1, 5), [[9 * 60, 17 * 60]]);
    }

    /** Mon-Fri 09:00-12:00 and 13:00-17:00 — a closed lunch, which is ordinary. */
    public function splitShift(): static
    {
        return $this->withWindows(range(1, 5), [[9 * 60, 12 * 60], [13 * 60, 17 * 60]]);
    }

    /** Seven full days. The enterprise tier is 24x7 by product decision. */
    public function twentyFourSeven(): static
    {
        return $this->withWindows(range(1, 7), [[0, 1440]]);
    }

    /**
     * @param  list<int>  $days  ISO weekdays
     * @param  list<array{0:int,1:int}>  $windows  [startMinute, endMinute] pairs
     */
    public function withWindows(array $days, array $windows): static
    {
        return $this->afterCreating(function (SlaCalendar $calendar) use ($days, $windows) {
            foreach ($days as $day) {
                foreach ($windows as [$start, $end]) {
                    SlaCalendarHour::factory()->create([
                        'tenant_id' => $calendar->tenant_id,
                        'sla_calendar_id' => $calendar->id,
                        'day_of_week' => $day,
                        'start_minute' => $start,
                        'end_minute' => $end,
                    ]);
                }
            }
        });
    }
}
