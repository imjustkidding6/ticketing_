<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureTenantSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_not_tenant_selector(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_user_with_one_active_tenant_is_auto_set(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenant->addUser($user);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $this->assertEquals($tenant->id, session('current_tenant_id'));
    }

    public function test_user_with_multiple_tenants_is_redirected_to_selector(): void
    {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        $tenant1->addUser($user);
        $tenant2->addUser($user);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('tenant.select'));
    }

    public function test_user_with_valid_tenant_in_session_can_proceed(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenant->addUser($user);

        $response = $this->actingAs($user)
            ->withSession(['current_tenant_id' => $tenant->id])
            ->get('/dashboard');

        $response->assertOk();
    }

    public function test_admin_user_bypasses_tenant_middleware(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
    }

    public function test_user_with_invalid_tenant_in_session_is_redirected(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['current_tenant_id' => $tenant->id])
            ->get('/dashboard');

        $response->assertRedirect(route('tenant.select'));
        $this->assertNull(session('current_tenant_id'));
    }

    public function test_user_with_suspended_tenant_in_session_is_redirected(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenant->addUser($user);
        $tenant->suspend();

        $response = $this->actingAs($user)
            ->withSession(['current_tenant_id' => $tenant->id])
            ->get('/dashboard');

        $response->assertRedirect(route('tenant.select'));
        $this->assertNull(session('current_tenant_id'));
    }

    public function test_user_with_inactive_tenant_in_session_is_redirected(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['is_active' => false]);
        $tenant->addUser($user);

        $response = $this->actingAs($user)
            ->withSession(['current_tenant_id' => $tenant->id])
            ->get('/dashboard');

        $response->assertRedirect(route('tenant.select'));
        $this->assertNull(session('current_tenant_id'));
    }

    public function test_user_with_no_tenants_is_redirected_to_no_tenant_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('dashboard.no-tenant'));
    }

    public function test_user_can_switch_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenant->addUser($user);

        $response = $this->actingAs($user)->post(route('tenant.switch'), [
            'tenant_id' => $tenant->id,
        ]);

        $response->assertRedirect(route('dashboard'));
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
