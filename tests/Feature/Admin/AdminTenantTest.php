<?php

namespace Tests\Feature\Admin;

use App\Enums\PlanFeature;
use App\Models\AppSetting;
use App\Models\Distributor;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Ticket;
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

        $this->actingAs($admin)->get('/admin/tenants')
            ->assertOk()
            ->assertSee('Actions')
            ->assertSee('View')
            ->assertSee('Edit')
            ->assertSee('Delete');
    }

    public function test_show_tenant(): void
    {
        $admin = $this->adminUser();
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin)->get("/admin/tenants/{$tenant->id}")->assertOk();
    }

    public function test_edit_tenant_view(): void
    {
        $admin = $this->adminUser();
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin)->get("/admin/tenants/{$tenant->id}/edit")
            ->assertOk()
            ->assertSee('Edit Tenant')
            ->assertSee($tenant->name);
    }

    public function test_update_tenant_details(): void
    {
        $admin = $this->adminUser();
        $starterPlan = Plan::factory()->start()->create(['features' => PlanFeature::forPlan('start')]);
        $businessPlan = Plan::factory()->create(['slug' => 'business', 'features' => PlanFeature::forPlan('business')]);
        $distributor = Distributor::factory()->create(['is_active' => true]);
        $license = License::factory()->active()->forPlan($starterPlan)->create(['seats' => 5]);
        $tenant = Tenant::factory()->create(['license_id' => $license->id, 'name' => 'Old Name']);

        $response = $this->actingAs($admin)->put("/admin/tenants/{$tenant->id}", [
            'name' => 'New Tenant Name',
            'company_name' => 'Acme Corporation',
            'contact_email' => 'contact@acme.com',
            'status' => 'suspended',
            'plan_id' => $businessPlan->id,
            'seats' => 25,
            'distributor_id' => $distributor->id,
        ]);

        $response->assertRedirect('/admin/tenants');

        $tenant->refresh();
        $license->refresh();

        $this->assertEquals('New Tenant Name', $tenant->name);
        $this->assertTrue($tenant->isSuspended());
        $this->assertEquals($businessPlan->id, $license->plan_id);
        $this->assertEquals(25, $license->seats);
        $this->assertEquals($distributor->id, $license->distributor_id);

        $this->assertDatabaseHas('app_settings', [
            'tenant_id' => $tenant->id,
            'key' => 'company_name',
            'value' => 'Acme Corporation',
        ]);

        $this->assertDatabaseHas('app_settings', [
            'tenant_id' => $tenant->id,
            'key' => 'company_email',
            'value' => 'contact@acme.com',
        ]);
    }

    public function test_delete_tenant(): void
    {
        $admin = $this->adminUser();
        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($admin)->delete("/admin/tenants/{$tenant->id}");

        $response->assertRedirect('/admin/tenants');
        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
    }

    public function test_delete_tenant_cleans_up_records(): void
    {
        $admin = $this->adminUser();
        $plan = Plan::factory()->start()->create();
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);
        $user = User::factory()->create();
        $tenant->addUser($user, 'admin');

        $response = $this->actingAs($admin)->delete("/admin/tenants/{$tenant->id}");

        $response->assertRedirect('/admin/tenants');
        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
        $this->assertDatabaseMissing('tenant_user', ['tenant_id' => $tenant->id]);
        
        $license->refresh();
        $this->assertNull($license->tenant_id);
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
            ->assertSessionHas('admin_impersonating', true)
            ->assertSessionHas('current_tenant_id', $tenant->id);
    }
}
