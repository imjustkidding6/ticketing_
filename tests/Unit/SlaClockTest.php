<?php

namespace Tests\Unit;

use App\Exceptions\EmptySlaCalendarException;
use App\Support\SlaClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * SlaClock is the part of business hours most likely to be subtly wrong, which is why
 * it ships with no consumer. These tests are the whole of its proof.
 *
 * No database, no tenant, no session: a SlaClock is constructed with an explicit
 * timezone and an explicit set of windows.
 */
class SlaClockTest extends TestCase
{
    /** Mon-Fri 09:00-17:00 in UTC. */
    private function weekdayClock(array $holidays = [], string $tz = 'UTC'): SlaClock
    {
        $windows = [];
        for ($day = 1; $day <= 5; $day++) {
            $windows[] = ['day' => $day, 'start' => 9 * 60, 'end' => 17 * 60];
        }

        return new SlaClock($tz, $windows, $holidays);
    }

    /** 09:00-17:00 every day, so a transition landing on a Sunday is still an open day. */
    private function everyDayClock(string $tz): SlaClock
    {
        $windows = [];
        for ($day = 1; $day <= 7; $day++) {
            $windows[] = ['day' => $day, 'start' => 9 * 60, 'end' => 17 * 60];
        }

        return new SlaClock($tz, $windows);
    }

    private function at(string $iso, string $tz = 'UTC'): CarbonImmutable
    {
        return CarbonImmutable::parse($iso, $tz);
    }

    public function test_adds_minutes_inside_a_single_open_window(): void
    {
        // The trivial case — and the one a broken next-window scan silently passes.
        $result = $this->weekdayClock()->addBusinessMinutes($this->at('2026-09-21 10:00'), 60);

        $this->assertSame('2026-09-21 11:00', $result->format('Y-m-d H:i'));
    }

    public function test_arrival_outside_hours_starts_the_clock_at_next_open(): void
    {
        // Freshdesk's worked example, and the single most important behaviour here:
        // a ticket at 18:00 Friday with a one-hour target is due an hour into Monday.
        $result = $this->weekdayClock()->addBusinessMinutes($this->at('2026-09-18 18:00'), 60);

        $this->assertSame('2026-09-21 10:00', $result->format('Y-m-d H:i'));
    }

    public function test_zero_minutes_returns_the_next_open_instant(): void
    {
        $result = $this->weekdayClock()->addBusinessMinutes($this->at('2026-09-19 12:00'), 0);

        // Saturday noon + 0 is Monday 09:00, not Saturday noon.
        $this->assertSame('2026-09-21 09:00', $result->format('Y-m-d H:i'));
    }

    public function test_spans_a_closed_lunch(): void
    {
        $windows = [];
        for ($day = 1; $day <= 5; $day++) {
            $windows[] = ['day' => $day, 'start' => 9 * 60, 'end' => 12 * 60];
            $windows[] = ['day' => $day, 'start' => 13 * 60, 'end' => 17 * 60];
        }

        $clock = new SlaClock('UTC', $windows);

        // 11:30 + 60 open minutes: 30 before lunch, 30 after.
        $result = $clock->addBusinessMinutes($this->at('2026-09-21 11:30'), 60);

        $this->assertSame('2026-09-21 13:30', $result->format('Y-m-d H:i'));
    }

    public function test_skips_a_recurring_holiday_in_any_year(): void
    {
        // A null year means every year. The JSM rot failure — calendars nobody
        // re-populates in January — pinned. 21 September is a Monday in 2026 and a
        // Tuesday in 2027, so both would otherwise be ordinary working days.
        $clock = $this->weekdayClock([['month' => 9, 'day' => 21, 'year' => null]]);

        $this->assertFalse($clock->isOpenAt($this->at('2026-09-21 10:00')));
        $this->assertFalse($clock->isOpenAt($this->at('2027-09-21 10:00')));

        // And the arithmetic skips it, not just the predicate: Friday 18:00 + 1h lands
        // on the Tuesday, because the Monday is closed.
        $this->assertSame(
            '2026-09-22 10:00',
            $clock->addBusinessMinutes($this->at('2026-09-18 18:00'), 60)->format('Y-m-d H:i')
        );
    }

    public function test_one_off_holiday_applies_only_to_its_year(): void
    {
        $clock = $this->weekdayClock([['month' => 9, 'day' => 21, 'year' => 2026]]);

        $this->assertFalse($clock->isOpenAt($this->at('2026-09-21 10:00')));
        // 2027-09-21 is a Tuesday, an ordinary working day.
        $this->assertTrue($clock->isOpenAt($this->at('2027-09-21 10:00')));
    }

    public function test_business_minutes_between_counts_only_open_minutes(): void
    {
        // Fri 16:00 -> Mon 10:00 = one hour Friday + one hour Monday.
        $minutes = $this->weekdayClock()->businessMinutesBetween(
            $this->at('2026-09-18 16:00'),
            $this->at('2026-09-21 10:00')
        );

        $this->assertSame(120, $minutes);
    }

