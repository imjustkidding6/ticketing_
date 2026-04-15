<?php

namespace App\Services;

use App\Enums\PlanFeature;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

class PlanService
{
    /**
     * Check if a tenant has access to a specific feature.
     */
    public function tenantHasFeature(Tenant $tenant, PlanFeature|string $feature): bool
    {
        if (! $this->hasValidLicense($tenant)) {
            return false;
        }

        $featureValue = $feature instanceof PlanFeature
            ? $feature->value
            : $feature;

        return in_array($featureValue, $this->getPlanFeatures($tenant), true);
    }

    /**
     * Get plan features (cached for 5 minutes as a safety net).
     * @return list<string>
     */
    protected function getPlanFeatures(Tenant $tenant): array
    {
        return Cache::remember(
            $this->planCacheKey($tenant),
            now()->addMinutes(5),
            function () use ($tenant) {
                $plan = $tenant->plan();

                return $plan?->features ?? [];
            }
        );
    }

    /**
     * Validate tenant license (NOT cached).
     */
    protected function hasValidLicense(Tenant $tenant): bool
    {
        $license = $tenant->license;

        return $license !== null && $license->isValid();
    }

    /**
     * Clear cached plan features.
     */
    public function clearCache(Tenant $tenant): void
    {
        Cache::forget($this->planCacheKey($tenant));
    }

    /**
     * Check feature for the current session tenant.
     */
    public function currentTenantHasFeature(PlanFeature|string $feature): bool
    {
        $tenantId = session('current_tenant_id');

        if (! $tenantId) {
            return false;
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return false;
        }

        return $this->tenantHasFeature($tenant, $feature);
    }

    protected function planCacheKey(Tenant $tenant): string
    {
        return "tenant_{$tenant->id}_plan_features";
    }
}