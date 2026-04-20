<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithTenant(): User
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $tenant->addUser($user, 'member');
        $user->setCurrentTenant($tenant);

        return $user;
    }

    public function test_confirm_password_screen_can_be_rendered(): void
    {
        $user = $this->createUserWithTenant();

        $response = $this->actingAs($user)
            ->withSession(['current_tenant_id' => $user->tenants()->first()->id])
            ->get('/confirm-password');

        $response->assertStatus(200);
    }

    public function test_password_can_be_confirmed(): void
    {
        $user = $this->createUserWithTenant();

        $response = $this->actingAs($user)
            ->withSession(['current_tenant_id' => $user->tenants()->first()->id])
            ->post('/confirm-password', [
                'password' => 'password',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    public function test_password_is_not_confirmed_with_invalid_password(): void
    {
        $user = $this->createUserWithTenant();

        $response = $this->actingAs($user)
            ->withSession(['current_tenant_id' => $user->tenants()->first()->id])
            ->post('/confirm-password', [
                'password' => 'wrong-password',
            ]);

        $response->assertSessionHasErrors();
    }
}
