<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    /** @use HasFactory<\Database\Factories\PlanFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'max_users',
        'max_tickets_per_month',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_users' => 'integer',
            'max_tickets_per_month' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the licenses for the plan.
     */
    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    /**
     * Check if the plan has unlimited users.
     */
    public function hasUnlimitedUsers(): bool
    {
        return $this->max_users === null;
    }

    /**
     * Check if the plan has unlimited tickets.
     */
    public function hasUnlimitedTickets(): bool
    {
        return $this->max_tickets_per_month === null;
    }

    /**
     * Scope a query to only include active plans.
     *
     * @param  Builder<Plan>  $query
     * @return Builder<Plan>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
