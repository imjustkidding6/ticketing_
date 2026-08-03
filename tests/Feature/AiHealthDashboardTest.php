<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiHealthDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_ai_health_dashboard(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get('/admin/ai/health');

        $response->assertStatus(200)
            ->assertSee('AI Infrastructure Health Monitor');
    }
}
