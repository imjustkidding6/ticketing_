<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A figure that cannot be computed is stated as uncomputable. The page used to fall
 * back to hard-coded 98.4 / 97.8 / 100.0, which is a number nobody measured.
 */
class AdminSlaEmptyStateTest extends TestCase
{
    use RefreshDatabase, SlaScopeFixtures;

    public function test_a_window_with_no_closed_sla_tickets_shows_a_dash_not_a_number(): void
    {
        $a = $this->businessTenant('AlphaCorp');
        $this->catchAllPolicy($a, 'POLICY-AlphaCorp');

        $response = $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/sla');

        $response->assertOk()
            ->assertSee('No closed tickets with an SLA in this window')
            ->assertDontSee('98.4')
            ->assertDontSee('97.8')
            ->assertDontSee('100.0');
    }

    public function test_excluded_tickets_without_a_policy_are_disclosed(): void
    {
        $a = $this->businessTenant('AlphaCorp');
        $this->closedTicket($a, null, 1, 5);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/sla')
            ->assertOk()
            ->assertSee('1 closed tickets excluded (no SLA policy)');
    }
}
