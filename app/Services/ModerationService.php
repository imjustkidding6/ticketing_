<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiModerationLog;
use Illuminate\Support\Facades\Log;

class ModerationService
{
    /** @var array<int, string> */
    private array $prohibitedKeywords = [
        'hate speech',
        'violent threat',
        'self-harm instructions',
        'illegal activity guide',
        'exploit vulnerability',
    ];

    /**
     * Check if text violates content moderation policies.
     *
     * @param  string  $mode  strict | balanced | relaxed
     */
    public function isFlagged(string $text, string $mode = 'balanced', ?int $tenantId = null, ?int $userId = null): bool
    {
        $normalized = strtolower($text);

        foreach ($this->prohibitedKeywords as $keyword) {
            if (str_contains($normalized, $keyword)) {
                $this->logEvent($text, "Matched keyword: {$keyword}", $tenantId, $userId);

                return true;
            }
        }

        if ($mode === 'strict' && (str_contains($normalized, 'hack') || str_contains($normalized, 'exploit'))) {
            $this->logEvent($text, 'Strict mode security trigger', $tenantId, $userId);

            return true;
        }

        return false;
    }

    /**
     * Log moderation event.
     */
    private function logEvent(string $text, string $reason, ?int $tenantId, ?int $userId): void
    {
        Log::info("Content moderation flagged message: {$reason}", [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
        ]);

        try {
            AiModerationLog::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'type' => 'moderation',
                'severity' => 'medium',
                'input_text' => mb_substr($text, 0, 1000),
                'reason' => $reason,
                'action_taken' => 'flagged',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to log moderation event: '.$e->getMessage());
        }
    }
}
