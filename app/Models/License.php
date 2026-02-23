<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class License extends Model
{
    /** @use HasFactory<\Database\Factories\LicenseFactory> */
    use HasFactory;

    public const DEFAULT_GRACE_DAYS = 7;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'license_key',
        'distributor_id',
        'plan_id',
        'tenant_id',
        'seats',
        'status',
        'issued_at',
        'activated_at',
        'expires_at',
        'grace_days',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seats' => 'integer',
            'issued_at' => 'datetime',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'grace_days' => 'integer',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (License $license) {
            if (empty($license->license_key)) {
                $license->license_key = static::generateKey();
            }
        });
    }

    /**
     * Get the distributor that owns the license.
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    /**
     * Get the plan associated with the license.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the tenant that activated the license.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Activate the license for a tenant.
     */
    public function activate(Tenant $tenant): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        $this->update([
            'tenant_id' => $tenant->id,
            'activated_at' => now(),
            'status' => self::STATUS_ACTIVE,
        ]);

        $tenant->update(['license_id' => $this->id]);

        return true;
    }

    /**
     * Revoke the license.
     */
    public function revoke(): bool
    {
        $this->update(['status' => self::STATUS_REVOKED]);

        return true;
    }

    /**
     * Check if the license is valid (active and not fully expired).
     */
    public function isValid(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->isFullyExpired();
    }

    /**
     * Check if the license is past its expiration date.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the license is in the grace period.
     */
    public function isInGracePeriod(): bool
    {
        if (! $this->isExpired()) {
            return false;
        }

        return now()->lt($this->gracePeriodEndsAt());
    }

    /**
     * Check if the license is fully expired (past grace period).
     */
    public function isFullyExpired(): bool
    {
        return now()->gte($this->gracePeriodEndsAt());
    }

    /**
     * Get the date when the grace period ends.
     */
    public function gracePeriodEndsAt(): Carbon
    {
        return $this->expires_at->copy()->addDays($this->grace_days);
    }

    /**
     * Calculate the number of days until expiration.
     */
    public function daysUntilExpiry(): int
    {
        return (int) max(0, now()->diffInDays($this->expires_at, false));
    }

    /**
     * Calculate the number of days until full expiry (including grace period).
     */
    public function daysUntilFullExpiry(): int
    {
        return (int) max(0, now()->diffInDays($this->gracePeriodEndsAt(), false));
    }

    /**
     * Change the plan for this license.
     */
    public function changePlan(Plan $plan): bool
    {
        return $this->update(['plan_id' => $plan->id]);
    }

    /**
     * Scope a query to only include pending licenses.
     *
     * @param  Builder<License>  $query
     * @return Builder<License>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to only include active licenses.
     *
     * @param  Builder<License>  $query
     * @return Builder<License>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope a query to only include expired licenses.
     *
     * @param  Builder<License>  $query
     * @return Builder<License>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<', now());
    }

    /**
     * Scope a query to only include licenses in grace period.
     *
     * @param  Builder<License>  $query
     * @return Builder<License>
     */
    public function scopeInGracePeriod(Builder $query): Builder
    {
        return $query->where('expires_at', '<', now())
            ->whereRaw('DATE_ADD(expires_at, INTERVAL grace_days DAY) > ?', [now()]);
    }

    /**
     * Generate a unique license key in XXXX-XXXX-XXXX-XXXX-XXXX format.
     */
    public static function generateKey(): string
    {
        $segments = [];
        for ($i = 0; $i < 5; $i++) {
            $segments[] = strtoupper(Str::random(4));
        }

        return implode('-', $segments);
    }
}
