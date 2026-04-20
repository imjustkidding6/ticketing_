<?php

namespace Tests\Feature\Admin;

use App\Models\Distributor;
use App\Models\License;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLicenseTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_non_admin_cannot_access(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/licenses')->assertForbidden();
    }

    public function test_list_licenses(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->get('/admin/licenses')->assertOk();
    }

    public function test_create_license(): void
    {
        $admin = $this->adminUser();
        $distributor = Distributor::factory()->create();
        $plan = Plan::factory()->create();

        $this->actingAs($admin)->post('/admin/licenses', [
            'distributor_id' => $distributor->id,
            'plan_id' => $plan->id,
            'seats' => 10,
            'expires_at' => now()->addYear()->toDateString(),
            'grace_days' => 30,
        ])->assertRedirect();

        $this->assertDatabaseHas('licenses', [
            'distributor_id' => $distributor->id,
            'plan_id' => $plan->id,
            'seats' => 10,
        ]);
    }

    public function test_revoke_license(): void
    {
        $admin = $this->adminUser();
        $license = License::factory()->active()->create();

        $this->actingAs($admin)->post("/admin/licenses/{$license->id}/revoke")->assertRedirect();

        $license->refresh();
        $this->assertEquals('revoked', $license->status);
    }

    public function test_expiration_must_be_future(): void
    {
        $admin = $this->adminUser();
        $distributor = Distributor::factory()->create();
        $plan = Plan::factory()->create();

        $this->actingAs($admin)->post('/admin/licenses', [
            'distributor_id' => $distributor->id,
            'plan_id' => $plan->id,
            'seats' => 10,
            'expires_at' => now()->subDay()->toDateString(),
            'grace_days' => 0,
        ])->assertSessionHasErrors('expires_at');
    }

    public function test_grace_days_bounds(): void
    {
        $admin = $this->adminUser();
        $distributor = Distributor::factory()->create();
        $plan = Plan::factory()->create();

        $this->actingAs($admin)->post('/admin/licenses', [
            'distributor_id' => $distributor->id,
            'plan_id' => $plan->id,
            'seats' => 10,
            'expires_at' => now()->addYear()->toDateString(),
            'grace_days' => 100,
        ])->assertSessionHasErrors('grace_days');
    }
}
