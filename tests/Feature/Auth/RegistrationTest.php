<?php

namespace Tests\Feature\Auth;

use App\Models\License;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register_with_valid_license(): void
    {
        $license = License::factory()->pending()->create();

        $response = $this->post('/register', [
            'license_key' => $license->license_key,
            'company_name' => 'Test Company',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('tenants', ['name' => 'Test Company']);
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);

        $license->refresh();
        $this->assertEquals(License::STATUS_ACTIVE, $license->status);
    }

    public function test_registration_fails_with_invalid_license_key(): void
    {
        $response = $this->post('/register', [
            'license_key' => 'INVALID-KEY-1234-5678',
            'company_name' => 'Test Company',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('license_key');
        $this->assertGuest();
    }

    public function test_registration_fails_with_already_activated_license(): void
    {
        $license = License::factory()->active()->create();

        $response = $this->post('/register', [
            'license_key' => $license->license_key,
            'company_name' => 'Test Company',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('license_key');
        $this->assertGuest();
    }

    public function test_user_is_added_as_tenant_owner_after_registration(): void
    {
        $license = License::factory()->pending()->create();

        $this->post('/register', [
            'license_key' => $license->license_key,
            'company_name' => 'Test Company',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'test@example.com')->first();
        $tenant = Tenant::where('name', 'Test Company')->first();

        $this->assertTrue($tenant->isOwner($user));
    }
}
