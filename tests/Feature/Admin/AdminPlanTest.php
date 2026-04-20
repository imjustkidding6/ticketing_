<?php

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPlanTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_non_admin_cannot_access(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/plans')->assertForbidden();
    }

    public function test_list_plans(): void
    {
        $admin = $this->adminUser();
        Plan::factory()->count(3)->create();

        $this->actingAs($admin)->get('/admin/plans')->assertOk();
    }

    public function test_edit_plan(): void
    {
        $admin = $this->adminUser();
        $plan = Plan::factory()->create();

        $this->actingAs($admin)->get("/admin/plans/{$plan->id}/edit")->assertOk();
    }

    public function test_update_plan(): void
    {
        $admin = $this->adminUser();
        $plan = Plan::factory()->create();

        $this->actingAs($admin)->put("/admin/plans/{$plan->id}", [
            'name' => 'Updated Plan',
            'slug' => $plan->slug,
            'max_users' => 100,
            'max_tickets_per_month' => 1000,
        ])->assertRedirect();

        $plan->refresh();
        $this->assertEquals('Updated Plan', $plan->name);
    }

    public function test_slug_uniqueness(): void
    {
        $admin = $this->adminUser();
        Plan::factory()->create(['slug' => 'existing-slug']);
        $plan = Plan::factory()->create();

        $this->actingAs($admin)->put("/admin/plans/{$plan->id}", [
            'name' => 'Test',
            'slug' => 'existing-slug',
            'max_users' => 10,
            'max_tickets_per_month' => 100,
        ])->assertSessionHasErrors('slug');
    }
}
