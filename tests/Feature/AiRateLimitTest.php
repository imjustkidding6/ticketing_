<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_ai_rate_limiter_allows_under_limit(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->postJson('/portal/ai/message', [
            'tenant_id' => $tenant->id,
            'message' => 'Hello',
        ]);

        $this->assertNotEquals(429, $response->status());
    }
}
