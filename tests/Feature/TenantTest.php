<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_can_be_created(): void
    {
        $tenant = Tenant::create([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'description' => 'A test company',
        ]);

        $this->assertDatabaseHas('tenants', [
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
        ]);
    }

    public function test_tenant_slug_is_auto_generated(): void
    {
        $tenant = Tenant::create([
            'name' => 'My Test Company',
        ]);

        $this->assertEquals('my-test-company', $tenant->slug);
    }

    public function test_tenant_is_active_by_default(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertTrue($tenant->is_active);
    }

    public function test_tenant_can_be_inactive(): void
    {
        $tenant = Tenant::factory()->inactive()->create();

        $this->assertFalse($tenant->is_active);
    }

    public function test_tenant_can_add_user(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        $tenant->addUser($user, 'member');

        $this->assertTrue($tenant->hasUser($user));
        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);
    }

    public function test_tenant_can_add_user_as_owner(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        $tenant->addUser($user, 'owner');

        $this->assertTrue($tenant->isOwner($user));
    }

    public function test_tenant_can_remove_user(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        $tenant->addUser($user);
        $this->assertTrue($tenant->hasUser($user));

        $tenant->removeUser($user);
        $this->assertFalse($tenant->hasUser($user));
    }

    public function test_tenant_can_get_owners(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $tenant->addUser($owner, 'owner');
        $tenant->addUser($member, 'member');

        $owners = $tenant->owners;

        $this->assertCount(1, $owners);
        $this->assertTrue($owners->contains($owner));
        $this->assertFalse($owners->contains($member));
    }

    public function test_tenant_can_get_admins(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();

        $tenant->addUser($admin, 'admin');
        $tenant->addUser($member, 'member');

        $admins = $tenant->admins;

        $this->assertCount(1, $admins);
        $this->assertTrue($admins->contains($admin));
    }

    public function test_tenant_can_get_members(): void
    {
        $tenant = Tenant::factory()->create();
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();
        $admin = User::factory()->create();

        $tenant->addUser($member1, 'member');
        $tenant->addUser($member2, 'member');
        $tenant->addUser($admin, 'admin');

        $members = $tenant->members;

        $this->assertCount(2, $members);
        $this->assertTrue($members->contains($member1));
        $this->assertTrue($members->contains($member2));
        $this->assertFalse($members->contains($admin));
    }

    public function test_tenant_users_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            $tenant->addUser($user);
        }

        $this->assertCount(3, $tenant->users);
    }
}
