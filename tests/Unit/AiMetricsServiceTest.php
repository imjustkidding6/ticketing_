<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\AiUsageLog;
use App\Services\AiMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_service_returns_summary(): void
    {
        AiUsageLog::factory()->create([
            'latency_ms' => 200,
            'response_status' => 'success',
        ]);

        $service = app(AiMetricsService::class);
        $summary = $service->getMetricsSummary();

        $this->assertArrayHasKey('avg_latency_ms', $summary);
        $this->assertArrayHasKey('p95_latency_ms', $summary);
        $this->assertArrayHasKey('cache_hit_ratio', $summary);
    }
}
