<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\Client;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientControllerTest extends TestCase
{
    use RefreshDatabase;

    private function setupContext(): array
    {
        $plan = Plan::factory()->create(['slug' => 'business', 'features' => PlanFeature::forPlan('business')]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);
        $user = User::factory()->create();
        $tenant->addUser($user, 'admin');

        $roleService = app(TenantRoleService::class);
        $roleService->setTenantContext($tenant);
        $roleService->setupDefaultRoles($tenant);
        $roleService->syncRole($user, 'admin', $tenant);

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);

        return [$tenant, $user];
    }

    public function test_list_clients(): void
    {
        [$tenant] = $this->setupContext();

        $this->get($this->tenantUrl('/clients'))->assertOk();
    }

    public function test_create_client(): void
    {
        [$tenant] = $this->setupContext();

        $this->post($this->tenantUrl('/clients'), [
            'name' => 'Test Client Corp',
            'email' => 'client@example.com',
            'contact_person' => 'Jane Doe',
            'tier' => 'basic',
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('clients', ['name' => 'Test Client Corp', 'tenant_id' => $tenant->id]);
    }

    public function test_show_client(): void
    {
        [$tenant] = $this->setupContext();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->get($this->tenantUrl("/clients/{$client->id}"))->assertOk();
    }

    public function test_update_client(): void
    {
        [$tenant] = $this->setupContext();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->put($this->tenantUrl("/clients/{$client->id}"), [
            'name' => 'Updated Client',
            'email' => $client->email,
            'contact_person' => $client->contact_person,
            'tier' => 'premium',
            'status' => 'active',
        ])->assertRedirect();

        $client->refresh();
        $this->assertEquals('Updated Client', $client->name);
    }

    public function test_delete_client(): void
    {
        [$tenant] = $this->setupContext();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->delete($this->tenantUrl("/clients/{$client->id}"))->assertRedirect();
    }

    public function test_assign_agent_to_client(): void
    {
        [$tenant, $user] = $this->setupContext();
        $agent = User::factory()->create();
        $tenant->addUser($agent, 'member');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->post($this->tenantUrl("/clients/{$client->id}/assign-agent"), [
            'agent_id' => $agent->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('client_agent_assignments', [
            'client_id' => $client->id,
            'agent_id' => $agent->id,
        ]);
    }

    public function test_permission_required(): void
    {
        $plan = Plan::factory()->create(['slug' => 'biz2', 'features' => PlanFeature::forPlan('business')]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);
        $user = User::factory()->create();
        $tenant->addUser($user, 'member');

        $roleService = app(TenantRoleService::class);
        $roleService->setTenantContext($tenant);
        $roleService->setupDefaultRoles($tenant);
        $roleService->syncRole($user, 'agent', $tenant);

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);

        $this->get($this->tenantUrl('/clients'))->assertForbidden();
    }
}
