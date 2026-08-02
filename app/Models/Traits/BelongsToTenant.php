<?php

namespace App\Models\Traits;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Boot the trait and add the global scope.
     */
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = TenantScope::getCurrentTenantId();
            }
        });
    }

    /**
     * Get the tenant that owns the model.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope a query to a specific tenant.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to ignore tenant filtering.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithoutTenantScope($query)
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }
}
