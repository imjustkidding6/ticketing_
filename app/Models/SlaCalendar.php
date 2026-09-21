<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\LogsActivity;
use App\Support\TenantTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A workspace's definition of "when we are open": a timezone, weekly windows and a
 * holiday list. Nothing reads it yet — App\Support\SlaClock turns one into arithmetic,
 * and no SLA policy points at a calendar until the mode columns exist.
 */
class SlaCalendar extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'timezone',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * The zone is seeded from the workspace's display timezone once, at creation, and
     * is the calendar's own from then on. Changing where reports are read must not
     * silently move every deadline.
     */
    protected static function booted(): void
    {
        static::creating(function (self $calendar) {
            if (blank($calendar->timezone)) {
                $calendar->timezone = TenantTime::timezone();
            }
        });
    }

    /**
     * @return HasMany<SlaCalendarHour, $this>
     */
    public function hours(): HasMany
    {
        return $this->hasMany(SlaCalendarHour::class);
    }

    /**
     * @return HasMany<SlaCalendarHoliday, $this>
     */
    public function holidays(): HasMany
    {
        return $this->hasMany(SlaCalendarHoliday::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Cheap enough to call before saving a business-hours policy: a calendar that never
     * opens must be refused where it is configured, not where a ticket is created.
     */
    public function hasOpenWindows(): bool
    {
        return $this->relationLoaded('hours')
            ? $this->hours->isNotEmpty()
            : $this->hours()->exists();
    }
}
