<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiUsageLog;
use Illuminate\Support\Facades\Cache;

class AiMetricsService
{
    /**
     * Get real-time AI performance metrics summary.
     *
     * @return array<string, mixed>
     */
    public function getMetricsSummary(): array
    {
        $avgLatency = (float) (AiUsageLog::avg('latency_ms') ?? 0);
        $totalLogs = AiUsageLog::count();
        $successfulLogs = AiUsageLog::where('response_status', 'success')->count();
        $failedLogs = AiUsageLog::where('response_status', '!=', 'success')->count();

        // Calculate P95 Latency
        $p95Latency = 0;
        if ($totalLogs > 0) {
            $p95Latency = (int) (AiUsageLog::orderBy('latency_ms', 'desc')
                ->skip((int) floor($totalLogs * 0.05))
                ->value('latency_ms') ?? $avgLatency);
        }

        // Calculate P99 Latency
        $p99Latency = 0;
        if ($totalLogs > 0) {
            $p99Latency = (int) (AiUsageLog::orderBy('latency_ms', 'desc')
                ->skip((int) floor($totalLogs * 0.01))
                ->value('latency_ms') ?? $p95Latency);
        }

        $cacheHits = (int) Cache::get('ai_metric_cache_hits', 0);
        $cacheMisses = (int) Cache::get('ai_metric_cache_miss', 0);
        $totalCache = $cacheHits + $cacheMisses;
        $cacheHitRatio = $totalCache > 0 ? round(($cacheHits / $totalCache) * 100, 1) : 100.0;

        return [
            'avg_latency_ms' => round($avgLatency, 2),
            'p95_latency_ms' => $p95Latency,
            'p99_latency_ms' => $p99Latency,
            'total_requests' => $totalLogs,
            'success_requests' => $successfulLogs,
            'failed_requests' => $failedLogs,
            'error_rate_percent' => $totalLogs > 0 ? round(($failedLogs / $totalLogs) * 100, 2) : 0.0,
            'cache_hit_ratio' => $cacheHitRatio,
        ];
    }
}
