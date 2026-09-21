<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SlaPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * /admin/sla observes; it does not edit. SLA targets belong to the workspace, so
 * the platform surface lists every workspace's policies and points at the
 * workspace record for changes.
 *
 * The writes never worked here anyway: store()/seedDefaults()/updateTier() read
 * session('current_tenant_id'), which a platform admin arriving via /admin/login
 * does not have, so BelongsToTenant::creating wrote a null tenant_id into a NOT
 * NULL column. They failed safely and visibly — a 500. These tests pin the
 * replacement: the routes are gone, not aborting, and the view offers no control
 * that looks live.
 */
class AdminSlaReadOnlyTest extends TestCase
{
    use RefreshDatabase, SlaScopeFixtures;

    /** The nine route names removed from the admin group. */
    private const REMOVED = [
        'admin.sla.store',
        'admin.sla.update',
        'admin.sla.toggle',
        'admin.sla.destroy',
        'admin.sla.bulk-action',
        'admin.sla.seed-defaults',
        'admin.sla.edit-tier',
        'admin.sla.update-tier',
        'admin.sla.destroy-tier',
    ];

    public function test_admin_sla_write_routes_are_not_registered(): void
    {
        foreach (self::REMOVED as $name) {
            $this->assertFalse(Route::has($name), "{$name} is registered; the admin SLA surface is read-only by decision.");
        }
    }

    public function test_admin_sla_read_routes_are_still_registered(): void
    {
        // The complement matters: deleting the whole block would satisfy the test
        // above and take the page with it.
        $this->assertTrue(Route::has('admin.sla.index'));
        $this->assertTrue(Route::has('admin.sla.export'));
    }

    /**
     * The failure mode being replaced was a 500 from a NOT NULL violation, so what
     * matters is that these URLs refuse and write nothing.
     *
     * **Unregistering the admin route does not stop the request reaching the
     * controller.** `{slug}/sla` in routes/tenant.php still matches `/admin/sla` —
     * `admin` satisfies the slug regex — and EnsureTenantSession returns early for
     * an `is_admin` user (it forgets the slug and calls $next before any slug
     * lookup), so it never rejects the workspace and never consults
     * Tenant::RESERVED_SLUGS. What refuses is the tenant group's own gating:
     * `feature:sla_management` and, past it, `checkPermission('create sla
     * policies')` — the `is_admin` bypass in Controller.php applies on `admin.*`
     * route names only, so a platform admin holding no tenant role is refused like
     * anyone else. That is 403, deterministically, with or without a
     * current_tenant_id.
     *
     * Asserted exactly, not as a range: the range would stay green if someone
     * later gave platform staff a tenant role or widened the bypass, which is the
     * scenario in which the write would actually go live. That is also why the
     * row count is asserted — it is the claim, and the status is the mechanism.
     */
    public function test_posting_to_an_admin_sla_write_url_is_refused_and_writes_nothing(): void
    {
        $tenant = $this->businessTenant('AlphaCorp');
        $admin = User::factory()->create(['is_admin' => true]);

        foreach (['/admin/sla', '/admin/sla/seed-defaults'] as $url) {
            $this->actingAs($admin)
                ->post($url, ['name' => 'SHOULD-NOT-EXIST'])
                ->assertForbidden();

            // Same again with a workspace in session — the state in which the old
            // code would have had a tenant_id to write with.
            $this->actingAs($admin)
                ->withSession(['current_tenant_id' => $tenant->id])
                ->post($url, ['name' => 'SHOULD-NOT-EXIST'])
                ->assertForbidden();
        }

        $this->assertSame(0, SlaPolicy::withoutGlobalScopes()->count());
    }

    public function test_admin_sla_index_names_the_workspace_for_every_row(): void
    {
        $a = $this->businessTenant('AlphaCorp');
        $b = $this->businessTenant('BetaCorp');
        $this->catchAllPolicy($a, 'POLICY-AlphaCorp', 1);
        $this->catchAllPolicy($b, 'POLICY-BetaCorp', 2);

        // Cross-tenant rows with no workspace column are unreadable: the admin
        // cannot tell whose targets they are looking at.
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/sla')
            ->assertOk()
            ->assertSee('AlphaCorp')
            ->assertSee('BetaCorp');
    }

    public function test_admin_sla_index_links_each_row_to_its_workspace(): void
    {
        $a = $this->businessTenant('AlphaCorp');
        $b = $this->businessTenant('BetaCorp');
        $this->catchAllPolicy($a, 'POLICY-AlphaCorp', 1);
        $this->catchAllPolicy($b, 'POLICY-BetaCorp', 2);

        // Deleting the controls is only half the job; the admin still needs a way
        // through to editing, which is Impersonate on the workspace page. The link
        // is Alpine-bound, so the URL lives in the registry's JSON payload — hence
        // json_encode, which escapes the slashes.
        $response = $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/sla')
            ->assertOk();

        foreach ([$a, $b] as $tenant) {
            $response->assertSee(trim(json_encode(route('admin.tenants.show', $tenant->id)), '"'), false);
        }
    }

    public function test_admin_sla_index_offers_no_write_control(): void
    {
        $a = $this->businessTenant('AlphaCorp');
        $this->catchAllPolicy($a, 'POLICY-AlphaCorp', 1);

        $response = $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/sla')
            ->assertOk();

        $response->assertDontSee('Seed Defaults');
        $response->assertDontSee('Delete Policy');
        $response->assertDontSee('Create Policy');
        // The real point. A Route::has() fallback leaves a button that looks live
        // and does nothing, which is a worse outcome than a missing one.
        $response->assertDontSee('action="#"', false);
    }

    public function test_admin_sla_export_is_cross_tenant_regardless_of_session(): void
    {
        $a = $this->businessTenant('AlphaCorp');
        $b = $this->businessTenant('BetaCorp');
        $this->catchAllPolicy($a, 'POLICY-AlphaCorp', 1);
        $this->catchAllPolicy($b, 'POLICY-BetaCorp', 2);

        $admin = User::factory()->create(['is_admin' => true]);

        // export() built from SlaPolicy::query() — scoped — so it returned every
        // row only while no tenant happened to be in session. Same defect 047c298
        // removed from index(), on a route we are keeping.
        $withSession = $this->actingAs($admin)
            ->withSession(['current_tenant_id' => $a->id])
            ->get('/admin/sla/export');
        $withSession->assertOk();

        $withoutSession = $this->actingAs($admin)->get('/admin/sla/export');
        $withoutSession->assertOk();

        foreach ([$withSession, $withoutSession] as $response) {
            $csv = $response->streamedContent();
            $this->assertStringContainsString('Workspace', $csv);
            $this->assertStringContainsString('POLICY-AlphaCorp', $csv);
            $this->assertStringContainsString('POLICY-BetaCorp', $csv);
            $this->assertStringContainsString('BetaCorp', $csv);
        }
    }
}
