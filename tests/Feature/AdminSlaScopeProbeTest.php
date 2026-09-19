<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /admin/sla is cross-tenant *by intent* (CLAUDE.md: "Admin controllers
 * intentionally operate across tenants"). It used to be cross-tenant by accident:
 * TenantScope adds no WHERE clause when session('current_tenant_id') is unset, so
 * an admin who had impersonated a workspace saw a narrower page than one who had
 * not. These started life as bug probes and were inverted once the code was fixed,
 * per the convention in QaScenarioProbeTest.
 */
class AdminSlaScopeProbeTest extends TestCase
{
    use RefreshDatabase, SlaScopeFixtures;

    public function test_admin_sla_index_with_no_tenant_session(): void
    {
        $a = $this->businessTenant('AlphaCorp');
        $b = $this->businessTenant('BetaCorp');
        $this->catchAllPolicy($a, 'POLICY-AlphaCorp', 1);
        $this->catchAllPolicy($b, 'POLICY-BetaCorp', 2);

        // A platform admin arriving via /admin/login never passes through
        // EnsureTenantSession, so no current_tenant_id is ever set.
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/sla')
            ->assertOk()
            ->assertSee('POLICY-AlphaCorp')
            ->assertSee('POLICY-BetaCorp');
    }

    public function test_admin_sla_index_after_the_admin_has_visited_one_tenant(): void
    {
        $a = $this->businessTenant('AlphaCorp');
        $b = $this->businessTenant('BetaCorp');
        $this->catchAllPolicy($a, 'POLICY-AlphaCorp', 1);
        $this->catchAllPolicy($b, 'POLICY-BetaCorp', 2);

        // Same admin, same page — but a tenant id is now in the session, e.g. from
        // impersonation or from also holding a tenant membership. The page must not
        // change: what it shows is a property of the page, not of where the admin
        // has been.
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->withSession(['current_tenant_id' => $a->id])
            ->get('/admin/sla')
            ->assertOk()
            ->assertSee('POLICY-AlphaCorp')
            ->assertSee('POLICY-BetaCorp');
    }

    public function test_admin_sla_compliance_figures_are_identical_with_and_without_a_tenant_session(): void
    {
        $a = $this->businessTenant('AlphaCorp');
        $b = $this->businessTenant('BetaCorp');

        // One met, one missed — so a report that saw only Alpha would read 100%
        // and one that saw both reads 50%.
        $this->closedTicket($a, $this->catchAllPolicy($a, 'POLICY-AlphaCorp'), 1, 5);
        $this->closedTicket($b, $this->catchAllPolicy($b, 'POLICY-BetaCorp'), 40, 60);

        $admin = User::factory()->create(['is_admin' => true]);

        $withoutSession = $this->actingAs($admin)->get('/admin/sla');
        $withoutSession->assertOk();

        $withSession = $this->actingAs($admin)
            ->withSession(['current_tenant_id' => $a->id])
            ->get('/admin/sla');
        $withSession->assertOk();

        $this->assertSame(
            $this->compliancePanel($withoutSession->getContent()),
            $this->compliancePanel($withSession->getContent()),
            'The ticket-derived figures must not depend on session state. Rows alone '
            .'do not prove this — the withCount() closures carry Ticket\'s global scope.'
        );

        // And the figure is the cross-tenant one, not Alpha's.
        $withSession->assertSee('50%');
    }

    public function test_near_breach_client_names_do_not_depend_on_the_session_tenant(): void
    {
        $a = $this->businessTenant('AlphaCorp');
        $b = $this->businessTenant('BetaCorp');

        $this->catchAllPolicy($a, 'POLICY-AlphaCorp');
        $this->nearBreachTicket($b, 'ZZZ Beta Client');

        $admin = User::factory()->create(['is_admin' => true]);

        // The near-breach query is cross-tenant but its eager loads were not, so the
        // client resolved to null — rendered N/A — as soon as the admin had any
        // tenant in session. Same page, different browsing history.
        $this->actingAs($admin)->get('/admin/sla')
            ->assertOk()
            ->assertSee('ZZZ Beta Client');

        $this->actingAs($admin)
            ->withSession(['current_tenant_id' => $a->id])
            ->get('/admin/sla')
            ->assertOk()
            ->assertSee('ZZZ Beta Client');
    }

    /**
     * The rendered compliance block, normalised — the half of the page the original
     * probe never measured.
     */
    private function compliancePanel(string $html): string
    {
        $start = strpos($html, 'Platform SLA Compliance');
        $this->assertNotFalse($start, 'The admin SLA page should render a compliance panel.');

        $slice = substr($html, $start, 3000);

        return preg_replace('/\s+/', ' ', strip_tags($slice));
    }
}
