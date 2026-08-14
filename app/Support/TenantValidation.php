<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

final class TenantValidation
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)
            ->where('tenant_id', self::tenantId());
    }

    public static function user(string $column = 'id'): Exists
    {
        $tenantId = self::tenantId();

        return Rule::exists('users', $column)
            ->whereNull('deleted_at')
            ->where(function (Builder $query) use ($tenantId): void {
                $query->whereExists(function (Builder $membership) use ($tenantId): void {
                    $membership->selectRaw('1')
                        ->from('tenant_user')
                        ->whereColumn('tenant_user.user_id', 'users.id')
                        ->where('tenant_user.tenant_id', $tenantId);
                });
            });
    }

    private static function tenantId(): int
    {
        $tenantId = session('current_tenant_id');

        if (! is_numeric($tenantId)) {
            throw new \LogicException('Tenant-aware validation requires an active tenant context.');
        }

        return (int) $tenantId;
    }
}
