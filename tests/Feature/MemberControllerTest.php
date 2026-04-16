<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberControllerTest extends TestCase
{
    use RefreshDatabase;

    private function setupContext(): array
    {
        $plan = Plan::factory()->create(['slug' => 'business', 'features' => PlanFeature::forPlan('business'), 'seats' => 25]);
        $license = License::factory()->active()->forPlan($plan)->create(['seats' => 25]);
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);
        $user = User::factory()->create();
        $tenant->addUser($user, 'owner');

        $roleService = app(TenantRoleService::class);
        $roleService->setTenantContext($tenant);
        $roleService->setupDefaultRoles($tenant);
        $roleService->syncRole($user, 'admin', $tenant);

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);

        return [$tenant, $user];
    }

    public function test_list_members(): void
    {
        [$tenant] = $this->setupContext();

        $this->get($this->tenantUrl('/members'))->assertOk();
    }

    public function test_create_member(): void
    {
        [$tenant] = $this->setupContext();

        $this->post($this->tenantUrl('/members'), [
            'name' => 'New Agent',
            'email' => 'agent@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'agent',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'agent@example.com']);
    }

    public function test_show_member_with_performance(): void
    {
        [$tenant] = $this->setupContext();
        $member = User::factory()->create();
        $tenant->addUser($member, 'member');

        $this->get($this->tenantUrl("/members/{$member->id}"))->assertOk();
    }

    public function test_show_member_performance_counts_closed_tickets_in_reporting_window(): void
    {
        [$tenant] = $this->setupContext();
        $member = User::factory()->create();
        $tenant->addUser($member, 'member');

        $ticket = Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'assigned_to' => $member->id,
            'created_at' => now()->subDays(20),
            'status' => 'closed',
            'closed_at' => now()->subDay(),
        ]);

        $response = $this->get($this->tenantUrl("/members/{$member->id}?perf_from=" . now()->subDays(7)->toDateString() . '&perf_to=' . now()->toDateString()));

        $response->assertOk();
        $response->assertViewHas('performance', function (array $performance) use ($ticket) {
            return $performance['closed'] === 1
                && $performance['total_assigned'] === 1
                && $performance['by_status']['closed'] === 1;
        });
    }

    public function test_show_member_performance_defaults_to_ticket_history(): void
    {
        [$tenant] = $this->setupContext();
        $member = User::factory()->create();
        $tenant->addUser($member, 'member');

        Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'assigned_to' => $member->id,
            'created_at' => now()->subDays(60),
            'status' => 'closed',
            'closed_at' => now()->subDays(2),
        ]);

        $response = $this->get($this->tenantUrl("/members/{$member->id}"));

        $response->assertOk();
        $response->assertViewHas('performance', function (array $performance): bool {
            return $performance['closed'] === 1
                && $performance['total_assigned'] === 1;
        });
    }

    public function test_update_member(): void
    {
        [$tenant] = $this->setupContext();
        $member = User::factory()->create();
        $tenant->addUser($member, 'member');

        $roleService = app(TenantRoleService::class);
        $roleService->syncRole($member, 'agent', $tenant);

        $this->put($this->tenantUrl("/members/{$member->id}"), [
            'name' => 'Updated Name',
            'email' => $member->email,
            'role' => 'agent',
        ])->assertRedirect();

        $member->refresh();
        $this->assertEquals('Updated Name', $member->name);
    }

    public function test_cannot_delete_self(): void
    {
        [$tenant, $user] = $this->setupContext();

        $this->delete($this->tenantUrl("/members/{$user->id}"))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_cannot_delete_owner(): void
    {
        [$tenant, $owner] = $this->setupContext();

        // Create another admin to try deleting the owner
        $admin = User::factory()->create();
        $tenant->addUser($admin, 'admin');
        $roleService = app(TenantRoleService::class);
        $roleService->syncRole($admin, 'admin', $tenant);
        $this->actingAs($admin)->withSession(['current_tenant_id' => $tenant->id]);

        $this->delete($this->tenantUrl("/members/{$owner->id}"))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_delete_member(): void
    {
        [$tenant] = $this->setupContext();
        $member = User::factory()->create();
        $tenant->addUser($member, 'member');

        $this->delete($this->tenantUrl("/members/{$member->id}"))->assertRedirect();
    }

    public function test_agent_cannot_manage_users(): void
    {
        $plan = Plan::factory()->create(['slug' => 'biz3', 'features' => PlanFeature::forPlan('business'), 'seats' => 25]);
        $license = License::factory()->active()->forPlan($plan)->create(['seats' => 25]);
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);
        $user = User::factory()->create();
        $tenant->addUser($user, 'member');

        $roleService = app(TenantRoleService::class);
        $roleService->setTenantContext($tenant);
        $roleService->setupDefaultRoles($tenant);
        $roleService->syncRole($user, 'agent', $tenant);

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);

        $this->post($this->tenantUrl('/members'), [
            'name' => 'Test',
            'email' => 'test@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'agent',
        ])->assertForbidden();
    }
}
