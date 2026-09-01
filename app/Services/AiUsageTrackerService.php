<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiUsageLog;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiUsageTrackerService
{
    /**
     * Record an AI request execution usage log.
     *
     * @param  array<string, mixed>  $data
     */
    public function log(array $data): AiUsageLog
    {
        $promptTokens = (int) ($data['prompt_tokens'] ?? 0);
        $completionTokens = (int) ($data['completion_tokens'] ?? 0);
        $totalTokens = $promptTokens + $completionTokens;

        $model = (string) ($data['model'] ?? config('services.openai.model', 'gpt-5'));
        $cost = $this->estimateCost($model, $promptTokens, $completionTokens);

        try {
            return AiUsageLog::create([
                'tenant_id' => $data['tenant_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'conversation_id' => $data['conversation_id'] ?? null,
                'chat_message_id' => $data['chat_message_id'] ?? null,
                'model' => $model,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $totalTokens,
                'estimated_cost' => $cost,
                'latency_ms' => (int) ($data['latency_ms'] ?? 0),
                'response_status' => (string) ($data['response_status'] ?? 'success'),
                'error_message' => isset($data['error_message']) ? (string) $data['error_message'] : null,
                'feature' => (string) ($data['feature'] ?? 'chat'),
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to save AI usage log: '.$e->getMessage());

            return new AiUsageLog;
        }
    }

    /**
     * Record a request that did not succeed.
     *
     * Failures used to go unlogged entirely, so the analytics screen reported a
     * 100% success rate by construction and error rates were invisible.
     */
    public function logFailure(array $data, string $status, ?string $message = null): AiUsageLog
    {
        return $this->log(array_merge($data, [
            'response_status' => $status,
            'error_message' => $message,
        ]));
    }

    /**
     * Estimated cost in USD, from the per-model rates in config.
     *
     * Rates were previously hard-coded at figures that predate the models in use
     * — gpt-5 completions were priced at a fifth of their actual rate.
     */
    private function estimateCost(string $model, int $promptTokens, int $completionTokens): float
    {
        $table = config('services.openai.pricing', []);
        $rates = $table['default'] ?? ['prompt' => 0.0, 'completion' => 0.0];

        // Longest matching prefix wins, so gpt-4o-mini doesn't match gpt-4o.
        $bestMatch = '';
        foreach ($table as $prefix => $candidate) {
            if ($prefix !== 'default'
                && str_starts_with($model, $prefix)
                && strlen($prefix) > strlen($bestMatch)
            ) {
                $bestMatch = $prefix;
                $rates = $candidate;
            }
        }

        return round(
            ($promptTokens / 1000 * $rates['prompt']) + ($completionTokens / 1000 * $rates['completion']),
            6,
        );
    }
}
