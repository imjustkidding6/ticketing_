<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The headline risk of making /admin/sla cross-tenant: SlaPolicyController::index()
 * serves both surfaces off one routeIs('admin.*') branch, so a withoutGlobalScopes()
 * escaping that branch would silently widen every workspace's own SLA page.
 *
 * TenantIsolationGuardrailsTest already pins that a foreign *policy name* is absent.
 * This pins the ticket-derived half — the withCount() usage columns and the ops
 * panel — which that test cannot see.
 */
class TenantSlaPageScopeTest extends TestCase
{
    use RefreshDatabase, SlaScopeFixtures;

    public function test_tenant_sla_page_figures_exclude_other_tenants_tickets(): void
    {
        $a = $this->businessTenant('AlphaCorp');
        $b = $this->businessTenant('BetaCorp');

        $this->catchAllPolicy($a, 'POLICY-AlphaCorp');
        $foreignPolicy = $this->catchAllPolicy($b, 'ZZZ Foreign Tenant Policy');

        $foreignTicket = $this->closedTicket($b, $foreignPolicy, 40, 60, [
            'subject' => 'ZZZ Foreign Breached Ticket',
        ]);

        $user = User::factory()->create();
        $a->addUser($user, 'admin');

        $roleService = app(TenantRoleService::class);
        $roleService->setupDefaultRoles($a);
        $roleService->syncRole($user, 'admin', $a);

        $this->actingAs($user)
            ->withTenant($a)
            ->withSession(['current_tenant_id' => $a->id])
            ->get($this->tenantUrl('/sla'))
            ->assertOk()
            ->assertViewIs('sla.index')
            ->assertDontSee('ZZZ Foreign Tenant Policy')
            ->assertDontSee($foreignTicket->ticket_number)
            ->assertDontSee('ZZZ Foreign Breached Ticket');
    }

    public function test_tenant_sla_page_ticket_usage_counts_only_count_its_own_tickets(): void
    {
        $a = $this->businessTenant('AlphaCorp');
        $b = $this->businessTenant('BetaCorp');

        $policyA = $this->catchAllPolicy($a, 'POLICY-AlphaCorp');
        $this->closedTicket($a, $policyA, 1, 5);
        $this->closedTicket($b, $this->catchAllPolicy($b, 'POLICY-BetaCorp'), 40, 60);

        $user = User::factory()->create();
        $a->addUser($user, 'admin');

        $roleService = app(TenantRoleService::class);
        $roleService->setupDefaultRoles($a);
        $roleService->syncRole($user, 'admin', $a);

        $response = $this->actingAs($user)
            ->withTenant($a)
            ->withSession(['current_tenant_id' => $a->id])
            ->get($this->tenantUrl('/sla'));

        $response->assertOk();

        $policies = $response->viewData('policiesList');
        $this->assertCount(1, $policies, 'Only Alpha\'s policies belong on Alpha\'s SLA page.');
        $this->assertSame(1, (int) $policies->first()->total_tickets_count);
    }
}
