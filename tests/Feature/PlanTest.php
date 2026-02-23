<?php

namespace Tests\Feature;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_can_be_created(): void
    {
        $plan = Plan::factory()->create([
            'name' => 'Test Plan',
            'slug' => 'test-plan',
            'max_users' => 10,
            'max_tickets_per_month' => 200,
        ]);

        $this->assertDatabaseHas('plans', [
            'name' => 'Test Plan',
            'slug' => 'test-plan',
            'max_users' => 10,
            'max_tickets_per_month' => 200,
        ]);
    }

    public function test_start_plan_state(): void
    {
        $plan = Plan::factory()->start()->create();

        $this->assertEquals('Start', $plan->name);
        $this->assertEquals('start', $plan->slug);
        $this->assertEquals(5, $plan->max_users);
        $this->assertEquals(100, $plan->max_tickets_per_month);
    }

    public function test_business_plan_state(): void
    {
        $plan = Plan::factory()->business()->create();

        $this->assertEquals('Business', $plan->name);
        $this->assertEquals('business', $plan->slug);
        $this->assertEquals(25, $plan->max_users);
        $this->assertEquals(500, $plan->max_tickets_per_month);
    }

    public function test_enterprise_plan_state(): void
    {
        $plan = Plan::factory()->enterprise()->create();

        $this->assertEquals('Enterprise', $plan->name);
        $this->assertEquals('enterprise', $plan->slug);
        $this->assertNull($plan->max_users);
        $this->assertNull($plan->max_tickets_per_month);
    }

    public function test_scope_active_returns_only_active_plans(): void
    {
        Plan::factory()->count(2)->create(['is_active' => true]);
        Plan::factory()->count(3)->inactive()->create();

        $activePlans = Plan::active()->get();

        $this->assertCount(2, $activePlans);
    }

    public function test_has_unlimited_users_returns_true_when_max_users_is_null(): void
    {
        $plan = Plan::factory()->enterprise()->create();

        $this->assertTrue($plan->hasUnlimitedUsers());
    }

    public function test_has_unlimited_users_returns_false_when_max_users_is_set(): void
    {
        $plan = Plan::factory()->start()->create();

        $this->assertFalse($plan->hasUnlimitedUsers());
    }

    public function test_has_unlimited_tickets_returns_true_when_max_tickets_is_null(): void
    {
        $plan = Plan::factory()->enterprise()->create();

        $this->assertTrue($plan->hasUnlimitedTickets());
    }

    public function test_has_unlimited_tickets_returns_false_when_max_tickets_is_set(): void
    {
        $plan = Plan::factory()->start()->create();

        $this->assertFalse($plan->hasUnlimitedTickets());
    }

    public function test_plan_has_many_licenses(): void
    {
        $plan = Plan::factory()->create();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $plan->licenses());
    }
}
