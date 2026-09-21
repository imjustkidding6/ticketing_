<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A closed date on a calendar. A null year means it recurs every year, which is the
 * column default — see the migration for why rotting must be the opt-in.
 */
class SlaCalendarHoliday extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'sla_calendar_id',
        'name',
        'month',
        'day',
        'year',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'day' => 'integer',
            'year' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SlaCalendar, $this>
     */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(SlaCalendar::class, 'sla_calendar_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeRecurring(Builder $query): Builder
    {
        return $query->whereNull('year');
    }
}
