<?php

namespace Tests\Feature\Admin;

use App\Models\Distributor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDistributorTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_non_admin_cannot_access(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/distributors')->assertForbidden();
    }

    public function test_list_distributors(): void
    {
        $admin = $this->adminUser();
        Distributor::factory()->count(3)->create();

        $this->actingAs($admin)->get('/admin/distributors')->assertOk();
    }

    public function test_create_distributor(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post('/admin/distributors', [
            'name' => 'Test Distributor',
            'email' => 'dist@example.com',
            'contact_person' => 'John Doe',
        ])->assertRedirect();

        $this->assertDatabaseHas('distributors', ['name' => 'Test Distributor']);
    }

    public function test_update_distributor(): void
    {
        $admin = $this->adminUser();
        $distributor = Distributor::factory()->create();

        $this->actingAs($admin)->put("/admin/distributors/{$distributor->id}", [
            'name' => 'Updated Name',
            'email' => $distributor->email,
            'contact_person' => $distributor->contact_person,
        ])->assertRedirect();

        $distributor->refresh();
        $this->assertEquals('Updated Name', $distributor->name);
    }

    public function test_delete_distributor(): void
    {
        $admin = $this->adminUser();
        $distributor = Distributor::factory()->create();

        $this->actingAs($admin)->delete("/admin/distributors/{$distributor->id}")->assertRedirect();

        $this->assertDatabaseMissing('distributors', ['id' => $distributor->id]);
    }
}
