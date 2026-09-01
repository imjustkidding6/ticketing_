<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * A small keyed cache for expensive AI lookups.
 *
 * Note the name overstates it: keys are an md5 of the exact query string, so two
 * differently-worded questions with the same meaning are separate entries. It is
 * a lexical cache, not a semantic one.
 */
class SemanticCacheService
{
    private int $defaultTtlMinutes = 60;

    /**
     * Cache keys have to be tracked explicitly: Laravel's cache API has no
     * wildcard delete, and the database and array stores do not support tags.
     * Without this index the clear methods below silently did nothing.
     */
    private const INDEX_PREFIX = 'semantic_cache_index_';

    private const INDEX_TTL_MINUTES = 1440;

    /**
     * Remember or fetch a cached result for a query.
     */
    public function remember(string $type, string $query, Closure $callback, ?int $ttlMinutes = null): mixed
    {
        $key = $this->key($type, $query);
        $ttl = $ttlMinutes ?? $this->defaultTtlMinutes;

        $this->registerKey($type, $key);

        return Cache::remember($key, now()->addMinutes($ttl), $callback);
    }

    /**
     * Drop every cached entry of a given type.
     */
    public function clear(string $type): int
    {
        $indexKey = self::INDEX_PREFIX.$type;
        $keys = Cache::get($indexKey, []);

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        Cache::forget($indexKey);

        return count($keys);
    }

    public function clearKbCache(): int
    {
        return $this->clear('kb');
    }

    public function clearEmbeddingCache(): int
    {
        return $this->clear('embed');
    }

    public function clearPromptCache(): int
    {
        return $this->clear('prompt');
    }

    private function key(string $type, string $query): string
    {
        return "semantic_cache_{$type}_".md5($query);
    }

    /**
     * Add a key to its type's index so it can be found again at clear time.
     */
    private function registerKey(string $type, string $key): void
    {
        $indexKey = self::INDEX_PREFIX.$type;
        $keys = Cache::get($indexKey, []);

        if (in_array($key, $keys, true)) {
            return;
        }

        $keys[] = $key;
        Cache::put($indexKey, $keys, now()->addMinutes(self::INDEX_TTL_MINUTES));
    }
}
