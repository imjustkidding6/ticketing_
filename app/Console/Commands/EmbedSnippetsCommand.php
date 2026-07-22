<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\LearnedSnippet;
use App\Services\EmbeddingService;
use App\Services\OpenAiService;
use Illuminate\Console\Command;

class EmbedSnippetsCommand extends Command
{
    protected $signature = 'ai:embed:snippets
                            {--tenant= : Filter by tenant ID}
                            {--force : Re-embed already processed snippets}
                            {--limit=100 : Maximum number of snippets to process}';

    protected $description = 'Generate embeddings for learned Q&A snippets.';

    public function handle(OpenAiService $openAi, EmbeddingService $embeddingService): int
    {
        if (! $openAi->isConfigured()) {
            $this->warn('OpenAI API key is not configured. Skipping snippet embeddings.');

            return self::FAILURE;
        }

        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $force = (bool) $this->option('force');
        $limit = (int) $this->option('limit');

        $query = LearnedSnippet::withoutGlobalScopes();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if (! $force) {
            $query->whereNull('embedding');
        }

        $snippets = $query->limit($limit)->get();

        if ($snippets->isEmpty()) {
            $this->info('No snippets need embedding.');

            return self::SUCCESS;
        }

        $this->info("Processing embeddings for {$snippets->count()} learned snippet(s)...");
        $bar = $this->output->createProgressBar($snippets->count());
        $bar->start();

        $processed = 0;
        foreach ($snippets as $snippet) {
            if ($embeddingService->embedSnippet($snippet, $force)) {
                $processed++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Successfully embedded {$processed} snippet(s).");

        return self::SUCCESS;
    }
}
