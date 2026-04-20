<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    /** @use HasFactory<\Database\Factories\ClientFactory> */
    use BelongsToTenant, HasFactory;

    public const TIER_BASIC = 'basic';

    public const TIER_PREMIUM = 'premium';

    public const TIER_ENTERPRISE = 'enterprise';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'email',
        'phone',
        'address',
        'contact_person',
        'tier',
        'status',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * Get the user account linked to this client.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tickets for this client.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Check if the client has portal access (linked user account).
     */
    public function hasPortalAccess(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Scope a query to only include active clients.
     *
     * @param  Builder<Client>  $query
     * @return Builder<Client>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Get available tiers.
     *
     * @return list<string>
     */
    public static function tiers(): array
    {
        return [self::TIER_BASIC, self::TIER_PREMIUM, self::TIER_ENTERPRISE];
    }

    /**
     * Get the agent assignments for this client.
     */
    public function agentAssignments(): HasMany
    {
        return $this->hasMany(ClientAgentAssignment::class);
    }

    /**
     * Get the current month's active agent assignment.
     */
    public function currentAgent(): ?ClientAgentAssignment
    {
        return $this->agentAssignments()
            ->currentMonth()
            ->active()
            ->first();
    }
}
