<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\KbArticle;
use App\Models\LearnedSnippet;
use App\Models\Ticket;
use Illuminate\Support\Facades\Cache;

/**
 * Service for managing vector embeddings, batch generation, and semantic vector search.
 */
class EmbeddingService
{
    public function __construct(
        private readonly OpenAiService $openAi,
    ) {}

    /**
     * Generate and store embedding for a single Knowledge Base article.
     */
    public function embedKbArticle(KbArticle $article, bool $force = false): bool
    {
        if (! $force && $article->embedded_at !== null && $article->embedding !== null) {
            return false;
        }

        if (! $article->is_published) {
            $this->clearKbArticleEmbedding($article);

            return false;
        }

        $textToEmbed = trim("{$article->title}\n{$article->excerpt}\n".strip_tags((string) $article->content));
        if (mb_strlen($textToEmbed) < 5) {
            return false;
        }

        $startTime = microtime(true);

        try {
            $vector = $this->openAi->embed(mb_substr($textToEmbed, 0, 8000));
            if (empty($vector)) {
                return false;
            }

            $article->forceFill([
                'embedding' => $vector,
                'embedded_at' => now(),
            ])->save();

            $duration = microtime(true) - $startTime;
            $this->recordMetric('generated', 1);
            $this->recordMetric('time', $duration);

            return true;
        } catch (\Throwable $e) {
            $this->recordMetric('failures', 1);
            report($e);

            return false;
        }
    }

    /**
     * Generate and store embedding for a resolved closed ticket.
     */
    public function embedTicket(Ticket $ticket, bool $force = false): bool
    {
        if (! $force && $ticket->solution_embedded_at !== null && $ticket->solution_embedding !== null) {
            return false;
        }

        if ($ticket->status !== 'closed') {
            $this->clearTicketEmbedding($ticket);

            return false;
        }

        $resolution = trim((string) $ticket->closing_remarks);
        if ($resolution === '') {
            $agentReply = $ticket->comments->first(fn ($c) => $c->user_id !== null);
            $resolution = (string) ($agentReply?->content ?? '');
        }

        $textToEmbed = trim("Subject: {$ticket->subject}\nDescription: {$ticket->description}\nResolution: {$resolution}");
        if (mb_strlen($textToEmbed) < 10) {
            return false;
        }

        $startTime = microtime(true);

        try {
            $vector = $this->openAi->embed(mb_substr($textToEmbed, 0, 8000));
            if (empty($vector)) {
                return false;
            }

            $ticket->forceFill([
                'solution_embedding' => $vector,
                'solution_embedded_at' => now(),
            ])->save();

            $duration = microtime(true) - $startTime;
            $this->recordMetric('generated', 1);
            $this->recordMetric('time', $duration);

            return true;
        } catch (\Throwable $e) {
            $this->recordMetric('failures', 1);
            report($e);

            return false;
        }
    }

    /**
     * Generate and store embedding for a learned snippet.
     */
    public function embedSnippet(LearnedSnippet $snippet, bool $force = false): bool
    {
        if (! $force && $snippet->embedding !== null) {
            return false;
        }

        $textToEmbed = trim("Question: {$snippet->question}\nAnswer: {$snippet->answer}");
        if (mb_strlen($textToEmbed) < 5) {
            return false;
        }

        $startTime = microtime(true);

        try {
            $vector = $this->openAi->embed(mb_substr($textToEmbed, 0, 8000));
            if (empty($vector)) {
                return false;
            }

            $snippet->forceFill([
                'embedding' => $vector,
            ])->save();

            $duration = microtime(true) - $startTime;
            $this->recordMetric('generated', 1);
            $this->recordMetric('time', $duration);

            return true;
        } catch (\Throwable $e) {
            $this->recordMetric('failures', 1);
            report($e);

            return false;
        }
    }

    /**
     * Clear embedding data for a Knowledge Base article.
     */
    public function clearKbArticleEmbedding(KbArticle $article): void
    {
        $article->forceFill([
            'embedding' => null,
            'embedded_at' => null,
        ])->save();
    }

    /**
     * Clear solution embedding data for a ticket.
     */
    public function clearTicketEmbedding(Ticket $ticket): void
    {
        $ticket->forceFill([
            'solution_embedding' => null,
            'solution_embedded_at' => null,
        ])->save();
    }

    /**
     * Search for similar resolved tickets using vector similarity with caching.
     *
     * @return array<int, array{ticket: Ticket, score: float}>
     */
    public function searchSimilarResolvedTickets(int $tenantId, string $query, int $limit = 5, float $threshold = 0.3): array
    {
        $cacheKey = "vector_tickets_{$tenantId}_".md5($query)."_{$limit}";

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($tenantId, $query, $limit, $threshold) {
            $this->recordMetric('cache_miss', 1);

            try {
                $queryVector = $this->openAi->embed($query);
            } catch (\Throwable $e) {
                return [];
            }

            if (empty($queryVector)) {
                return [];
            }

            $candidates = Ticket::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('status', 'closed')
                ->whereNotNull('solution_embedded_at')
                ->whereNotNull('solution_embedding')
                ->latest('closed_at')
                ->limit(300)
                ->get();

            $results = [];
            foreach ($candidates as $ticket) {
                $vector = $ticket->solution_embedding;
                if (! is_array($vector) || empty($vector)) {
                    continue;
                }

                $score = $this->cosineSimilarity($queryVector, $vector);
                if ($score >= $threshold) {
                    $results[] = [
                        'ticket' => $ticket,
                        'score' => round($score, 4),
                    ];
                }
            }

            usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);

            return array_slice($results, 0, $limit);
        });
    }

    /**
     * Calculate Cosine Similarity between two vector arrays.
     *
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $n = min(count($a), count($b));

        for ($i = 0; $i < $n; $i++) {
            $x = (float) $a[$i];
            $y = (float) $b[$i];
            $dot += $x * $y;
            $normA += $x * $x;
            $normB += $y * $y;
        }

        return ($normA > 0 && $normB > 0) ? $dot / (sqrt($normA) * sqrt($normB)) : 0.0;
    }

    /**
     * Record an AI metrics event.
     */
    private function recordMetric(string $key, float|int $value): void
    {
        $cacheKey = "ai_metric_{$key}";
        Cache::increment($cacheKey, (int) round($value));
    }

    /**
     * Get system-wide AI metrics summary.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        return [
            'generated_count' => (int) Cache::get('ai_metric_generated', 0),
            'failure_count' => (int) Cache::get('ai_metric_failures', 0),
            'total_duration_seconds' => (int) Cache::get('ai_metric_time', 0),
            'cache_misses' => (int) Cache::get('ai_metric_cache_miss', 0),
        ];
    }
}
