<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiProfile;

class AiProfileService
{
    /**
     * Get list of default pre-defined AI profiles.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDefaultProfiles(): array
    {
        return [
            [
                'name' => 'Fast',
                'slug' => 'fast',
                'model' => 'gpt-4o-mini',
                'temperature' => 0.3,
                'max_tokens' => 1000,
                'top_p' => 1.0,
                'frequency_penalty' => 0.0,
                'presence_penalty' => 0.0,
                'enabled_tools' => ['kb_search'],
                'is_default' => false,
            ],
            [
                'name' => 'Balanced',
                'slug' => 'balanced',
                'model' => 'gpt-4o',
                'temperature' => 0.7,
                'max_tokens' => 2000,
                'top_p' => 1.0,
                'frequency_penalty' => 0.0,
                'presence_penalty' => 0.0,
                'enabled_tools' => ['kb_search', 'ticket_search', 'web_search'],
                'is_default' => true,
            ],
            [
                'name' => 'Cheap',
                'slug' => 'cheap',
                'model' => 'gpt-4o-mini',
                'temperature' => 0.2,
                'max_tokens' => 500,
                'top_p' => 1.0,
                'frequency_penalty' => 0.0,
                'presence_penalty' => 0.0,
                'enabled_tools' => ['kb_search'],
                'is_default' => false,
            ],
            [
                'name' => 'High Accuracy',
                'slug' => 'high-accuracy',
                'model' => 'gpt-5',
                'temperature' => 0.4,
                'max_tokens' => 4000,
                'top_p' => 1.0,
                'frequency_penalty' => 0.0,
                'presence_penalty' => 0.0,
                'enabled_tools' => ['kb_search', 'ticket_search', 'web_search', 'bug_report'],
                'is_default' => false,
            ],
            [
                'name' => 'Vision',
                'slug' => 'vision',
                'model' => 'gpt-4o',
                'temperature' => 0.5,
                'max_tokens' => 2000,
                'top_p' => 1.0,
                'frequency_penalty' => 0.0,
                'presence_penalty' => 0.0,
                'enabled_tools' => ['vision', 'kb_search'],
                'is_default' => false,
            ],
            [
                'name' => 'Reasoning',
                'slug' => 'reasoning',
                'model' => 'gpt-5',
                'temperature' => 0.2,
                'max_tokens' => 6000,
                'top_p' => 1.0,
                'frequency_penalty' => 0.0,
                'presence_penalty' => 0.0,
                'enabled_tools' => ['kb_search', 'ticket_search', 'web_search', 'bug_report', 'charts'],
                'is_default' => false,
            ],
        ];
    }

    /**
     * Get current active profile or default profile.
     */
    public function getActiveProfile(): AiProfile
    {
        $profile = AiProfile::where('is_default', true)->first();
        if ($profile) {
            return $profile;
        }

        return AiProfile::firstOrCreate(
            ['slug' => 'balanced'],
            $this->getDefaultProfiles()[1]
        );
    }
}
