<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

class SemanticCacheService
{
    private int $defaultTtlMinutes = 60;

    /**
     * Remember or fetch cached result for search query.
     */
    public function remember(string $type, string $query, Closure $callback, ?int $ttlMinutes = null): mixed
    {
        $key = "semantic_cache_{$type}_".md5($query);
        $ttl = $ttlMinutes ?? $this->defaultTtlMinutes;

        return Cache::remember($key, now()->addMinutes($ttl), $callback);
    }

    /**
     * Clear cached results for Knowledge Base searches.
     */
    public function clearKbCache(): void
    {
        Cache::forget('semantic_cache_kb_*');
    }

    /**
     * Clear cached results for embeddings.
     */
    public function clearEmbeddingCache(): void
    {
        Cache::forget('semantic_cache_embed_*');
    }

    /**
     * Clear cached results for prompts.
     */
    public function clearPromptCache(): void
    {
        Cache::forget('semantic_cache_prompt_*');
    }
}
