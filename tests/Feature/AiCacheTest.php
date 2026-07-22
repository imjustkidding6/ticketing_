<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\SemanticCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_semantic_cache_remembers_search_results(): void
    {
        $cache = app(SemanticCacheService::class);

        $result1 = $cache->remember('kb', 'password reset', fn () => ['article_id' => 10]);
        $result2 = $cache->remember('kb', 'password reset', fn () => ['article_id' => 999]);

        $this->assertEquals(['article_id' => 10], $result1);
        $this->assertEquals(['article_id' => 10], $result2);
    }
}
