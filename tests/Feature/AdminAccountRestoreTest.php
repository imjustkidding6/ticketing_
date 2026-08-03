<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAccountRestoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_restore_admin_command_creates_admin_account(): void
    {
        $this->artisan('ai:restore-admin')
            ->assertExitCode(0);

        $admin = User::where('email', 'admin@example.com')->first();

        $this->assertNotNull($admin);
        $this->assertTrue($admin->is_admin);
        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertNotNull($admin->email_verified_at);
        $this->assertGreaterThan(0, $admin->tenants()->count());
    }

    public function test_admin_can_login_with_default_credentials(): void
    {
        $this->artisan('ai:restore-admin');

        $response = $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(302);
        $this->assertAuthenticated();

        $admin = User::where('email', 'admin@example.com')->first();
        $this->assertAuthenticatedAs($admin);
    }
}
