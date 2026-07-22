<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\OpenAiException;
use App\Services\CircuitBreakerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    use RefreshDatabase;

    public function test_circuit_breaker_trips_after_consecutive_failures(): void
    {
        $circuit = app(CircuitBreakerService::class);

        $circuit->recordSuccess();
        $this->assertEquals('CLOSED', $circuit->getStatus()['state']);

        for ($i = 0; $i < 5; $i++) {
            $circuit->recordFailure();
        }

        $this->assertEquals('OPEN', $circuit->getStatus()['state']);

        $this->expectException(OpenAiException::class);
        $circuit->ensureAvailable();
    }
}
