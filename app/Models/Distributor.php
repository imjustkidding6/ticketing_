<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Distributor extends Model
{
    /** @use HasFactory<\Database\Factories\DistributorFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'email',
        'contact_person',
        'phone',
        'address',
        'is_active',
        'api_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Distributor $distributor) {
            if (empty($distributor->slug)) {
                $distributor->slug = Str::slug($distributor->name);
            }
            if (empty($distributor->api_key)) {
                $distributor->api_key = static::generateApiKey();
            }
        });
    }

    /**
     * Get the licenses for the distributor.
     */
    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    /**
     * Generate a new license for this distributor.
     *
     * @param  array<string, mixed>  $options
     */
    public function generateLicense(Plan $plan, array $options = []): License
    {
        return $this->licenses()->create([
            'license_key' => License::generateKey(),
            'plan_id' => $plan->id,
            'seats' => $options['seats'] ?? $plan->max_users ?? 5,
            'status' => 'pending',
            'issued_at' => now(),
            'expires_at' => $options['expires_at'] ?? now()->addYear(),
            'grace_days' => $options['grace_days'] ?? License::DEFAULT_GRACE_DAYS,
        ]);
    }

    /**
     * Count active licenses for this distributor.
     */
    public function activeLicenses(): int
    {
        return $this->licenses()->where('status', 'active')->count();
    }

    /**
     * Scope a query to only include active distributors.
     *
     * @param  Builder<Distributor>  $query
     * @return Builder<Distributor>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Generate a unique API key.
     */
    public static function generateApiKey(): string
    {
        return 'dk_'.Str::random(32);
    }
}
