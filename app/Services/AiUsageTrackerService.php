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

        // Estimated cost ($0.0015 per 1k prompt, $0.0020 per 1k completion tokens)
        $cost = round(($promptTokens * 0.0000015) + ($completionTokens * 0.0000020), 6);

        try {
            return AiUsageLog::create([
                'tenant_id' => $data['tenant_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'conversation_id' => $data['conversation_id'] ?? null,
                'chat_message_id' => $data['chat_message_id'] ?? null,
                'model' => (string) ($data['model'] ?? 'gpt-5'),
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
}
