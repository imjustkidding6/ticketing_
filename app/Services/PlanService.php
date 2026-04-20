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
        $featureValue = $feature instanceof PlanFeature ? $feature->value : $feature;
        $features = $this->getTenantFeatures($tenant);

        return in_array($featureValue, $features, true);
    }

    /**
     * Get all features available for a tenant.
     *
     * @return list<string>
     */
    public function getTenantFeatures(Tenant $tenant): array
    {
        return Cache::remember(
            "tenant_{$tenant->id}_features",
            300,
            function () use ($tenant) {
                $plan = $tenant->plan();

                if (! $plan) {
                    return [];
                }

                return $plan->features ?? [];
            }
        );
    }

    /**
     * Clear the cached features for a tenant.
     */
    public function clearCache(Tenant $tenant): void
    {
        Cache::forget("tenant_{$tenant->id}_features");
    }

    /**
     * Check if the current session tenant has a feature.
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
}
