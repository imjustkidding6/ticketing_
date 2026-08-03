<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AiUsageLog;
use App\Models\Tenant;
use App\Services\AiUsageTrackerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_usage_tracker_logs_requests_and_calculates_estimated_cost(): void
    {
        $tenant = Tenant::factory()->create();
        $tracker = app(AiUsageTrackerService::class);

        $log = $tracker->log([
            'tenant_id' => $tenant->id,
            'model' => 'gpt-5',
            'prompt_tokens' => 1000,
            'completion_tokens' => 500,
            'latency_ms' => 350,
            'response_status' => 'success',
            'feature' => 'chat',
        ]);

        $this->assertInstanceOf(AiUsageLog::class, $log);
        $this->assertEquals(1500, $log->total_tokens);
        $this->assertGreaterThan(0, $log->estimated_cost);
        $this->assertDatabaseHas('ai_usage_logs', [
            'tenant_id' => $tenant->id,
            'model' => 'gpt-5',
            'total_tokens' => 1500,
        ]);
    }
}
