<?php

namespace Database\Factories;

use App\Models\SlaCalendar;
use App\Models\SlaCalendarHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SlaCalendarHour>
 */
class SlaCalendarHourFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $calendar = SlaCalendar::factory();

        return [
            'sla_calendar_id' => $calendar,
            'tenant_id' => fn (array $attributes) => SlaCalendar::withoutGlobalScopes()
                ->find($attributes['sla_calendar_id'])?->tenant_id,
            'day_of_week' => 1,
            'start_minute' => 9 * 60,
            'end_minute' => 17 * 60,
        ];
    }

    /**
     * A window may not end before it starts — one that crosses midnight is expressed as
     * two rows on two days — and may not run past minute 1440.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (SlaCalendarHour $hour) {
            if ($hour->end_minute <= $hour->start_minute || $hour->end_minute > 1440) {
                throw new \InvalidArgumentException(
                    "Invalid SLA calendar window {$hour->start_minute}-{$hour->end_minute}: end must be after start and no later than 1440."
                );
            }
        });
    }
}
