<?php

namespace App\Models\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasTenants
{
    /**
     * Get the tenants that the user belongs to.
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Get the current tenant from session.
     */
    public function currentTenant(): ?Tenant
    {
        $tenantId = session('current_tenant_id');

        if (! $tenantId) {
            return null;
        }

        return $this->tenants()->where('tenants.id', $tenantId)->first();
    }

    /**
     * Set the current tenant in session.
     */
    public function setCurrentTenant(Tenant $tenant): void
    {
        if (! $this->belongsToTenant($tenant)) {
            throw new \InvalidArgumentException('User does not belong to this tenant.');
        }

        session(['current_tenant_id' => $tenant->id]);
    }

    /**
     * Clear the current tenant from session.
     */
    public function clearCurrentTenant(): void
    {
        session()->forget('current_tenant_id');
    }

    /**
     * Check if the user belongs to a tenant.
     */
    public function belongsToTenant(Tenant $tenant): bool
    {
        return $this->tenants()->where('tenants.id', $tenant->id)->exists();
    }

    /**
     * Get the user's role in a specific tenant.
     */
    public function roleInTenant(Tenant $tenant): ?string
    {
        $pivot = $this->tenants()
            ->where('tenants.id', $tenant->id)
            ->first()
            ?->pivot;

        return $pivot?->role;
    }

    /**
     * Check if the user is an owner of a tenant.
     */
    public function isOwnerOf(Tenant $tenant): bool
    {
        return $this->roleInTenant($tenant) === 'owner';
    }

    /**
     * Check if the user is an admin of a tenant.
     */
    public function isAdminOf(Tenant $tenant): bool
    {
        return in_array($this->roleInTenant($tenant), ['owner', 'admin']);
    }

    /**
     * Get tenants where the user is an owner.
     */
    public function ownedTenants(): BelongsToMany
    {
        return $this->tenants()->wherePivot('role', 'owner');
    }

    /**
     * Get tenants where the user is an admin.
     */
    public function adminTenants(): BelongsToMany
    {
        return $this->tenants()->wherePivotIn('role', ['owner', 'admin']);
    }

    /**
     * Switch to the first available tenant if none is set.
     */
    public function ensureCurrentTenant(): ?Tenant
    {
        if (session('current_tenant_id')) {
            return $this->currentTenant();
        }

        $tenant = $this->tenants()->first();

        if ($tenant) {
            $this->setCurrentTenant($tenant);
        }

        return $tenant;
    }
}
