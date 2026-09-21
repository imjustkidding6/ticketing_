<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\AppSetting;
use App\Models\Client;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaPolicy;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Support\AdminTime;
use App\Support\TenantTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * TenantTime::timezone() used to answer "which zone am I in?" by asking whether
 * session('current_tenant_id') happened to be set: an admin who had visited a
 * workspace got that workspace's day boundaries, one who had not got
 * admin_timezone. Same page, same admin, different dates. These tests pin the
 * split that replaced it — TenantTime means the workspace, AdminTime means
 * platform staff, and neither changes meaning with browsing history.
 */
class AdminTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private function businessTenant(): Tenant
    {
        $plan = Plan::factory()->create(['slug' => 'business', 'features' => PlanFeature::forPlan('business')]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);
        SlaPolicy::factory()->create([
            'tenant_id' => $tenant->id, 'priority' => null, 'client_tier' => null, 'is_active' => true,
        ]);

        return $tenant;
    }

    /**
     * AppSetting::get() reads session('current_tenant_id'), so the row has to be
     * written with that session in place.
     */
    private function setTenantTimezone(Tenant $tenant, string $tz): void
    {
        AppSetting::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'key' => 'timezone',
            'value' => $tz,
            'type' => 'string',
            'group' => 'general',
        ]);
    }

    public function test_admin_time_uses_the_admin_timezone_regardless_of_session(): void
    {
        $tenant = $this->businessTenant();
        $this->setTenantTimezone($tenant, 'Asia/Manila');
        SystemSetting::set('admin_timezone', 'America/New_York');

        $this->assertSame('America/New_York', AdminTime::timezone());

        $this->withSession(['current_tenant_id' => $tenant->id]);
        session(['current_tenant_id' => $tenant->id]);

        $this->assertSame('America/New_York', AdminTime::timezone());
    }

    public function test_tenant_time_no_longer_falls_back_to_the_admin_timezone(): void
    {
        SystemSetting::set('admin_timezone', 'America/New_York');

        $this->assertFalse(session()->has('current_tenant_id'));
        $this->assertSame('UTC', TenantTime::timezone());
    }

    public function test_tenant_time_is_unchanged_inside_a_tenant_session(): void
    {
        $tenant = $this->businessTenant();
        $this->setTenantTimezone($tenant, 'Asia/Manila');
        SystemSetting::set('admin_timezone', 'America/New_York');
        session(['current_tenant_id' => $tenant->id]);

        $this->assertSame('Asia/Manila', TenantTime::timezone());
        $this->assertSame(
            '2026-08-26 16:00:00',
            TenantTime::startOfDay('2026-08-27')->format('Y-m-d H:i:s')
        );
        $this->assertSame('created_at + INTERVAL 28800 SECOND', TenantTime::sqlLocal('created_at'));
    }

    public function test_admin_sla_dashboard_window_is_the_same_for_both_admins(): void
    {
        Carbon::setTestNow('2026-08-27 03:00:00');

        $tenant = $this->businessTenant();
        $this->setTenantTimezone($tenant, 'Asia/Manila');
        SystemSetting::set('admin_timezone', 'America/New_York');

        $admin = User::factory()->create(['is_admin' => true]);

        $withoutTenant = $this->actingAs($admin)->get('/admin/sla');
        $withoutTenant->assertOk();

        $withTenant = $this->actingAs($admin)
            ->withSession(['current_tenant_id' => $tenant->id])
            ->get('/admin/sla');
        $withTenant->assertOk();

        $window = fn (string $html) => $this->slaWindowLine($html);

        $this->assertSame(
            $window($withoutTenant->getContent()),
            $window($withTenant->getContent()),
            'The admin SLA compliance window must not depend on whether the admin has visited a workspace.'
        );

        Carbon::setTestNow();
    }

    /**
     * The window line the page prints: "<scope> · <from> to <to> (UTC)".
     */
    private function slaWindowLine(string $html): string
    {
        $this->assertTrue(
            (bool) preg_match('/([^>]*\bto\b[^<]*\(UTC\))/', $html, $m),
            'The admin SLA page no longer prints the window it used.'
        );

        return trim(preg_replace('/\s+/', ' ', $m[1]));
    }

    /**
     * The public portal resolves its tenant from the URL slug and has no tenant
     * session, so it used to take the deleted SystemSetting branch. It turns out
     * nothing on that path formats through TenantTime at all — the track page
     * prints the raw stored (UTC) instant — so the split cannot move a portal
     * date. This pins that, and would fail if a portal date were ever routed
     * through a timezone helper without a tenant being set first.
     */
    public function test_portal_dates_are_unchanged(): void
    {
        $tenant = $this->businessTenant();
        $this->setTenantTimezone($tenant, 'Asia/Manila');
        SystemSetting::set('admin_timezone', 'America/New_York');

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $created = Carbon::parse('2026-08-27 01:30:00', 'UTC');
        $ticket = Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'created_by' => null,
            'tracking_token' => 'tz-token-001',
        ]);
        // The factory stamps its own timestamps; set the instant we want to read back.
        Ticket::withoutGlobalScopes()->where('id', $ticket->id)->update(['created_at' => $created]);

        $response = $this->get("/{$tenant->slug}/track-ticket/tz-token-001");

        $response->assertOk()
            // tenant/track-result.blade.php prints the stored instant directly.
            ->assertSee($created->format('F d, Y \a\t g:i A'))
            // Neither the workspace zone nor the admin zone moves it.
            ->assertDontSee($created->copy()->setTimezone('America/New_York')->format('F d, Y \a\t g:i A'))
            ->assertDontSee($created->copy()->setTimezone('Asia/Manila')->format('F d, Y \a\t g:i A'));
    }
}
