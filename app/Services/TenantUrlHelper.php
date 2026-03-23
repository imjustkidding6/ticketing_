<?php

namespace App\Services;

use App\Models\Tenant;

class TenantUrlHelper
{
    /**
     * Generate an absolute URL for a path under a tenant's slug prefix.
     */
    public function tenantUrl(Tenant $tenant, string $path = '/'): string
    {
        $base = rtrim(config('app.url'), '/').'/'.$tenant->slug;

        return rtrim($base, '/').'/'.ltrim($path, '/');
    }
}
