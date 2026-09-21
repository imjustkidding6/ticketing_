<?php

namespace App\Support;

use App\Models\SystemSetting;

/**
 * Dates as platform staff see them: /admin/* and other cross-tenant surfaces.
 *
 * Never call this from tenant code — a workspace's dates belong in the
 * workspace's zone, and the point of the split is that each caller says which
 * one it means rather than inferring it from session state.
 */
final class AdminTime extends ZonedTime
{
    /**
     * The system-wide admin display timezone, falling back to UTC.
     */
    public static function timezone(): string
    {
        $tz = SystemSetting::get('admin_timezone', null);

        return is_string($tz) && $tz !== '' ? $tz : 'UTC';
    }
}
