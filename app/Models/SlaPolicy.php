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
        // Use the ticket's tenant_id directly — don't rely on session,
        // which may not be set during public submissions or queued jobs.
        return static::withoutGlobalScopes()
            ->where('tenant_id', $ticket->tenant_id)
            ->where('is_active', true)
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

    public const TIERS = ['basic', 'premium', 'enterprise'];
    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    /**
     * Industry-standard defaults: each tier's {priority => [response_hours, resolution_hours]}.
     */
    public const STANDARD_DEFAULTS = [
        'basic' => [
            'low' => [48, 72],
            'medium' => [24, 48],
            'high' => [8, 24],
            'critical' => [2, 8],
        ],
        'premium' => [
            'low' => [24, 48],
            'medium' => [8, 24],
            'high' => [4, 8],
            'critical' => [1, 4],
        ],
        'enterprise' => [
            'low' => [8, 24],
            'medium' => [4, 8],
            'high' => [2, 4],
            'critical' => [1, 2],
        ],
    ];

    /**
     * Seed standard policies for a tenant. Skips any (tier, priority) pair that already exists.
     * Returns the count of newly created policies.
     */
    public static function seedStandardDefaults(int $tenantId): int
    {
        $created = 0;
        foreach (self::STANDARD_DEFAULTS as $tier => $priorities) {
            foreach ($priorities as $priority => [$response, $resolution]) {
                $exists = static::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('client_tier', $tier)
                    ->where('priority', $priority)
                    ->exists();

                if ($exists) {
                    continue;
                }

                static::withoutGlobalScopes()->create([
                    'tenant_id' => $tenantId,
                    'name' => ucfirst($tier).' - '.ucfirst($priority),
                    'description' => null,
                    'client_tier' => $tier,
                    'priority' => $priority,
                    'response_time_hours' => $response,
                    'resolution_time_hours' => $resolution,
                    'is_active' => true,
                ]);
                $created++;
            }
        }

        return $created;
    }

    /**
     * Whether any active SLA policy covers (client_tier, priority) for this tenant.
     */
    public static function hasPolicyFor(int $tenantId, ?string $clientTier, string $priority): bool
    {
        return static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('priority', $priority)->orWhereNull('priority'))
            ->where(fn ($q) => $q->where('client_tier', $clientTier)->orWhereNull('client_tier'))
            ->exists();
    }
}
