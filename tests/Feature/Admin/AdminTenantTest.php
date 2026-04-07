<?php

namespace Tests\Feature\Admin;

use App\Enums\PlanFeature;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTenantTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_non_admin_cannot_access(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/tenants')->assertForbidden();
    }

    public function test_list_tenants(): void
    {
        $admin = $this->adminUser();
        Tenant::factory()->count(3)->create();

        $this->actingAs($admin)->get('/admin/tenants')->assertOk();
    }

    public function test_show_tenant(): void
    {
        $admin = $this->adminUser();
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin)->get("/admin/tenants/{$tenant->id}")->assertOk();
    }

    public function test_suspend_tenant(): void
    {
        $admin = $this->adminUser();
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin)->post("/admin/tenants/{$tenant->id}/suspend")->assertRedirect();

        $tenant->refresh();
        $this->assertNotNull($tenant->suspended_at);
    }

    public function test_unsuspend_tenant(): void
    {
        $admin = $this->adminUser();
        $tenant = Tenant::factory()->create(['suspended_at' => now()]);

        $this->actingAs($admin)->post("/admin/tenants/{$tenant->id}/unsuspend")->assertRedirect();

        $tenant->refresh();
        $this->assertNull($tenant->suspended_at);
    }

    public function test_change_plan(): void
    {
        $admin = $this->adminUser();
        $starterPlan = Plan::factory()->start()->create(['features' => PlanFeature::forPlan('start')]);
        $businessPlan = Plan::factory()->create(['slug' => 'business', 'features' => PlanFeature::forPlan('business')]);
        $license = License::factory()->active()->forPlan($starterPlan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);

        $this->actingAs($admin)->post("/admin/tenants/{$tenant->id}/change-plan", [
            'plan_id' => $businessPlan->id,
        ])->assertRedirect();

        $license->refresh();
        $this->assertEquals($businessPlan->id, $license->plan_id);
    }

    public function test_update_seats(): void
    {
        $admin = $this->adminUser();
        $plan = Plan::factory()->start()->create();
        $license = License::factory()->active()->forPlan($plan)->create(['seats' => 5]);
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);

        $this->actingAs($admin)->post("/admin/tenants/{$tenant->id}/update-seats", [
            'seats' => 50,
        ])->assertRedirect();

        $license->refresh();
        $this->assertEquals(50, $license->seats);
    }

    public function test_impersonate_tenant(): void
    {
        $admin = $this->adminUser();
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin)->post("/admin/tenants/{$tenant->id}/impersonate")
            ->assertRedirect()
            ->assertSessionHas('impersonating_tenant_id', $tenant->id);
    }
}
