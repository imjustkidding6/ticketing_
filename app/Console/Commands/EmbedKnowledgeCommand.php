<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\KbArticle;
use App\Services\EmbeddingService;
use App\Services\OpenAiService;
use Illuminate\Console\Command;

class EmbedKnowledgeCommand extends Command
{
    protected $signature = 'ai:embed:knowledge
                            {--tenant= : Filter by tenant ID}
                            {--force : Re-embed already processed KB articles}
                            {--limit=100 : Maximum number of articles to process}';

    protected $description = 'Generate embeddings for published Knowledge Base articles.';

    public function handle(OpenAiService $openAi, EmbeddingService $embeddingService): int
    {
        if (! $openAi->isConfigured()) {
            $this->warn('OpenAI API key is not configured. Skipping KB embeddings.');

            return self::FAILURE;
        }

        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $force = (bool) $this->option('force');
        $limit = (int) $this->option('limit');

        $query = KbArticle::withoutGlobalScopes()->where('is_published', true);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if (! $force) {
            $query->whereNull('embedded_at');
        }

        $articles = $query->limit($limit)->get();

        if ($articles->isEmpty()) {
            $this->info('No KB articles need embedding.');

            return self::SUCCESS;
        }

        $this->info("Processing embeddings for {$articles->count()} KB article(s)...");
        $bar = $this->output->createProgressBar($articles->count());
        $bar->start();

        $processed = 0;
        foreach ($articles as $article) {
            if ($embeddingService->embedKbArticle($article, $force)) {
                $processed++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Successfully embedded {$processed} KB article(s).");

        return self::SUCCESS;
    }
}
