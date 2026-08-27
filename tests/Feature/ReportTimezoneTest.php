<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\AppSetting;
use App\Models\Client;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\ReportService;
use App\Services\TicketWorkflowService;
use App\Support\TenantTime;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Timestamps are stored in UTC and displayed in the tenant's timezone. Reports
 * used to filter and bucket on UTC boundaries while displaying tenant-local
 * times, so a report headed "today" could exclude a row it then printed as today.
 */
class ReportTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->create(['slug' => 'business', 'features' => PlanFeature::forPlan('business')]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $this->tenant = Tenant::factory()->create(['license_id' => $license->id]);
        SlaPolicy::factory()->create([
            'tenant_id' => $this->tenant->id, 'priority' => null,
            'client_tier' => null, 'is_active' => true,
        ]);

        $user = User::factory()->create();
        $this->tenant->addUser($user, 'owner');
        $this->actingAs($user)->withTenant($this->tenant)
            ->withSession(['current_tenant_id' => $this->tenant->id]);

        // A workspace eight hours ahead of the server.
        AppSetting::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'group' => 'general', 'key' => 'timezone', 'value' => 'Asia/Manila',
        ]);
    }

    private function closedTicketAt(Carbon $closedAtUtc): Ticket
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        return Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'status' => 'closed',
            'priority' => 'medium',
            'created_at' => $closedAtUtc->copy()->subHours(2),
            'closed_at' => $closedAtUtc,
        ]);
    }

    public function test_tenant_local_day_boundaries_are_not_the_servers(): void
    {
        $this->assertSame('Asia/Manila', TenantTime::timezone());

        // 27 Aug in Manila begins at 26 Aug 16:00 UTC.
        Carbon::setTestNow(Carbon::parse('2026-08-27 03:00:00', 'UTC'));

        $this->assertSame('2026-08-26 16:00:00', TenantTime::startOfDay()->toDateTimeString());
        $this->assertSame('2026-08-27 15:59:59', TenantTime::endOfDay()->toDateTimeString());

        Carbon::setTestNow();
    }

    public function test_a_ticket_closed_early_local_morning_counts_as_today(): void
    {
        // 07:00 on 27 Aug in Manila is 23:00 on 26 Aug UTC — the case that used
        // to fall into yesterday's bucket while being displayed as today.
        Carbon::setTestNow(Carbon::parse('2026-08-27 04:00:00', 'UTC')); // 12:00 Manila
        $ticket = $this->closedTicketAt(Carbon::parse('2026-08-26 23:00:00', 'UTC'));

        $metrics = app(TicketWorkflowService::class)->getMetrics();

        $this->assertSame(1, $metrics['closed_today'],
            'A ticket closed at 07:00 local on the report date must count as closed today');

        // And the display agrees with the bucket.
        $this->assertStringStartsWith('08/27/2026', TenantTime::format($ticket->closed_at, 'm/d/Y g:i A'));

        Carbon::setTestNow();
    }

    public function test_a_ticket_closed_late_local_evening_does_not_leak_into_tomorrow(): void
    {
        // 23:00 on 26 Aug Manila is 15:00 on 26 Aug UTC — same day either way,
        // but the server's "today" on the 27th must not claim it.
        Carbon::setTestNow(Carbon::parse('2026-08-27 04:00:00', 'UTC'));
        $this->closedTicketAt(Carbon::parse('2026-08-26 15:00:00', 'UTC'));

        $this->assertSame(0, app(TicketWorkflowService::class)->getMetrics()['closed_today']);

        Carbon::setTestNow();
    }

    public function test_trend_buckets_rows_by_the_tenants_calendar(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-27 04:00:00', 'UTC'));

        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        // Created 00:30 on 27 Aug Manila = 16:30 on 26 Aug UTC.
        Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'status' => 'open',
            'priority' => 'medium',
            'created_at' => Carbon::parse('2026-08-26 16:30:00', 'UTC'),
        ]);

        $trend = collect(app(ReportService::class)->getTrend([], 'daily'))->keyBy('label');

        $this->assertSame(1, $trend['2026-08-27']['count'] ?? 0,
            'The ticket was created just after local midnight on the 27th');
        $this->assertSame(0, $trend['2026-08-26']['count'] ?? 0);

        Carbon::setTestNow();
    }

    public function test_a_utc_tenant_is_unaffected(): void
    {
        AppSetting::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('key', 'timezone')
            ->update(['value' => 'UTC']);

        Carbon::setTestNow(Carbon::parse('2026-08-27 12:00:00', 'UTC'));

        $this->assertSame('2026-08-27 00:00:00', TenantTime::startOfDay()->toDateTimeString());
        $this->assertSame('created_at', TenantTime::sqlLocal('created_at'),
            'With no offset the SQL expression stays a plain column');

        Carbon::setTestNow();
    }
}
