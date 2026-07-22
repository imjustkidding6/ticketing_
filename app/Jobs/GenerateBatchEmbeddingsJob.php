<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\KbArticle;
use App\Models\LearnedSnippet;
use App\Models\Ticket;
use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateBatchEmbeddingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public readonly ?int $tenantId = null,
        public readonly bool $force = false,
        public readonly int $limit = 50,
    ) {
        $this->onQueue('embeddings');
    }

    public function handle(EmbeddingService $embeddingService): void
    {
        // 1. Process KB Articles
        $articleQuery = KbArticle::withoutGlobalScopes()->where('is_published', true);
        if ($this->tenantId) {
            $articleQuery->where('tenant_id', $this->tenantId);
        }
        if (! $this->force) {
            $articleQuery->whereNull('embedded_at');
        }

        $articles = $articleQuery->limit($this->limit)->get();
        foreach ($articles as $article) {
            $embeddingService->embedKbArticle($article, $this->force);
        }

        // 2. Process Resolved Tickets
        $ticketQuery = Ticket::withoutGlobalScopes()->where('status', 'closed');
        if ($this->tenantId) {
            $ticketQuery->where('tenant_id', $this->tenantId);
        }
        if (! $this->force) {
            $ticketQuery->whereNull('solution_embedded_at');
        }

        $tickets = $ticketQuery->limit($this->limit)->get();
        foreach ($tickets as $ticket) {
            $embeddingService->embedTicket($ticket, $this->force);
        }

        // 3. Process Learned Snippets
        $snippetQuery = LearnedSnippet::withoutGlobalScopes();
        if ($this->tenantId) {
            $snippetQuery->where('tenant_id', $this->tenantId);
        }
        if (! $this->force) {
            $snippetQuery->whereNull('embedding');
        }

        $snippets = $snippetQuery->limit($this->limit)->get();
        foreach ($snippets as $snippet) {
            $embeddingService->embedSnippet($snippet, $this->force);
        }
    }
}
