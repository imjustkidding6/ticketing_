<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One open window on one weekday, in minutes from local midnight.
 *
 * No LogsActivity: a window edit is a calendar edit, and per-row diffs would bury the
 * meaningful change under noise. Stated as a decision, not an omission.
 */
class SlaCalendarHour extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'sla_calendar_id',
        'day_of_week',
        'start_minute',
        'end_minute',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'start_minute' => 'integer',
            'end_minute' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SlaCalendar, $this>
     */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(SlaCalendar::class, 'sla_calendar_id');
    }
}
