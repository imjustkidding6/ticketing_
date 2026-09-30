<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\AiAgent;
use App\Models\AppSetting;
use App\Models\License;
use App\Models\Plan;
use App\Models\Skill;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PlanService;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use ReflectionClass;
use Tests\TestCase;

/**
 * Criteria 27–29: who may reach the AI configuration screens, and on which plan.
 *
 * Table-driven over every route in the feature, because the failure this guards against is
 * one action missing its check while its siblings have theirs.
 */
class AiAgentPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
    }

    private function createTenantOn(string $planSlug): Tenant
    {
        // 'start', never 'starter' — forPlan('starter') hits the default arm and returns [].
        // first()-or-create rather than firstOrCreate(): passing a factory's getAttributes()
        // as the second argument double-encodes the JSON `features` cast, leaving the plan
        // with no features and every route 403 for the wrong reason.
        $plan = Plan::query()->where('slug', $planSlug)->first()
            ?? Plan::factory()->create(['slug' => $planSlug, 'features' => PlanFeature::forPlan($planSlug)]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);

        SlaPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'priority' => null,
            'client_tier' => null,
            'is_active' => true,
        ]);

        // PlanService caches features for 300s under the tenant id, and tenant ids repeat
        // across tests in one process — a stale entry from another plan reads as a 403.
        app(PlanService::class)->clearCache($tenant);

        return $tenant;
    }

    /**
     * @param  string|null  $role  a seeded tenant role, or null for a workspace owner
     */
    private function actAs(Tenant $tenant, ?string $role, bool $enabled = true): User
    {
        $user = User::factory()->create();
        $tenant->addUser($user, $role === null ? 'owner' : 'member');

        $roleService = app(TenantRoleService::class);
        $roleService->setupDefaultRoles($tenant);

        if ($role !== null) {
            $roleService->syncRole($user, $role, $tenant);
        }

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);

        session(['current_tenant_id' => $tenant->id]);
        AppSetting::set('ai_agents_enabled', $enabled, 'boolean', 'ai');

        return $user;
    }

    /**
     * Every route in the feature, as [method, path, payload].
     *
     * Payloads are valid, because a FormRequest resolves before the controller's permission
     * check: an invalid body would return 302-with-errors and the test would never reach the
     * 403 it is asserting. Destructive routes come last, so an earlier delete does not make a
     * later route 404 on a row that no longer exists.
     *
     * @return list<array{0: string, 1: string, 2: array<string, mixed>}>
     */
    private function routes(Tenant $tenant): array
    {
        $skill = Skill::factory()->create(['tenant_id' => $tenant->id]);
        $agent = AiAgent::factory()->create(['tenant_id' => $tenant->id]);

        return [
            ['get', '/skills', []],
            ['get', '/skills/create', []],
            ['post', '/skills', ['name' => 'Permission Probe Skill']],
            ['get', "/skills/{$skill->id}/edit", []],
            ['put', "/skills/{$skill->id}", ['name' => $skill->name]],
            ['post', "/skills/{$skill->id}/toggle", []],
            ['get', '/ai-agents', []],
            ['get', '/ai-agents/create', []],
            ['post', '/ai-agents', ['name' => 'Permission Probe Agent', 'role_type' => 'specialist']],
            ['get', "/ai-agents/{$agent->id}/edit", []],
            ['put', "/ai-agents/{$agent->id}", ['name' => $agent->name, 'role_type' => $agent->role_type]],
            ['post', "/ai-agents/{$agent->id}/toggle", []],
            ['put', "/ai-agents/{$agent->id}/skills", ['skills' => [['skill_id' => $skill->id, 'proficiency' => 'expert']]]],
            ['get', '/ai-agents/catalogue', []],
            ['post', '/ai-agents/catalogue', []],
            ['delete', "/ai-agents/{$agent->id}/skills/{$skill->id}", []],
            ['delete', "/ai-agents/{$agent->id}", []],
            ['delete', "/skills/{$skill->id}", []],
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: array<string, mixed>}>
     */
    private function hubRoutes(): array
    {
        return [['get', '/ai-agents/setup', []], ['post', '/ai-agents/setup', ['ai_agents_enabled' => '1']]];
    }

    public function test_an_agent_role_gets_403_on_every_ai_configuration_route_and_sees_no_sidebar_entry(): void
    {
        $tenant = $this->createTenantOn('enterprise');
        $this->actAs($tenant, 'agent');

        foreach (array_merge($this->routes($tenant), $this->hubRoutes()) as [$method, $path, $payload]) {
            $this->{$method}($this->tenantUrl($path), $payload)
                ->assertForbidden("{$method} {$path} should be forbidden for an agent.");
        }

        $this->get($this->tenantUrl('/dashboard'))->assertOk()->assertDontSee('ai-agents/setup');
    }

    public function test_a_viewer_role_reaches_no_create_update_or_delete_route(): void
    {
        $tenant = $this->createTenantOn('enterprise');
        $this->actAs($tenant, 'viewer');

        foreach ($this->routes($tenant) as [$method, $path, $payload]) {
            $this->{$method}($this->tenantUrl($path), $payload)
                ->assertForbidden("{$method} {$path} should be forbidden for a viewer.");
        }
    }

    public function test_an_admin_reaches_every_route_and_sees_the_sidebar_entry(): void
    {
        $tenant = $this->createTenantOn('enterprise');
        $this->actAs($tenant, 'admin');

        foreach (array_merge($this->routes($tenant), $this->hubRoutes()) as [$method, $path, $payload]) {
            $response = $this->{$method}($this->tenantUrl($path), $payload);

            $this->assertContains(
                $response->getStatusCode(),
                [200, 302],
                "{$method} {$path} should be reachable by an admin, got {$response->getStatusCode()}.",
            );

            // Without this the positive half of the table is unfalsifiable: a route that
            // rejected the payload at validation also redirects, so a 302 alone would read
            // as "reachable" for a request that did nothing.
            if ($response->getStatusCode() === 302) {
                $response->assertSessionHasNoErrors();
            }
        }

        $this->get($this->tenantUrl('/dashboard'))->assertOk()->assertSee('ai-agents/setup');
    }

    public function test_a_business_workspace_gets_403_on_every_route(): void
    {
        $tenant = $this->createTenantOn('business');
        $this->actAs($tenant, 'admin');

        // The plan gate is the route middleware, so even the hub is refused: without the
        // feature there is nothing to switch on.
        foreach (array_merge($this->routes($tenant), $this->hubRoutes()) as [$method, $path, $payload]) {
            $this->{$method}($this->tenantUrl($path), $payload)
                ->assertForbidden("{$method} {$path} should be forbidden on a Business plan.");
        }
    }

    public function test_an_enterprise_workspace_with_the_feature_switched_off_gets_403_except_the_hub(): void
    {
        $tenant = $this->createTenantOn('enterprise');
        $this->actAs($tenant, 'admin', enabled: false);

        foreach ($this->routes($tenant) as [$method, $path, $payload]) {
            $this->{$method}($this->tenantUrl($path), $payload)
                ->assertForbidden("{$method} {$path} should be forbidden while the opt-in is off.");
        }

        // The exemption that makes the switch reachable again.
        $this->get($this->tenantUrl('/ai-agents/setup'))->assertOk();
        $this->post($this->tenantUrl('/ai-agents/setup'), ['ai_agents_enabled' => '1'])->assertRedirect();

        $this->assertTrue((bool) AppSetting::get('ai_agents_enabled'));
        $this->get($this->tenantUrl('/skills'))->assertOk();
    }

    public function test_an_owner_bypasses_the_permission_check_but_not_the_plan_gate(): void
    {
        $enterprise = $this->createTenantOn('enterprise');
        $this->actAs($enterprise, null);
        $this->get($this->tenantUrl('/skills'))->assertOk();

        $business = $this->createTenantOn('business');
        $this->actAs($business, null);
        $this->get($this->tenantUrl('/skills'))->assertForbidden();
    }

    public function test_the_permission_is_granted_to_admin_only_and_only_on_an_ai_agents_tenant(): void
    {
        $this->assertContains('manage ai agents', TenantRoleService::PERMISSION_GROUPS['AI']);
        $this->assertContains('manage ai agents', TenantRoleService::LEGACY_PERMISSION_MAP['manage settings']);

        $reflection = new ReflectionClass(TenantRoleService::class);

        // Both constants are private, so reflection is the only way to assert the shape —
        // and the shape is the point: a later hand granting this to 'manager' should red.
        foreach ($reflection->getConstant('BASE_ROLE_PERMISSIONS') as $role => $permissions) {
            $this->assertNotContains(
                'manage ai agents',
                $permissions,
                "Role '{$role}' must not hold 'manage ai agents' in the base matrix — it is keyed on the ai_agents feature.",
            );
        }

        $feature = $reflection->getConstant('FEATURE_PERMISSIONS');
        $this->assertSame(['admin' => ['manage ai agents']], $feature['ai_agents']);

        // And the resulting grants: an Enterprise admin holds it, nobody else does.
        $enterprise = $this->createTenantOn('enterprise');
        $defaults = app(TenantRoleService::class)->defaultsForTenant($enterprise);
        foreach ($defaults as $role => $granted) {
            $this->assertSame(
                $role === 'admin',
                in_array('manage ai agents', $granted, true),
                "Unexpected 'manage ai agents' grant state for role '{$role}'.",
            );
        }

        // A Business workspace's admin never receives it — it gates a feature they cannot reach.
        $business = $this->createTenantOn('business');
        $this->assertNotContains(
            'manage ai agents',
            app(TenantRoleService::class)->defaultsForTenant($business)['admin'],
        );
    }
}
