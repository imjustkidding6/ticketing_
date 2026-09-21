<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * The date maths behind TenantTime and AdminTime.
 *
 * Every helper below computes a boundary in whatever timezone the subclass
 * names, then hands back a UTC instant fit for a query. The zone is the only
 * thing that differs between surfaces, so it is the only thing a subclass
 * supplies — splitting it out is what stops "which timezone am I in?" being
 * answered by whatever session state happens to be set.
 *
 * Comments here still say "tenant" where the rule was written for tenant
 * reporting; it holds identically for the admin zone.
 */
abstract class ZonedTime
{
    /**
     * The display timezone for this surface. Implementations must always
     * return a usable zone name, falling back to 'UTC'.
     */
    abstract public static function timezone(): string;

    /**
     * Format a datetime in the tenant's timezone. Returns an empty string for null.
     */
    public static function format(DateTimeInterface|string|null $dt, string $format = 'M d, Y g:i A'): string
    {
        if ($dt === null || $dt === '') {
            return '';
        }

        $carbon = $dt instanceof Carbon ? $dt->copy() : Carbon::parse($dt);

        return $carbon->setTimezone(static::timezone())->format($format);
    }

    /**
     * "Now", expressed in the tenant's timezone.
     *
     * Storage and the application timezone are UTC. Everything below returns a
     * UTC instant so it can go straight into a query — but computes the boundary
     * in the tenant's timezone first, which is the whole point: a report headed
     * "today" must mean the tenant's today, not the server's.
     */
    public static function now(): Carbon
    {
        return Carbon::now(static::timezone());
    }

    /**
     * The UTC instant at which a tenant-local day begins.
     *
     * Pass null for today. For a tenant on UTC+8, startOfDay() for 27 Aug is
     * 26 Aug 16:00 UTC — which is exactly why filtering on the raw date is wrong.
     */
    public static function startOfDay(DateTimeInterface|string|null $date = null): Carbon
    {
        return static::inZone($date)->startOfDay()->utc();
    }

    /**
     * The UTC instant at which a tenant-local day ends.
     */
    public static function endOfDay(DateTimeInterface|string|null $date = null): Carbon
    {
        return static::inZone($date)->endOfDay()->utc();
    }

    /**
     * Start of the tenant-local week containing the given date (default now).
     */
    public static function startOfWeek(DateTimeInterface|string|null $date = null): Carbon
    {
        return static::inZone($date)->startOfWeek()->utc();
    }

    /**
     * Start of the tenant-local month containing the given date (default now).
     */
    public static function startOfMonth(DateTimeInterface|string|null $date = null): Carbon
    {
        return static::inZone($date)->startOfMonth()->utc();
    }

    /**
     * Start of the tenant-local day N days before today.
     */
    public static function startOfDaysAgo(int $days): Carbon
    {
        return static::now()->subDays($days)->startOfDay()->utc();
    }

    /**
     * The tenant-local day bounds for a date, as a [from, to] UTC pair — the
     * replacement for whereDate(), which compares against the server's calendar.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function dayRange(DateTimeInterface|string|null $date = null): array
    {
        return [static::startOfDay($date), static::endOfDay($date)];
    }

    /**
     * Reinterpret a value in the tenant's timezone.
     *
     * A bare date string like "2026-08-27" is parsed AS a tenant-local date. A
     * real instant is converted, preserving the moment it refers to.
     */
    private static function inZone(DateTimeInterface|string|null $date): Carbon
    {
        $tz = static::timezone();

        if ($date === null || $date === '') {
            return Carbon::now($tz);
        }

        if ($date instanceof DateTimeInterface) {
            return Carbon::instance($date)->setTimezone($tz);
        }

        return Carbon::parse($date, $tz);
    }

    /**
     * The tenant's current UTC offset, in seconds.
     */
    public static function offsetSeconds(): int
    {
        return static::now()->getOffset();
    }

    /**
     * A SQL expression shifting a UTC column into the tenant's local time.
     *
     * GROUP BY DATE(created_at) buckets by the server's calendar, so for a tenant
     * on UTC+8 a ticket closed at 07:00 local lands in the previous day's bucket
     * while the row is displayed as today. Shifting the column first puts the
     * grouping and the display in agreement.
     *
     * CONVERT_TZ is avoided deliberately: with a named zone it returns NULL
     * unless MySQL's timezone tables are loaded, which fails silently. The
     * trade-off is that the offset is the tenant's *current* one, so a range
     * spanning a DST change buckets those rows by today's offset. Tenants on
     * zones without DST — the common case here — are unaffected.
     */
    public static function sqlLocal(string $column): string
    {
        $offset = static::offsetSeconds();

        if ($offset === 0) {
            return $column;
        }

        return sprintf('%s + INTERVAL %d SECOND', $column, $offset);
    }
}
