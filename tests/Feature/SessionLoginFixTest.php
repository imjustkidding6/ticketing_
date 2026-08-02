<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionLoginFixTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_domain_config_evaluates_to_null_instead_of_string_null(): void
    {
        $this->assertNull(config('session.domain'));
    }

    public function test_user_can_login_and_redirect_without_419_session_expired(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $tenant = Tenant::factory()->create(['is_active' => true]);
        $user->tenants()->attach($tenant->id, ['role' => 'owner']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(302);
        $this->assertAuthenticatedAs($user);
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertStatus(302);
        $this->assertGuest();
    }
}
