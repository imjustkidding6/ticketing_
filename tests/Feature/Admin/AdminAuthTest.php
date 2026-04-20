<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_page_loads(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_admin_can_login(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect('/admin');
    }

    public function test_non_admin_cannot_login(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');
    }

    public function test_invalid_credentials_rejected(): void
    {
        $this->post('/admin/login', [
            'email' => 'nobody@test.com',
            'password' => 'wrong',
        ])->assertSessionHasErrors('email');
    }

    public function test_admin_dashboard_requires_auth(): void
    {
        $this->get('/admin')->assertRedirect();
    }

    public function test_admin_can_logout(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/logout')->assertRedirect();
    }
}
