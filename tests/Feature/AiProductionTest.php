<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AiProductionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.openai.api_key', 'sk-test-key-12345');
        Config::set('services.openai.model', 'gpt-5');
    }

    public function test_ai_doctor_command_runs_diagnostics_successfully(): void
    {
        $this->artisan('ai:doctor')
            ->assertExitCode(0);
    }

    public function test_ai_maintenance_command_executes_daily_mode(): void
    {
        $this->artisan('ai:maintenance --mode=daily')
            ->assertExitCode(0);
    }

    public function test_horizon_config_is_loaded_correctly(): void
    {
        $config = config('horizon');
        $this->assertIsArray($config);
        $this->assertArrayHasKey('waits', $config);
        $this->assertArrayHasKey('defaults', $config);
    }

    public function test_health_dashboard_displays_all_infrastructure_checks(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get('/admin/ai/health');

        $response->assertStatus(200)
            ->assertSee('OpenAI API Service')
            ->assertSee('Circuit Breaker State')
            ->assertSee('Cache Hit Ratio');
    }
}
