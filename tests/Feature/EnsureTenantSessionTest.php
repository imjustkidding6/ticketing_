<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantUrlHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureTenantSessionTest extends TestCase
{
    use RefreshDatabase;

    private function tenantDashboardUrl(Tenant $tenant): string
    {
        return app(TenantUrlHelper::class)->tenantUrl($tenant, '/dashboard');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->get($this->tenantDashboardUrl($tenant));

        $response->assertRedirect('/login');
    }

    public function test_slug_resolves_tenant_and_sets_session(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenant->addUser($user);

        $response = $this->actingAs($user)
            ->get($this->tenantDashboardUrl($tenant));

        $response->assertOk();
        $this->assertEquals($tenant->id, session('current_tenant_id'));
    }

    public function test_user_accessing_wrong_slug_is_redirected(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($user)
            ->get($this->tenantDashboardUrl($tenant));

        $response->assertRedirect(config('app.url'));
    }

    public function test_admin_user_bypasses_tenant_middleware(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($admin)
            ->get($this->tenantDashboardUrl($tenant));

        $response->assertOk();
    }

    public function test_suspended_tenant_redirects(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenant->addUser($user);
        $tenant->suspend();

        $response = $this->actingAs($user)
            ->get($this->tenantDashboardUrl($tenant));

        $response->assertRedirect(config('app.url'));
    }

    public function test_inactive_tenant_redirects(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['is_active' => false]);
        $tenant->addUser($user);

        $response = $this->actingAs($user)
            ->get($this->tenantDashboardUrl($tenant));

        $response->assertRedirect(config('app.url'));
    }

    public function test_user_can_switch_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenant->addUser($user);

        $response = $this->actingAs($user)->post(route('tenant.switch'), [
            'tenant_id' => $tenant->id,
        ]);

        $expectedUrl = app(TenantUrlHelper::class)->tenantUrl($tenant, '/dashboard');
        $response->assertRedirect($expectedUrl);
        $this->assertEquals($tenant->id, session('current_tenant_id'));
    }

    public function test_user_cannot_switch_to_tenant_they_dont_belong_to(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($user)->post(route('tenant.switch'), [
            'tenant_id' => $tenant->id,
        ]);

        $response->assertForbidden();
    }

    public function test_user_cannot_switch_to_suspended_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenant->addUser($user);
        $tenant->suspend();

        $response = $this->actingAs($user)->post(route('tenant.switch'), [
            'tenant_id' => $tenant->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('tenant_id');
    }

    public function test_user_cannot_switch_to_inactive_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['is_active' => false]);
        $tenant->addUser($user);

        $response = $this->actingAs($user)->post(route('tenant.switch'), [
            'tenant_id' => $tenant->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('tenant_id');
    }

    public function test_tenant_select_page_shows_user_tenants(): void
    {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create(['name' => 'Acme Corp']);
        $tenant2 = Tenant::factory()->create(['name' => 'Beta Inc']);
        $tenant1->addUser($user, 'owner');
        $tenant2->addUser($user, 'member');

        $response = $this->actingAs($user)->get(route('tenant.select'));

        $response->assertOk();
        $response->assertSee('Acme Corp');
        $response->assertSee('Beta Inc');
    }

    public function test_tenant_select_page_excludes_inactive_tenants(): void
    {
        $user = User::factory()->create();
        $activeTenant = Tenant::factory()->create(['name' => 'Active Org']);
        $inactiveTenant = Tenant::factory()->create(['name' => 'Inactive Org', 'is_active' => false]);
        $activeTenant->addUser($user);
        $inactiveTenant->addUser($user);

        $response = $this->actingAs($user)->get(route('tenant.select'));

        $response->assertOk();
        $response->assertSee('Active Org');
        $response->assertDontSee('Inactive Org');
    }
}
