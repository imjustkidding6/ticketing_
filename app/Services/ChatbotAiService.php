<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;

class ChatbotAiService
{
    /**
     * @param  array<int, array{role: string, content: string}>  $conversation
     * @return array{reply: string, confidence: float, model: string, prompt_tokens: int, completion_tokens: int, fallback: bool}
     */
    public function generateReply(Tenant $tenant, array $conversation): array
    {
        $settings = $this->getSettings();
        $apiKey = $settings['chatbot_api_key'] ?? null;
        $model = $settings['chatbot_model'] ?? 'gemini-1.5-flash';
        $systemPrompt = $settings['chatbot_system_prompt'] ?? 'You are a helpful support assistant for a SaaS helpdesk. Reply in plain language and be concise.';

        if (! $apiKey) {
            return $this->fallbackResponse($model);
        }

        $payload = [
            'systemInstruction' => [
                'role' => 'system',
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => $this->formatConversation($conversation),
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => 512,
            ],
        ];

        $response = Http::timeout(15)
            ->retry(1, 250)
            ->post($this->geminiUrl($model, $apiKey), $payload);

        if (! $response->ok()) {
            return $this->fallbackResponse($model);
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text', '');

        if (! is_string($text) || trim($text) === '') {
            return $this->fallbackResponse($model);
        }

        $decoded = $this->parseJsonReply($text);
        $reply = $decoded['reply'] ?? trim($text);
        $confidence = (float) ($decoded['confidence'] ?? 0.75);

        return [
            'reply' => $reply,
            'confidence' => $confidence,
            'model' => $model,
            'prompt_tokens' => (int) data_get($response->json(), 'usageMetadata.promptTokenCount', 0),
            'completion_tokens' => (int) data_get($response->json(), 'usageMetadata.candidatesTokenCount', 0),
            'fallback' => false,
        ];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $conversation
     * @param  array<int, string>  $departmentNames
     * @return array{subject: string, description: string, priority: string, department: string|null, fallback: bool}
     */
    public function generateTicketDraft(Tenant $tenant, array $conversation, array $departmentNames): array
    {
        $settings = $this->getSettings();
        $apiKey = $settings['chatbot_api_key'] ?? null;
        $model = $settings['chatbot_model'] ?? 'gemini-1.5-flash';

        if (! $apiKey) {
            return $this->fallbackDraft($conversation);
        }

        $systemPrompt = 'Generate a concise ticket draft from the conversation. Return JSON only with: subject, description, priority (low|medium|high|critical), department (optional).';
        $departmentHint = $departmentNames ? 'Available departments: '.implode(', ', $departmentNames).'.' : 'No departments available.';

        $payload = [
            'systemInstruction' => [
                'role' => 'system',
                'parts' => [['text' => $systemPrompt.' '.$departmentHint]],
            ],
            'contents' => $this->formatConversation($conversation),
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => 256,
            ],
        ];

        $response = Http::timeout(15)
            ->retry(1, 250)
            ->post($this->geminiUrl($model, $apiKey), $payload);

        if (! $response->ok()) {
            return $this->fallbackDraft($conversation);
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text', '');
        $decoded = $this->parseJsonReply(is_string($text) ? $text : '');

        return [
            'subject' => (string) ($decoded['subject'] ?? $this->fallbackDraft($conversation)['subject']),
            'description' => (string) ($decoded['description'] ?? $this->fallbackDraft($conversation)['description']),
            'priority' => (string) ($decoded['priority'] ?? 'medium'),
            'department' => $decoded['department'] ?? null,
            'fallback' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettings(): array
    {
        return SystemSetting::getByGroup('chatbot');
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $conversation
     * @return array<int, array{role: string, parts: array<int, array{text: string}>}>
     */
    private function formatConversation(array $conversation): array
    {
        return array_map(function (array $message) {
            $role = $message['role'] === 'assistant' ? 'model' : 'user';

            return [
                'role' => $role,
                'parts' => [['text' => $message['content']]],
            ];
        }, $conversation);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJsonReply(string $text): array
    {
        $trimmed = trim($text);
        $decoded = json_decode($trimmed, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($trimmed, $start, $end - $start + 1), true);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array{reply: string, confidence: float, model: string, prompt_tokens: int, completion_tokens: int, fallback: bool}
     */
    private function fallbackResponse(string $model): array
    {
        return [
            'reply' => 'I’m having trouble answering that right now, but I can create a support ticket for you.',
            'confidence' => 0.0,
            'model' => $model,
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'fallback' => true,
        ];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $conversation
     * @return array{subject: string, description: string, priority: string, department: string|null, fallback: bool}
     */
    private function fallbackDraft(array $conversation): array
    {
        $firstUser = collect($conversation)->firstWhere('role', 'user')['content'] ?? 'Support request';

        return [
            'subject' => str($firstUser)->limit(80)->toString(),
            'description' => collect($conversation)
                ->map(fn (array $message) => strtoupper($message['role']).': '.$message['content'])
                ->implode("\n"),
            'priority' => 'medium',
            'department' => null,
            'fallback' => true,
        ];
    }

    private function geminiUrl(string $model, string $apiKey): string
    {
        return "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
    }
}
