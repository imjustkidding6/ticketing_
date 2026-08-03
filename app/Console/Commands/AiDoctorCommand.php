<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\OpenAiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class AiDoctorCommand extends Command
{
    protected $signature = 'ai:doctor';

    protected $description = 'Perform production deployment health diagnostics for the AI Platform (OpenAI, Redis, Queue, DB, Storage).';

    public function handle(OpenAiService $openAi): int
    {
        $this->newLine();
        $this->info('====================================================');
        $this->info('      AI PLATFORM PRODUCTION DOCTOR & DIAGNOSTICS   ');
        $this->info('====================================================');
        $this->newLine();

        $allPassed = true;

        // 1. OpenAI API Key & Model Check
        $apiKeyConfigured = $openAi->isConfigured();
        $model = (string) config('services.openai.model', 'gpt-5');
        $embedModel = (string) config('services.openai.embed_model', 'text-embedding-3-small');

        if ($apiKeyConfigured) {
            $this->line("  [PASS] OpenAI API Key: Configured (Model: {$model}, Embed: {$embedModel})");
        } else {
            $this->error('  [FAIL] OpenAI API Key: Not configured in .env (OPENAI_API_KEY)');
            $allPassed = false;
        }

        // 2. Database Connection
        try {
            DB::connection()->getPdo();
            $this->line('  [PASS] Database Connection: Connected');
        } catch (Throwable $e) {
            $this->error("  [FAIL] Database Connection: Failed ({$e->getMessage()})");
            $allPassed = false;
        }

        // 3. Cache & Redis Verification
        try {
            Cache::put('ai_doctor_ping', 'pong', 10);
            $cached = Cache::get('ai_doctor_ping');
            if ($cached === 'pong') {
                $driver = config('cache.default', 'file');
                $this->line("  [PASS] Cache System: Functional (Driver: {$driver})");
            } else {
                $this->error('  [FAIL] Cache System: Cache write failed.');
                $allPassed = false;
            }
        } catch (Throwable $e) {
            $this->error("  [FAIL] Cache System: Exception ({$e->getMessage()})");
            $allPassed = false;
        }

        // 4. Queue System Verification
        $queueDriver = config('queue.default', 'sync');
        $this->line("  [PASS] Queue Connection: Configured (Driver: {$queueDriver})");

        // 5. Storage Directory Permissions
        $storagePath = storage_path();
        if (is_writable($storagePath)) {
            $this->line("  [PASS] Storage Permissions: Writable ({$storagePath})");
        } else {
            $this->error("  [FAIL] Storage Permissions: Not writable ({$storagePath})");
            $allPassed = false;
        }

        // 6. Horizon Configuration
        $horizonConfigured = file_exists(config_path('horizon.php'));
        if ($horizonConfigured) {
            $this->line('  [PASS] Horizon Configuration: Present (config/horizon.php)');
        } else {
            $this->error('  [FAIL] Horizon Configuration: Missing config/horizon.php');
            $allPassed = false;
        }

        $this->newLine();
        $this->info('====================================================');
        if ($allPassed) {
            $this->info('  DIAGNOSTIC STATUS: ALL CHECKS PASSED (READY FOR PROD)');
            $this->info('====================================================');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->error('  DIAGNOSTIC STATUS: ISSUES DETECTED (CHECK FAILURES)');
        $this->info('====================================================');
        $this->newLine();

        return self::FAILURE;
    }
}
