<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlaPolicy extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'client_tier',
        'priority',
        'response_time_hours',
        'resolution_time_hours',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'response_time_hours' => 'integer',
            'resolution_time_hours' => 'integer',
        ];
    }

    /**
     * @param  Builder<SlaPolicy>  $query
     * @return Builder<SlaPolicy>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Find the best matching SLA policy for a ticket.
     */
    public static function findForTicket(Ticket $ticket): ?self
    {
        return static::query()
            ->active()
            ->where(function ($q) use ($ticket) {
                $q->where('priority', $ticket->priority)
                    ->orWhereNull('priority');
            })
            ->where(function ($q) use ($ticket) {
                $q->where('client_tier', $ticket->client?->tier)
                    ->orWhereNull('client_tier');
            })
            ->orderByRaw('
                CASE WHEN priority IS NOT NULL AND client_tier IS NOT NULL THEN 1
                     WHEN priority IS NOT NULL THEN 2
                     WHEN client_tier IS NOT NULL THEN 3
                     ELSE 4 END
            ')
            ->first();
    }
}
