<?php

namespace Tests;

use App\Models\Tenant;
use App\Services\TenantUrlHelper;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected ?Tenant $currentTestTenant = null;

    /**
     * Set the current tenant for test requests.
     */
    protected function withTenant(Tenant $tenant): static
    {
        $this->currentTestTenant = $tenant;

        return $this;
    }

    /**
     * Generate a tenant-prefixed URL for the current test tenant.
     */
    protected function tenantUrl(string $path = '/'): string
    {
        if (! $this->currentTestTenant) {
            throw new \RuntimeException('No tenant set. Call withTenant() first.');
        }

        return app(TenantUrlHelper::class)->tenantUrl($this->currentTestTenant, $path);
    }
}
