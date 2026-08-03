<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\AiUsageLog;
use App\Services\AiUsageTrackerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageTrackerTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_tracker_saves_log(): void
    {
        $tracker = app(AiUsageTrackerService::class);

        $log = $tracker->log([
            'model' => 'gpt-4o',
            'prompt_tokens' => 200,
            'completion_tokens' => 100,
            'latency_ms' => 120,
        ]);

        $this->assertInstanceOf(AiUsageLog::class, $log);
        $this->assertEquals(300, $log->total_tokens);
    }
}
