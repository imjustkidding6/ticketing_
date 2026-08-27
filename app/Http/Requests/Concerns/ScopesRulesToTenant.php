<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Tenant-aware replacements for the plain `exists:` rule.
 *
 * A bare `exists:clients,id` queries the raw table with no tenant predicate, so it
 * happily accepts an id belonging to another workspace — the model's global
 * TenantScope never sees the value, because validation runs before any model does.
 * Every foreign key accepted from a request must go through one of these.
 */
trait ScopesRulesToTenant
{
    /**
     * The tenant the current request is operating in.
     */
    protected function currentTenantId(): ?int
    {
        $tenantId = session('current_tenant_id');

        return $tenantId ? (int) $tenantId : null;
    }

    /**
     * `exists` against a tenant-owned table, constrained to the current tenant.
     *
     * With no tenant in session there is nothing to scope to, so the rule is made
     * unsatisfiable rather than falling back to an unscoped lookup.
     */
    protected function tenantExists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)
            ->where('tenant_id', $this->currentTenantId() ?? 0);
    }

    /**
     * Rules for a user id that must be an active member of the current tenant.
     *
     * Membership lives on the tenant_user pivot, so it takes a second `exists`
     * alongside the soft-delete check on the users table itself.
     *
     * @return array<int, Exists>
     */
    protected function tenantMemberRules(): array
    {
        return [
            Rule::exists('users', 'id')->whereNull('deleted_at'),
            Rule::exists('tenant_user', 'user_id')->where('tenant_id', $this->currentTenantId() ?? 0),
        ];
    }
}
