<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantUrlHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function createActiveTenant(?string $slug = null): Tenant
    {
        $plan = Plan::factory()->business()->create([
            'features' => PlanFeature::forPlan('business'),
        ]);
        $license = License::factory()->active()->forPlan($plan)->create();

        $attrs = ['license_id' => $license->id];
        if ($slug) {
            $attrs['slug'] = $slug;
        }

        return Tenant::factory()->create($attrs);
    }

    private function urlFor(Tenant $tenant, string $path = '/'): string
    {
        return app(TenantUrlHelper::class)->tenantUrl($tenant, $path);
    }

    public function test_tenant_dashboard_accessible_via_slug_prefix(): void
    {
        $tenant = $this->createActiveTenant('acme');
        $user = User::factory()->create();
        $tenant->addUser($user);

        $this->actingAs($user)
            ->get($this->urlFor($tenant, '/dashboard'))
            ->assertOk();

        $this->assertEquals($tenant->id, session('current_tenant_id'));
    }

    public function test_wrong_slug_redirects_to_main_domain(): void
    {
        $tenant = $this->createActiveTenant('acme');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get($this->urlFor($tenant, '/dashboard'))
            ->assertRedirect(config('app.url'));
    }

    public function test_nonexistent_slug_redirects(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/nonexistent/dashboard')
            ->assertRedirect(config('app.url'));
    }

    public function test_main_domain_routes_still_accessible(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/health')->assertOk();
    }

    public function test_admin_routes_still_work(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_portal_routes_still_work(): void
    {
        $tenant = $this->createActiveTenant();

        $this->get(route('portal.index', ['tenant' => $tenant->slug]))
            ->assertOk();
    }

    public function test_slug_check_endpoint_returns_available(): void
    {
        $this->getJson('/register/check-slug?slug=new-company')
            ->assertOk()
            ->assertJson(['available' => true]);
    }

    public function test_slug_check_endpoint_returns_unavailable_for_taken_slug(): void
    {
        Tenant::factory()->create(['slug' => 'taken']);

        $this->getJson('/register/check-slug?slug=taken')
            ->assertOk()
            ->assertJson(['available' => false]);
    }

    public function test_slug_check_endpoint_returns_unavailable_for_reserved_slug(): void
    {
        $this->getJson('/register/check-slug?slug=admin')
            ->assertOk()
            ->assertJson(['available' => false]);
    }

    public function test_slug_check_endpoint_returns_unavailable_for_short_slug(): void
    {
        $this->getJson('/register/check-slug?slug=ab')
            ->assertOk()
            ->assertJson(['available' => false]);
    }

    public function test_registration_creates_tenant_with_custom_slug(): void
    {
        $license = License::factory()->pending()->create();

        $this->post('/register', [
            'license_key' => $license->license_key,
            'company_name' => 'My Company',
            'app_slug' => 'my-company',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();

        $this->assertDatabaseHas('tenants', [
            'name' => 'My Company',
            'slug' => 'my-company',
        ]);
    }

    public function test_login_redirects_to_tenant_slug_prefix(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $tenant->addUser($user);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $expectedUrl = app(TenantUrlHelper::class)->tenantUrl($tenant, '/dashboard');
        $response->assertRedirect($expectedUrl);
    }

    public function test_tenant_switch_redirects_to_new_slug_prefix(): void
    {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        $tenant1->addUser($user);
        $tenant2->addUser($user);

        $response = $this->actingAs($user)
            ->withSession(['current_tenant_id' => $tenant1->id])
            ->post(route('tenant.switch'), [
                'tenant_id' => $tenant2->id,
            ]);

        $expectedUrl = app(TenantUrlHelper::class)->tenantUrl($tenant2, '/dashboard');
        $response->assertRedirect($expectedUrl);
    }

    public function test_slug_prefix_sets_permission_team_id(): void
    {
        $tenant = $this->createActiveTenant();
        $user = User::factory()->create();
        $tenant->addUser($user);

        $this->actingAs($user)
            ->get($this->urlFor($tenant, '/dashboard'))
            ->assertOk();

        $this->assertEquals(
            $tenant->id,
            app(\Spatie\Permission\PermissionRegistrar::class)->getPermissionsTeamId()
        );
    }
}
