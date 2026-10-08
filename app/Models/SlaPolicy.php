<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlaPolicy extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity;

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
        'response_hours_mode',
        'resolution_time_hours',
        'resolution_hours_mode',
        'is_active',
    ];

    /**
     * The clock a target is measured on. varchar in the database, so this is the only
     * enumeration — every entry point (updateTier(), the tier editor, SlaService)
     * validates against it.
     *
     * 'calendar' is elapsed wall-clock time and is the default for every row, existing
     * and new: it is today's behaviour, so nothing moves until an admin chooses.
     * 'business' counts only the open minutes on the workspace's SLA calendar.
     */
    public const HOURS_MODES = ['calendar', 'business'];

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

    public function responseClockIsBusiness(): bool
    {
        return $this->response_hours_mode === 'business';
    }

    public function resolutionClockIsBusiness(): bool
    {
        return $this->resolution_hours_mode === 'business';
    }

    /**
     * Tickets using this SLA Policy.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'sla_policy_id');
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
     * How many (tier, priority) slots one workspace's SLA matrix has.
     * Coverage figures used to divide by a literal 12, which stops being
     * meaningful the moment the caller is looking at more than one workspace.
     */
    public static function matrixSlotCount(): int
    {
        return count(self::TIERS) * count(self::PRIORITIES);
    }

    /**
     * Industry-standard defaults: each tier's {priority => [response_hours, resolution_hours]}.
     *
     * Every number here is **calendar hours** — elapsed clock time from ticket creation,
     * counting nights, weekends and public holidays. Nothing pauses it except time on
     * hold, which moves the deadline out rather than being subtracted afterwards.
     *
     * The enterprise row is the one place that is deliberate rather than merely inherited:
     * enterprise tier is a 24x7 promise by product decision, so calendar hours is the
     * correct clock for it and would remain correct even if other tiers ever moved to a
     * business-hours clock.
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
     * The predicate pair both coverage questions use, in ONE place.
     *
     * `tenant_id` EXPLICITLY and `withoutGlobalScopes()`: this is reachable from the
     * queued intake analysis and from a console command, where `TenantScope` applies no
     * predicate at all. A null `priority` or `client_tier` on a policy row means
     * CATCH-ALL and matches.
     *
     * @return Builder<SlaPolicy>
     */
    private static function coveringQuery(int $tenantId, ?string $clientTier, string $priority): Builder
    {
        return static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('priority', $priority)->orWhereNull('priority'))
            ->where(fn ($q) => $q->where('client_tier', $clientTier)->orWhereNull('client_tier'));
    }

    /**
     * Whether any active SLA policy covers (client_tier, priority) for this tenant.
     */
    public static function hasPolicyFor(int $tenantId, ?string $clientTier, string $priority): bool
    {
        return static::coveringQuery($tenantId, $clientTier, $priority)->exists();
    }

    /**
     * The WINNING policy for (tier, priority) — the hours a ticket would actually get.
     *
     * The specificity ladder is `findForTicket()`'s, restated here only because that one
     * is keyed on a persisted Ticket and the create-form draft has none. A third copy of
     * the ladder in PHP (a `whereIn` plus a tie-break) is precisely the drift CLAUDE.md
     * warns about, so this is a SQL ordering like its sibling and not a reimplementation.
     */
    public static function policyFor(int $tenantId, ?string $clientTier, string $priority): ?self
    {
        return static::coveringQuery($tenantId, $clientTier, $priority)
            ->orderByRaw('
                CASE WHEN priority IS NOT NULL AND client_tier IS NOT NULL THEN 1
                     WHEN priority IS NOT NULL THEN 2
                     WHEN client_tier IS NOT NULL THEN 3
                     ELSE 4 END
            ')
            ->first();
    }
}
