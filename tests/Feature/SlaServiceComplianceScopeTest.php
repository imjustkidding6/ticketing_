<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\SlaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * getComplianceReport() takes the scope as an argument rather than inheriting it
 * from TenantScope. These tests pin that the argument decides the answer.
 */
class SlaServiceComplianceScopeTest extends TestCase
{
    use RefreshDatabase, SlaScopeFixtures;

    private function window(): array
    {
        $to = Carbon::now('UTC');

        return [$to->copy()->subDays(30), $to];
    }

    public function test_passing_a_tenant_id_restricts_the_report_to_that_tenant(): void
    {
        $a = $this->businessTenant('AlphaCorp');
        $b = $this->businessTenant('BetaCorp');

        // A meets its 4h/24h targets, B misses both.
        $this->closedTicket($a, $this->catchAllPolicy($a, 'POLICY-A'), 1, 5);
        $this->closedTicket($b, $this->catchAllPolicy($b, 'POLICY-B'), 40, 60);

        [$from, $to] = $this->window();
        $report = app(SlaService::class)->getComplianceReport($from, $to, $a->id);

        $this->assertSame(1, $report['total_with_sla']);
        $this->assertSame(1, $report['response_met']);
        $this->assertSame(0, $report['response_missed']);
        $this->assertSame('tenant', $report['scope']);
        $this->assertSame($a->id, $report['tenant_id']);
    }

    public function test_passing_null_covers_every_tenant(): void
    {
        $a = $this->businessTenant('AlphaCorp');
        $b = $this->businessTenant('BetaCorp');

        $this->closedTicket($a, $this->catchAllPolicy($a, 'POLICY-A'), 1, 5);
        $this->closedTicket($b, $this->catchAllPolicy($b, 'POLICY-B'), 40, 60);

        [$from, $to] = $this->window();
        $report = app(SlaService::class)->getComplianceReport($from, $to, null);

        $this->assertSame(2, $report['total_with_sla']);
        $this->assertSame(1, $report['response_met']);
        $this->assertSame(1, $report['response_missed']);
        $this->assertSame('platform', $report['scope']);
        $this->assertNull($report['tenant_id']);
    }

    public function test_a_zero_tenant_id_is_not_treated_as_every_tenant(): void
    {
        $a = $this->businessTenant('AlphaCorp');
        $b = $this->businessTenant('BetaCorp');

        $this->closedTicket($a, $this->catchAllPolicy($a, 'POLICY-A'), 1, 5);
        $this->closedTicket($b, $this->catchAllPolicy($b, 'POLICY-B'), 40, 60);

        // A caller that casts a missing session id to int would pass 0. when(0, ...)
        // is falsy, so the filter used to be skipped: the report returned every
        // tenant's tickets while labelling itself 'tenant'. Widening is bad; lying
        // about it in the return value is worse, because the caller cannot tell.
        [$from, $to] = $this->window();
        $report = app(SlaService::class)->getComplianceReport($from, $to, 0);

        $this->assertSame(0, $report['total_with_sla'], 'tenant 0 owns no tickets.');
        $this->assertSame('tenant', $report['scope']);
    }

    public function test_a_tenant_scoped_report_ignores_the_session_tenant(): void
    {
        $a = $this->businessTenant('AlphaCorp');
        $b = $this->businessTenant('BetaCorp');

        $this->closedTicket($a, $this->catchAllPolicy($a, 'POLICY-A'), 1, 5);
        $this->closedTicket($b, $this->catchAllPolicy($b, 'POLICY-B'), 40, 60);

        // Session says B; the argument says A. The argument has to win, otherwise
        // the two are merely ANDed and the "restricts" test above passes by luck.
        session(['current_tenant_id' => $b->id]);

        [$from, $to] = $this->window();
        $report = app(SlaService::class)->getComplianceReport($from, $to, $a->id);

        $this->assertSame(1, $report['total_with_sla']);
        $this->assertSame(1, $report['response_met']);
        $this->assertSame(0, $report['response_missed']);
    }
}
