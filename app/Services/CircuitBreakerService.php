<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\OpenAiException;
use Illuminate\Support\Facades\Cache;

class CircuitBreakerService
{
    private const CACHE_KEY_FAILURES = 'circuit_breaker_openai_failures';

    private const CACHE_KEY_STATE = 'circuit_breaker_openai_state';

    private int $failureThreshold = 5;

    private int $cooldownSeconds = 60;

    /**
     * Check if the circuit breaker allows request execution.
     *
     * @throws OpenAiException
     */
    public function ensureAvailable(): void
    {
        $state = Cache::get(self::CACHE_KEY_STATE, 'CLOSED');
        if ($state === 'OPEN') {
            throw new OpenAiException('The AI service is experiencing a temporary outage. Please try again shortly.');
        }
    }

    /**
     * Record a successful request.
     */
    public function recordSuccess(): void
    {
        Cache::forget(self::CACHE_KEY_FAILURES);
        Cache::put(self::CACHE_KEY_STATE, 'CLOSED');
    }

    /**
     * Record a failed request.
     */
    public function recordFailure(): void
    {
        $failures = (int) Cache::increment(self::CACHE_KEY_FAILURES);
        if ($failures >= $this->failureThreshold) {
            Cache::put(self::CACHE_KEY_STATE, 'OPEN', now()->addSeconds($this->cooldownSeconds));
        }
    }

    /**
     * Get current status of the circuit breaker.
     */
    public function getStatus(): array
    {
        return [
            'state' => Cache::get(self::CACHE_KEY_STATE, 'CLOSED'),
            'failures' => (int) Cache::get(self::CACHE_KEY_FAILURES, 0),
            'threshold' => $this->failureThreshold,
        ];
    }
}
