<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\LearnedSnippet;
use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateSnippetEmbeddingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly LearnedSnippet $snippet,
        public readonly bool $force = false,
    ) {
        $this->onQueue('embeddings');
    }

    public function handle(EmbeddingService $embeddingService): void
    {
        $embeddingService->embedSnippet($this->snippet, $this->force);
    }
}
