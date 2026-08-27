<?php

namespace App\Services;

use App\Enums\PlanFeature;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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

                // The column is cast to array, but a malformed or double-encoded
                // value comes back as a string and would fatal on the declared
                // array return type — taking down every request for the tenant.
                // Degrade to no features instead, and say so in the log.
                $features = $plan->features;

                if (! is_array($features)) {
                    if ($features !== null) {
                        Log::warning('Plan features are not an array; treating the tenant as having none.', [
                            'plan_id' => $plan->id,
                            'tenant_id' => $tenant->id,
                            'type' => get_debug_type($features),
                        ]);
                    }

                    return [];
                }

                return $features;
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
