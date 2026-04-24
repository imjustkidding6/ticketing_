<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\SystemSetting;
use DateTimeInterface;
use Illuminate\Support\Carbon;

class TenantTime
{
    /**
     * Resolve the effective display timezone:
     *   - Inside a tenant session: tenant's configured timezone.
     *   - Otherwise (admin panel, system context): the system-wide admin timezone.
     *   - Falls back to UTC if neither is set.
     */
    public static function timezone(): string
    {
        if (session()->has('current_tenant_id')) {
            $tz = AppSetting::get('timezone', null);
        } else {
            $tz = SystemSetting::get('admin_timezone', null);
        }

        return is_string($tz) && $tz !== '' ? $tz : 'UTC';
    }

    /**
     * Format a datetime in the tenant's timezone. Returns an empty string for null.
     */
    public static function format(DateTimeInterface|string|null $dt, string $format = 'M d, Y g:i A'): string
    {
        if ($dt === null || $dt === '') {
            return '';
        }

        $carbon = $dt instanceof Carbon ? $dt->copy() : Carbon::parse($dt);

        return $carbon->setTimezone(self::timezone())->format($format);
    }
}
