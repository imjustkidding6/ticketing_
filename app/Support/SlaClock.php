<?php

namespace App\Support;

use App\Exceptions\EmptySlaCalendarException;
use App\Models\SlaCalendar;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Interval arithmetic over a set of open windows: "48 hours from now, counting only
 * the time we are actually open".
 *
 * Deliberately NOT a subclass of ZonedTime, and it never reads the session or an
 * AppSetting. ZonedTime's surface (startOfDay, dayRange, sqlLocal) answers a
 * reporting-boundary question; this answers an interval question, and the two are
 * only superficially alike. More to the point, the ambient-session read was removed
 * from TenantTime because the same code produced two different days depending on
 * where the viewer had been, and this class will eventually run inside a queue
 * worker where there is no session at all. The zone is a constructor argument.
 *
 * Contrast with ZonedTime::sqlLocal(), which applies the tenant's *current* UTC
 * offset because CONVERT_TZ returns NULL when MySQL's timezone tables are not
 * loaded — a documented trade-off that mis-buckets a range spanning a DST change.
 * SlaClock has no such constraint: it works in PHP, uses genuine zone conversion, and is
 * correct across DST — window edges are wall-clock (see atMinute()) while the time inside
 * a window is real elapsed minutes. The two are different on purpose. A developer who
 * "makes them consistent" breaks one of them.
 *
 * UTC in, UTC out. Local time exists only inside this class, because
 * response_due_at/resolution_due_at are UTC columns the breach sweep compares to
 * now() — a zoned Carbon returned from here would eventually be persisted and read
 * back as if it were UTC.
 *
 * There is no "days" unit anywhere in this API and there must never be one.
 * Freshdesk shipped day-based targets under business hours and needed a breaking
 * change to redefine "1 day" as 24 business hours; the way not to have that problem
 * is to never have the word. Minutes are canonical, hours are a multiply.
 */
final class SlaClock
{
    /**
     * A calendar that never opens would make addBusinessMinutes() scan forever. The
     * empty case is caught up front, but a pathological one (a window of zero length,
     * say) is caught by this bound. Never an infinite loop in a queue worker.
     */
    private const MAX_SCAN_DAYS = 400;

    /** @var array<int, list<array{start:int,end:int}>> keyed by ISO weekday, each list sorted and merged */
    private array $windowsByDay = [];

    /** @var list<array{month:int,day:int,year:int|null}> */
    private array $holidays;

    private bool $hasWindows = false;

    /**
     * @param  string  $timezone  IANA zone. Explicit, always. This class never reads the session.
     * @param  list<array{day:int,start:int,end:int}>  $windows  day = ISO-8601 1..7; start/end = minutes from local midnight
     * @param  list<array{month:int,day:int,year:int|null}>  $holidays  year null = recurs annually
     */
    public function __construct(
        private readonly string $timezone,
        array $windows,
        array $holidays = [],
    ) {
        foreach ($windows as $window) {
            $day = (int) $window['day'];
            $start = max(0, (int) $window['start']);
            $end = min(1440, (int) $window['end']);

            // A window crossing midnight is expressed as two rows on two days; one that
            // ends before it starts is meaningless and is dropped rather than inverted.
            if ($end <= $start || $day < 1 || $day > 7) {
                continue;
            }

            $this->windowsByDay[$day][] = ['start' => $start, 'end' => $end];
            $this->hasWindows = true;
        }

        foreach ($this->windowsByDay as $day => $dayWindows) {
            usort($dayWindows, fn ($a, $b) => $a['start'] <=> $b['start']);

            // Overlapping windows would double-count their intersection, which is how a
            // 4-hour target quietly becomes a 2-hour one. Merge them.
            $merged = [];
            foreach ($dayWindows as $window) {
                $last = array_key_last($merged);
                if ($last !== null && $window['start'] <= $merged[$last]['end']) {
                    $merged[$last]['end'] = max($merged[$last]['end'], $window['end']);

                    continue;
                }
                $merged[] = $window;
            }

            $this->windowsByDay[$day] = $merged;
        }

        $this->holidays = array_map(fn (array $h) => [
            'month' => (int) $h['month'],
            'day' => (int) $h['day'],
            'year' => isset($h['year']) && $h['year'] !== null ? (int) $h['year'] : null,
        ], $holidays);
    }

