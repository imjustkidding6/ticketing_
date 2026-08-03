<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\KbArticle;
use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateKnowledgeBaseEmbeddingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly KbArticle $article,
        public readonly bool $force = false,
    ) {
        $this->onQueue('embeddings');
    }

    public function handle(EmbeddingService $embeddingService): void
    {
        $embeddingService->embedKbArticle($this->article, $this->force);
    }
}