    public function test_business_minutes_between_is_zero_when_to_precedes_from(): void
    {
        $minutes = $this->weekdayClock()->businessMinutesBetween(
            $this->at('2026-09-21 12:00'),
            $this->at('2026-09-21 10:00')
        );

        // Never negative: a negative would surface as a negative resolution time.
        $this->assertSame(0, $minutes);
    }

    public function test_business_minutes_between_is_the_inverse_of_add(): void
    {
        // The deadline and the report must agree — this is the exact defect class the
        // whole feature exists to fix, so the two operations are checked against each
        // other over a spread of starts and durations, in UTC and in a DST zone. The
        // DST half is the one that caught atMinute() adding minutes as absolute time.
        $cases = [
            ['UTC', $this->weekdayClock(), ['2026-09-21 09:00', '2026-09-21 16:30', '2026-09-23 11:15', '2026-09-25 09:01']],
            ['America/New_York', $this->everyDayClock('America/New_York'), ['2026-03-07 09:00', '2026-03-08 09:30', '2026-10-31 16:00', '2026-11-01 09:00']],
        ];

        foreach ($cases as [$tz, $clock, $starts]) {
            foreach ($starts as $start) {
                foreach ([1, 30, 60, 240, 480, 481, 2400] as $minutes) {
                    $from = $this->at($start, $tz);
                    $to = $clock->addBusinessMinutes($from, $minutes);

                    $this->assertSame(
                        $minutes,
                        $clock->businessMinutesBetween($from, $to),
                        "add/between disagree in {$tz} for {$start} + {$minutes}m"
                    );
                }
            }
        }
    }

    /**
     * A window is a WALL-CLOCK boundary: "we open at 09:00" means 09:00 local on the day
     * itself, whatever that day's UTC offset is.
     *
     * The previous version of this test asked about Monday 2026-03-09 on a Mon-Fri clock —
     * the day *after* the transition, which is an ordinary day — so it could not see the
     * bug it was named for. 2026-03-08 is a Sunday, hence everyDayClock().
     *
     * Explicitly unlike ZonedTime::sqlLocal(), which applies a fixed current offset.
     */
    public function test_dst_spring_forward_window_still_opens_at_local_nine(): void
    {
        $clock = $this->everyDayClock('America/New_York');

        // 06:00 UTC on the transition day is 01:00 EST, before the window opens.
        $result = $clock->addBusinessMinutes($this->at('2026-03-08 06:00'), 60);

        $this->assertSame(
            '2026-03-08 10:00 EDT',
            $result->setTimezone('America/New_York')->format('Y-m-d H:i T')
        );
    }

    public function test_dst_fall_back_window_still_opens_at_local_nine(): void
    {
        $clock = $this->everyDayClock('America/New_York');

        // 2026-11-01 is the fall-back; 05:00 UTC is 01:00 EDT, before the window opens.
        $next = $clock->nextOpenAt($this->at('2026-11-01 05:00'));

        $this->assertSame(
            '2026-11-01 09:00 EST',
            $next->setTimezone('America/New_York')->format('Y-m-d H:i T')
        );
    }

    /**
     * Boundaries are wall-clock; durations are real elapsed minutes. A spring-forward day
     * is genuinely 23 hours long and an always-open clock must say so, or "calendar hours"
     * and plain elapsed time part company.
     */
    public function test_business_minutes_between_counts_real_elapsed_minutes_across_dst(): void
    {
        $clock = SlaClock::alwaysOpen('America/New_York');

        $this->assertSame(4260, $clock->businessMinutesBetween(
            $this->at('2026-03-07 00:00', 'America/New_York'),
            $this->at('2026-03-10 00:00', 'America/New_York')
        ), 'Three days spanning a spring-forward is 4260 minutes, not 4320.');

        $this->assertSame(4380, $clock->businessMinutesBetween(
            $this->at('2026-10-31 00:00', 'America/New_York'),
            $this->at('2026-11-03 00:00', 'America/New_York')
        ), 'Three days spanning a fall-back is 4380 minutes, not 4320.');
    }

    /**
     * alwaysOpen() must equal addHours() in EVERY zone, not just UTC. This equality is the
     * foundation the deferred test_flag_off_produces_identical_due_dates_to_today rests on:
     * if it fails outside UTC, "calendar hours" and "business hours" cannot share one code
     * path and the mode switch would move deadlines it promised not to touch.
     */
    public function test_always_open_matches_add_hours_inside_a_dst_zone(): void
    {
        $clock = SlaClock::alwaysOpen('America/New_York');

        foreach ([
            '2026-03-07 12:00',  // the day before spring-forward
            '2026-03-08 00:30',  // the transition day itself
            '2026-10-31 12:00',  // the day before fall-back
            '2026-11-01 00:30',
        ] as $start) {
            foreach ([1, 24, 48, 72] as $hours) {
                $from = $this->at($start, 'America/New_York');

                $this->assertSame(
                    $from->addHours($hours)->setTimezone('UTC')->toIso8601String(),
                    $clock->addBusinessHours($from, $hours)->toIso8601String(),
                    "alwaysOpen diverges from addHours in America/New_York for {$start} + {$hours}h"
                );
            }
        }
    }