    /**
     * The only DB-aware entry point. Eager-loads hours and holidays so building a clock
     * is one query per relation rather than one per window.
     */
    public static function fromCalendar(SlaCalendar $calendar): self
    {
        $calendar->loadMissing(['hours', 'holidays']);

        return new self(
            $calendar->timezone ?: 'UTC',
            $calendar->hours->map(fn ($h) => [
                'day' => (int) $h->day_of_week,
                'start' => (int) $h->start_minute,
                'end' => (int) $h->end_minute,
            ])->all(),
            $calendar->holidays->map(fn ($h) => [
                'month' => (int) $h->month,
                'day' => (int) $h->day,
                'year' => $h->year !== null ? (int) $h->year : null,
            ])->all(),
        );
    }

    /**
     * An always-open clock — seven full days, no holidays.
     *
     * This is what makes "calendar hours" expressible as a SlaClock, so the eventual
     * mode switch is one code path with two clocks rather than two code paths.
     */
    public static function alwaysOpen(string $timezone = 'UTC'): self
    {
        $windows = [];
        for ($day = 1; $day <= 7; $day++) {
            $windows[] = ['day' => $day, 'start' => 0, 'end' => 1440];
        }

        return new self($timezone, $windows);
    }

    /**
     * UTC in, UTC out. If $from falls outside an open window the clock does not start
     * until the next open minute — the Freshdesk rule: a ticket arriving at 18:00 on
     * Friday with a one-hour target is not due until an hour into Monday morning.
     *
     * Passing 0 therefore returns the next open instant, not $from: a zero-length
     * target is the degenerate case of "the clock starts when we open".
     */
    public function addBusinessMinutes(CarbonInterface $from, int $minutes): CarbonImmutable
    {
        $this->guardOpen();

        $position = $this->toLocal($from);
        $cursor = $position;
        // Carried in SECONDS, not minutes. $from routinely has a seconds component
        // (created_at does), and a target that runs out of one window and into the next
        // has to carry that remainder across the boundary. Rounding to whole minutes at
        // each crossing moved the deadline by up to a second per window — invisible in a
        // single-window case and caught only by asserting equality with addHours() to the
        // second across a day boundary.
        $remaining = max(0, $minutes) * 60;
        $daysScanned = 0;

        while (true) {
            if ($daysScanned > self::MAX_SCAN_DAYS) {
                throw EmptySlaCalendarException::scanExhausted(self::MAX_SCAN_DAYS);
            }

            foreach ($this->windowsFor($cursor) as $window) {
                // Window edges are wall-clock instants; the time inside them is measured as
                // real elapsed minutes. Keeping those two apart is what makes a
                // spring-forward day open at 09:00 local AND be 23 hours long.
                $windowEnd = $this->atMinute($cursor, $window['end']);

                if ($windowEnd <= $position) {
                    continue;
                }

                $windowStart = $this->atMinute($cursor, $window['start']);
                $entry = $windowStart > $position ? $windowStart : $position;

                // Zero remaining: the next open minute is the answer.
                if ($remaining === 0) {
                    return $this->toUtc($entry);
                }

                $available = (int) round($entry->diffInSeconds($windowEnd, false));

                if ($available >= $remaining) {
                    return $this->toUtc($entry->addSeconds($remaining));
                }

                $remaining -= $available;
                $position = $windowEnd;
            }

            $cursor = $cursor->addDay()->startOfDay();
            $position = $cursor;
            $daysScanned++;
        }
    }

    /**
     * Open minutes in the half-open interval [$from, $to).
     *
     * Zero when $to <= $from, never negative: a negative would surface as a negative
     * resolution time in a report, which reads as data corruption rather than as a
     * clock that ran backwards.
     */
    public function businessMinutesBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        $start = $this->toLocal($from);
        $end = $this->toLocal($to);

        if ($end <= $start) {
            return 0;
        }

        $total = 0;
        $cursor = $this->atMinute($start, 0);
        $days = 0;

