<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * Dates as the current workspace sees them. The date maths lives in ZonedTime.
 */
final class TenantTime extends ZonedTime
{
    /**
     * The workspace's configured display timezone, falling back to UTC.
     *
     * It used to read the system-wide admin_timezone whenever no tenant was in
     * session, so the same code meant two different days depending on whether
     * the viewer had visited a workspace — the /admin/sla defect in a second
     * place. Platform-staff surfaces ask AdminTime for that answer now; here,
     * "no tenant timezone" means UTC and nothing else.
     */
    public static function timezone(): string
    {
        $tz = AppSetting::get('timezone', null);

        return is_string($tz) && $tz !== '' ? $tz : 'UTC';
    }
}
