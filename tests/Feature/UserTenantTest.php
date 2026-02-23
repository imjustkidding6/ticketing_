<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_belong_to_multiple_tenants(): void
    {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        $tenant1->addUser($user);
        $tenant2->addUser($user);

        $this->assertCount(2, $user->tenants);
        $this->assertTrue($user->belongsToTenant($tenant1));
        $this->assertTrue($user->belongsToTenant($tenant2));
    }

    public function test_user_can_set_current_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenant->addUser($user);

        $this->actingAs($user);
        $user->setCurrentTenant($tenant);

        $this->assertEquals($tenant->id, session('current_tenant_id'));
    }

    public function test_user_can_get_current_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenant->addUser($user);

        $this->actingAs($user);
        $user->setCurrentTenant($tenant);

        $currentTenant = $user->currentTenant();

        $this->assertNotNull($currentTenant);
        $this->assertEquals($tenant->id, $currentTenant->id);
    }

    public function test_user_cannot_set_tenant_they_dont_belong_to(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($user);

        $this->expectException(\InvalidArgumentException::class);
        $user->setCurrentTenant($tenant);
    }

    public function test_user_can_clear_current_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenant->addUser($user);

        $this->actingAs($user);
        $user->setCurrentTenant($tenant);
        $user->clearCurrentTenant();

        $this->assertNull(session('current_tenant_id'));
    }

    public function test_user_can_get_role_in_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenant->addUser($user, 'admin');

        $this->assertEquals('admin', $user->roleInTenant($tenant));
    }

    public function test_user_role_in_tenant_returns_null_if_not_member(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $this->assertNull($user->roleInTenant($tenant));
    }

    public function test_user_is_owner_of_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenant->addUser($user, 'owner');

        $this->assertTrue($user->isOwnerOf($tenant));
    }

    public function test_user_is_not_owner_of_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenant->addUser($user, 'member');

        $this->assertFalse($user->isOwnerOf($tenant));
    }

    public function test_user_is_admin_of_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenant->addUser($user, 'admin');

        $this->assertTrue($user->isAdminOf($tenant));
    }

    public function test_owner_is_also_admin(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenant->addUser($user, 'owner');

        $this->assertTrue($user->isAdminOf($tenant));
    }

    public function test_member_is_not_admin(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenant->addUser($user, 'member');

        $this->assertFalse($user->isAdminOf($tenant));
    }

    public function test_user_can_get_owned_tenants(): void
    {
        $user = User::factory()->create();
        $ownedTenant = Tenant::factory()->create();
        $memberTenant = Tenant::factory()->create();

        $ownedTenant->addUser($user, 'owner');
        $memberTenant->addUser($user, 'member');

        $ownedTenants = $user->ownedTenants;

        $this->assertCount(1, $ownedTenants);
        $this->assertTrue($ownedTenants->contains($ownedTenant));
    }

    public function test_user_can_get_admin_tenants(): void
    {
        $user = User::factory()->create();
        $ownedTenant = Tenant::factory()->create();
        $adminTenant = Tenant::factory()->create();
        $memberTenant = Tenant::factory()->create();

        $ownedTenant->addUser($user, 'owner');
        $adminTenant->addUser($user, 'admin');
        $memberTenant->addUser($user, 'member');

        $adminTenants = $user->adminTenants;

        $this->assertCount(2, $adminTenants);
        $this->assertTrue($adminTenants->contains($ownedTenant));
        $this->assertTrue($adminTenants->contains($adminTenant));
        $this->assertFalse($adminTenants->contains($memberTenant));
    }

    public function test_ensure_current_tenant_sets_first_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenant->addUser($user);

        $this->actingAs($user);
        $result = $user->ensureCurrentTenant();

        $this->assertNotNull($result);
        $this->assertEquals($tenant->id, session('current_tenant_id'));
    }

    public function test_ensure_current_tenant_returns_existing_tenant(): void
    {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        $tenant1->addUser($user);
        $tenant2->addUser($user);

        $this->actingAs($user);
        $user->setCurrentTenant($tenant1);

        $result = $user->ensureCurrentTenant();

        $this->assertEquals($tenant1->id, $result->id);
    }

    public function test_ensure_current_tenant_returns_null_if_no_tenants(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $result = $user->ensureCurrentTenant();

        $this->assertNull($result);
    }
}