    public function test_returns_utc_immutable_and_does_not_mutate_input(): void
    {
        $clock = $this->weekdayClock(tz: 'Asia/Manila');
        $from = Carbon::parse('2026-09-21 02:00', 'UTC');
        $snapshot = $from->toIso8601String();

        $result = $clock->addBusinessMinutes($from, 60);

        $this->assertInstanceOf(CarbonImmutable::class, $result);
        $this->assertSame('UTC', $result->timezone->getName());
        $this->assertSame($snapshot, $from->toIso8601String(), 'The input instant must not be mutated.');
    }

    public function test_empty_calendar_throws_rather_than_hanging(): void
    {
        $clock = new SlaClock('UTC', []);

        $this->assertFalse($clock->hasOpenWindows());
        $this->assertNull($clock->nextOpenAt($this->at('2026-09-21 10:00')));

        $start = microtime(true);

        try {
            $clock->addBusinessMinutes($this->at('2026-09-21 10:00'), 60);
            $this->fail('An empty calendar must throw rather than scan forever.');
        } catch (EmptySlaCalendarException) {
            // A time bound, so a regression fails the test rather than hanging CI.
            $this->assertLessThan(5, microtime(true) - $start);
        }
    }

    public function test_always_open_clock_matches_plain_add_hours(): void
    {
        // The seed of the eventual test_flag_off_produces_identical_due_dates_to_today,
        // and the proof that "calendar hours" and "business hours" can share one path.
        $clock = SlaClock::alwaysOpen();

        foreach ([
            '2026-09-18 18:00',
            '2026-09-19 00:00',
            '2026-03-08 06:30', // across a DST transition in other zones
            '2026-12-25 23:59',
        ] as $start) {
            foreach ([1, 24, 48, 72] as $hours) {
                $from = $this->at($start);

                $this->assertSame(
                    $from->addHours($hours)->format('Y-m-d H:i'),
                    $clock->addBusinessHours($from, $hours)->format('Y-m-d H:i'),
                    "alwaysOpen diverges from addHours for {$start} + {$hours}h"
                );
            }
        }
    }

    public function test_24x7_calendar_is_never_closed(): void
    {
        // Enterprise tier is 24x7 by product decision; that must be expressible as an
        // ordinary calendar rather than a special case in the arithmetic.
        $windows = [];
        for ($day = 1; $day <= 7; $day++) {
            $windows[] = ['day' => $day, 'start' => 0, 'end' => 1440];
        }

        $clock = new SlaClock('UTC', $windows);

        foreach (['2026-09-19 03:00', '2026-09-20 23:59', '2026-09-21 00:00'] as $instant) {
            $this->assertTrue($clock->isOpenAt($this->at($instant)), "{$instant} should be open");
        }

        $this->assertSame(
            SlaClock::alwaysOpen()->addBusinessHours($this->at('2026-09-18 18:00'), 48)->toIso8601String(),
            $clock->addBusinessHours($this->at('2026-09-18 18:00'), 48)->toIso8601String()
        );
    }

    public function test_business_hours_between_is_rounded_to_two_places(): void
    {
        $hours = $this->weekdayClock()->businessHoursBetween(
            $this->at('2026-09-21 09:00'),
            $this->at('2026-09-21 09:50')
        );

        $this->assertSame(0.83, $hours);
    }

    public function test_overlapping_windows_are_not_double_counted(): void
    {
        // Two overlapping rows on the same day would otherwise halve the target.
        $clock = new SlaClock('UTC', [
            ['day' => 1, 'start' => 9 * 60, 'end' => 13 * 60],
            ['day' => 1, 'start' => 11 * 60, 'end' => 17 * 60],
        ]);

        $this->assertSame(480, $clock->businessMinutesBetween(
            $this->at('2026-09-21 00:00'),
            $this->at('2026-09-22 00:00')
        ));
    }

    public function test_next_open_at_returns_the_instant_itself_when_already_open(): void
    {
        $clock = $this->weekdayClock();
        $open = $this->at('2026-09-21 10:00');

        $this->assertSame($open->toIso8601String(), $clock->nextOpenAt($open)->toIso8601String());
        $this->assertSame(
            '2026-09-21 09:00',
            $clock->nextOpenAt($this->at('2026-09-19 12:00'))->format('Y-m-d H:i')
        );
    }
}
