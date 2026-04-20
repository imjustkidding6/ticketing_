<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk();
        $response->assertJson([
            'status' => 'ok',
            'database' => true,
            'cache' => true,
        ]);
    }

    public function test_up_endpoint_returns_ok(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
    }
}
