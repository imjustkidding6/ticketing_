<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiModerationLog;
use Illuminate\Support\Facades\Log;

class PromptInjectionService
{
    /** @var array<int, string> */
    private array $patterns = [
        '/ignore (all )?previous instructions/i',
        '/reveal (your )?system prompt/i',
        '/show (hidden )?instructions/i',
        '/act as (a )?developer/i',
        '/output (system )?secrets/i',
        '/print (your )?system prompt/i',
        '/override system guidelines/i',
        '/disregard previous directives/i',
    ];

    /**
     * Check if a given text contains prompt injection attempts.
     */
    public function isInjection(string $text, ?int $tenantId = null, ?int $userId = null): bool
    {
        foreach ($this->patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                $this->logIncident($text, "Matched pattern: {$pattern}", $tenantId, $userId);

                return true;
            }
        }

        return false;
    }

    /**
     * Log prompt injection incident.
     */
    private function logIncident(string $text, string $reason, ?int $tenantId, ?int $userId): void
    {
        Log::warning("Prompt injection attempt detected: {$reason}", [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'input' => mb_substr($text, 0, 500),
        ]);

        try {
            AiModerationLog::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'type' => 'prompt_injection',
                'severity' => 'high',
                'input_text' => mb_substr($text, 0, 1000),
                'reason' => $reason,
                'action_taken' => 'blocked',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to save prompt injection log: '.$e->getMessage());
        }
    }
}
