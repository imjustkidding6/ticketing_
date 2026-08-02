<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiUsageLog;
use App\Services\AiProfileService;
use App\Services\EmbeddingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class AiMaintenanceCommand extends Command
{
    protected $signature = 'ai:maintenance
                            {--mode=daily : Maintenance mode: daily | hourly | weekly}';

    protected $description = 'Perform background maintenance, cache warming, log cleanup, and metric recalculations for AI Platform.';

    public function handle(AiProfileService $profileService, EmbeddingService $embeddingService): int
    {
        $mode = (string) $this->option('mode');

        $this->info("Starting AI Platform maintenance mode: [{$mode}]");

        if ($mode === 'daily') {
            $deletedLogs = AiUsageLog::where('created_at', '<', now()->subDays(30))->delete();
            $this->info("Cleaned up {$deletedLogs} expired AI usage log entries.");

            Cache::forget('ai_metric_*');
            $this->info('Flushed old AI metrics cache counters.');
        }

        if ($mode === 'hourly' || $mode === 'daily') {
            // Warm AI profile cache
            $profileService->getActiveProfile();
            $this->info('Warmed active AI configuration profile cache.');

            // Refresh system metrics
            $embeddingService->getMetrics();
            $this->info('Refreshed vector embedding metrics.');
        }

        if ($mode === 'weekly') {
            $this->info('Running weekly embedding optimization pass...');
            $this->call('ai:embed:all', ['--limit' => 200]);
        }

        $this->info('AI Platform maintenance completed successfully.');

        return self::SUCCESS;
    }
}
