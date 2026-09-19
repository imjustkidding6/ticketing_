<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SlaPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage is a per-workspace matrix (3 tiers x 4 priorities). Divided by a literal
 * 12 across every workspace it read 800%; re-based it answers the question platform
 * staff actually have — who has not configured SLAs.
 */
class AdminSlaCoverageTest extends TestCase
{
    use RefreshDatabase, SlaScopeFixtures;

    public function test_coverage_is_reported_per_workspace_not_as_a_percentage_over_twelve(): void
    {
        $full = $this->businessTenant('FullCorp');
        foreach (SlaPolicy::TIERS as $tier) {
            foreach (SlaPolicy::PRIORITIES as $priority) {
                SlaPolicy::factory()->create([
                    'tenant_id' => $full->id,
                    'name' => "FULL-{$tier}-{$priority}",
                    'client_tier' => $tier,
                    'priority' => $priority,
                    'is_active' => true,
                ]);
            }
        }

        $partial = $this->businessTenant('PartialCorp');
        foreach (['low', 'medium', 'high'] as $priority) {
            SlaPolicy::factory()->create([
                'tenant_id' => $partial->id,
                'name' => "PART-basic-{$priority}",
                'client_tier' => 'basic',
                'priority' => $priority,
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/sla');

        $response->assertOk()
            ->assertSee('1 of 2 workspaces have a complete SLA matrix');

        // Nothing on the page may claim more than 100% coverage.
        preg_match_all('/(\d+(?:\.\d+)?)%/', strip_tags($response->getContent()), $matches);
        foreach ($matches[1] as $percentage) {
            $this->assertLessThanOrEqual(100, (float) $percentage, "Rendered {$percentage}% — coverage is being computed across workspaces.");
        }
    }
}
