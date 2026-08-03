<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\GenerateKnowledgeBaseEmbeddingJob;
use App\Models\KbArticle;
use App\Services\EmbeddingService;

class KbArticleObserver
{
    public function saved(KbArticle $article): void
    {
        if ($article->wasChanged(['embedding', 'embedded_at'])) {
            return;
        }

        if ($article->is_published) {
            GenerateKnowledgeBaseEmbeddingJob::dispatch($article, force: true);
        } else {
            app(EmbeddingService::class)->clearKbArticleEmbedding($article);
        }
    }

    public function deleted(KbArticle $article): void
    {
        app(EmbeddingService::class)->clearKbArticleEmbedding($article);
    }
}