        while ($cursor < $end) {
            if ($days++ > self::MAX_SCAN_DAYS) {
                break;
            }

            foreach ($this->windowsFor($cursor) as $window) {
                $windowStart = $this->atMinute($cursor, $window['start']);
                $windowEnd = $this->atMinute($cursor, $window['end']);

                $overlapStart = $windowStart > $start ? $windowStart : $start;
                $overlapEnd = $windowEnd < $end ? $windowEnd : $end;

                if ($overlapEnd > $overlapStart) {
                    // Seconds for the same reason as addBusinessMinutes(): a span split
                    // across window boundaries must not gain or lose a minute at each cut.
                    // Converted once, at the end.
                    $total += (int) round($overlapStart->diffInSeconds($overlapEnd, false));
                }
            }

            $cursor = $this->atMinute($cursor->addDay(), 0);
        }

        // Truncated, matching the whole-minute figure these callers reported before the
        // clock existed; the fractional minute is not information anyone is reading.
        return max(0, intdiv($total, 60));
    }

    /**
     * Hour-shaped convenience so call sites keep the shape of
     * SlaPolicy::$response_time_hours rather than converting at every one.
     */
    public function addBusinessHours(CarbonInterface $from, int|float $hours): CarbonImmutable
    {
        return $this->addBusinessMinutes($from, (int) round($hours * 60));
    }

    /** Rounded to 2dp, matching getEffectiveResolutionTimeHours()'s return shape. */
    public function businessHoursBetween(CarbonInterface $from, CarbonInterface $to): float
    {
        return round($this->businessMinutesBetween($from, $to) / 60, 2);
    }

    public function isOpenAt(CarbonInterface $at): bool
    {
        $local = $this->toLocal($at);

        foreach ($this->windowsFor($local) as $window) {
            if ($local >= $this->atMinute($local, $window['start'])
                && $local < $this->atMinute($local, $window['end'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * $at itself when it is already open, otherwise the next open instant.
     * Null when the calendar never opens — the one predicate that does not throw,
     * because "when do we next open?" has a legitimate answer of "never".
     */
    public function nextOpenAt(CarbonInterface $at): ?CarbonImmutable
    {
        if (! $this->hasWindows) {
            return null;
        }

        return $this->addBusinessMinutes($at, 0);
    }

    public function hasOpenWindows(): bool
    {
        return $this->hasWindows;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    private function guardOpen(): void
    {
        if (! $this->hasWindows) {
            throw EmptySlaCalendarException::neverOpens();
        }
    }

    /**
     * The open windows on the local date the cursor sits on, or none when that date is
     * a holiday. Holiday matching is on the *local* date and a null year matches every
     * year.
     */
    private function windowsFor(CarbonImmutable $localDay): array
    {
        foreach ($this->holidays as $holiday) {
            if ($holiday['month'] === $localDay->month
                && $holiday['day'] === $localDay->day
                && ($holiday['year'] === null || $holiday['year'] === $localDay->year)) {
                return [];
            }
        }

        return $this->windowsByDay[$localDay->dayOfWeekIso] ?? [];
    }

    /**
     * The instant at a given minute-from-local-midnight, as WALL-CLOCK time.
     *
     * setTime(), not startOfDay()->addMinutes(): PHP adds relative hour and minute units
     * as absolute time, so on a day whose UTC offset changes, adding 540 minutes to local
     * midnight lands an hour either side of 09:00. "We open at nine" means nine on the
     * clock on the wall, on that day, at that day's offset.
     *
     * Minute 1440 is midnight at the close of the day, which is the next day's start —
     * expressed as a calendar add so it too follows the wall clock.
     *
     * Note the division of labour with addBusinessMinutes(): boundaries come from here and
     * are wall-clock; the duration *inside* a window is measured as real elapsed minutes.
     * A spring-forward day therefore opens at 09:00 local and contains 23 hours, which is
     * what makes alwaysOpen() equal to addHours() in every zone.
     */
    private function atMinute(CarbonImmutable $localDay, int $minute): CarbonImmutable
    {
        if ($minute >= 1440) {
            return $localDay->startOfDay()->addDay();
        }

        return $localDay->setTime(intdiv($minute, 60), $minute % 60);
    }

    private function toLocal(CarbonInterface $instant): CarbonImmutable
    {
        return CarbonImmutable::instance($instant)->setTimezone($this->timezone);
    }

    private function toUtc(CarbonImmutable $local): CarbonImmutable
    {
        return $local->setTimezone('UTC');
    }
}
