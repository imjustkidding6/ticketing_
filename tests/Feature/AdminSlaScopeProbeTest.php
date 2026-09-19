<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\License;
use App\Models\Plan;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QA EVIDENCE PROBE — documents what /admin/sla actually does today.
 * Following the convention in QaScenarioProbeTest: if a probe asserts a bug
 * exists, do not "fix" the probe. Fix the code, then invert it.
 */
class AdminSlaScopeProbeTest extends TestCase
{
    use RefreshDatabase;

    private function tenantWithPolicy(string $name, int $responseHours): Tenant
    {
        // plans.slug is unique, so both tenants share one plan record.
        $plan = Plan::firstOrCreate(
            ['slug' => 'business'],
            Plan::factory()->make(['slug' => 'business'])->getAttributes()
        );
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['name' => $name, 'license_id' => $license->id]);

        SlaPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => "POLICY-{$name}",
            'response_time_hours' => $responseHours,
            'priority' => null,
            'client_tier' => null,
            'is_active' => true,
        ]);

        return $tenant;
    }

    public function test_admin_sla_index_with_no_tenant_session(): void
    {
        $a = $this->tenantWithPolicy('AlphaCorp', 1);
        $b = $this->tenantWithPolicy('BetaCorp', 2);

        // A platform admin arriving via /admin/login never passes through
        // EnsureTenantSession, so no current_tenant_id is ever set.
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get('/admin/sla');
        $response->assertOk();

        $sawAlpha = str_contains($response->getContent(), 'POLICY-AlphaCorp');
        $sawBeta = str_contains($response->getContent(), 'POLICY-BetaCorp');

        fwrite(STDERR, "\n[probe] no tenant session -> AlphaCorp: "
            .($sawAlpha ? 'VISIBLE' : 'hidden')
            .', BetaCorp: '.($sawBeta ? 'VISIBLE' : 'hidden')."\n");

        // Documenting current behaviour. TenantScope returns null when
        // session('current_tenant_id') is unset, which applies NO where clause
        // at all rather than an empty result.
        $this->assertTrue(
            $sawAlpha && $sawBeta,
            'Expected the unscoped behaviour this probe documents. If this now fails, '
            .'the leak has been fixed — invert the probe.'
        );
        unset($a, $b);
    }

    public function test_admin_sla_index_after_the_admin_has_visited_one_tenant(): void
    {
        $a = $this->tenantWithPolicy('AlphaCorp', 1);
        $this->tenantWithPolicy('BetaCorp', 2);

        $admin = User::factory()->create(['is_admin' => true]);

        // Same admin, same page — but a tenant id is now in the session, e.g.
        // from impersonation or from also holding a tenant membership.
        $response = $this->actingAs($admin)
            ->withSession(['current_tenant_id' => $a->id])
            ->get('/admin/sla');
        $response->assertOk();

        $sawAlpha = str_contains($response->getContent(), 'POLICY-AlphaCorp');
        $sawBeta = str_contains($response->getContent(), 'POLICY-BetaCorp');

        fwrite(STDERR, '[probe] session tenant = Alpha -> AlphaCorp: '
            .($sawAlpha ? 'VISIBLE' : 'hidden')
            .', BetaCorp: '.($sawBeta ? 'VISIBLE' : 'hidden')."\n");

        // The point of this probe is that the SAME admin page shows a different
        // set of rows depending on browsing history — the result is not a
        // property of the page, which is what makes it hard to notice.
        $this->assertTrue($sawAlpha);
        $this->assertFalse(
            $sawBeta,
            'Expected the session tenant to narrow the page. If this fails, scoping changed.'
        );
    }
}
