<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Tenant extends Model
{
    /** @use HasFactory<\Database\Factories\TenantFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo_path',
        'service_report_logo_path',
        'primary_color',
        'accent_color',
        'dark_primary_color',
        'dark_accent_color',
        'is_active',
        'license_id',
        'settings',
        'suspended_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * Get the full URL for the tenant's logo.
     */
    public function logoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }

    /**
     * Logo to print on the service report PDF. Falls back to the portal logo.
     */
    public function serviceReportLogoPath(): ?string
    {
        return $this->service_report_logo_path ?: $this->logo_path;
    }

    public function serviceReportLogoUrl(): ?string
    {
        $path = $this->serviceReportLogoPath();

        return $path ? Storage::disk('public')->url($path) : null;
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Tenant $tenant) {
            if (empty($tenant->slug)) {
                $tenant->slug = Str::slug($tenant->name);
            }
        });
    }

    /**
     * Get the license associated with the tenant.
     */
    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    /**
     * Get the plan for the tenant through the license.
     */
    public function plan(): ?Plan
    {
        return $this->license?->plan;
    }

    /**
     * Check if the tenant's license is valid.
     */
    public function isLicenseValid(): bool
    {
        return $this->license !== null && $this->license->isValid();
    }

    /**
     * Check if the tenant is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Suspend the tenant.
     */
    public function suspend(): bool
    {
        return $this->update(['suspended_at' => now()]);
    }

    /**
     * Unsuspend the tenant.
     */
    public function unsuspend(): bool
    {
        return $this->update(['suspended_at' => null]);
    }

    /**
     * Check if the tenant can add more users based on license seats.
     */
    public function canAddUsers(): bool
    {
        if (! $this->license) {
            return false;
        }

        $currentUserCount = $this->users()->count();

        return $currentUserCount < $this->license->seats;
    }

    /**
     * Get the number of available user slots.
     */
    public function availableUserSlots(): int
    {
        if (! $this->license) {
            return 0;
        }

        return max(0, $this->license->seats - $this->users()->count());
    }

    /**
     * Change the plan for this tenant's license.
     */
    public function changePlan(Plan $plan): bool
    {
        if (! $this->license) {
            return false;
        }

        return $this->license->changePlan($plan);
    }

    /**
     * Get the users that belong to the tenant.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Get the owner(s) of the tenant.
     */
    public function owners(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'owner');
    }

    /**
     * Get the admins of the tenant.
     */
    public function admins(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'admin');
    }

    /**
     * Get the members of the tenant.
     */
    public function members(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'member');
    }

    /**
     * Add a user to the tenant with the given role.
     */
    public function addUser(User $user, string $role = 'member'): void
    {
        $this->users()->syncWithoutDetaching([
            $user->id => [
                'role' => $role,
                'joined_at' => now(),
            ],
        ]);
    }

    /**
     * Remove a user from the tenant.
     */
    public function removeUser(User $user): void
    {
        $this->users()->detach($user->id);
    }

    /**
     * Check if a user belongs to the tenant.
     */
    public function hasUser(User $user): bool
    {
        return $this->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Check if a user is an owner of the tenant.
     */
    public function isOwner(User $user): bool
    {
        return $this->users()
            ->where('user_id', $user->id)
            ->wherePivot('role', 'owner')
            ->exists();
    }
}
