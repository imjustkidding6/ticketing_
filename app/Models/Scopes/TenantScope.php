<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = static::getCurrentTenantId();

        if ($tenantId !== null) {
            $builder->where($model->getTable() . '.tenant_id', $tenantId);
        }
    }

    /**
     * Get the current tenant ID from the authenticated user's session.
     */
    public static function getCurrentTenantId(): ?int
    {
        if (! auth()->check()) {
            return null;
        }

        return session('current_tenant_id');
    }
}
