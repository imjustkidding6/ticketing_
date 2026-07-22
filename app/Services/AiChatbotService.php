<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiChatbotService
{
    public function __construct(
        private readonly OpenAiService $openAi,
        private readonly PromptInjectionService $promptInjection,
        private readonly ModerationService $moderation,
        private readonly SemanticCacheService $cache
    ) {}

    /**
     * Create a new AI chat conversation for the given user.
     */
    public function startConversation(User $user, ?string $title = null): ChatConversation
    {
        $tenantId = $user->tenant_id;
        if (! $tenantId) {
            $tenant = Tenant::first();
            $tenantId = $tenant ? $tenant->id : Tenant::create(['name' => 'Demo Company', 'slug' => 'demo-company'])->id;
        }

        return ChatConversation::create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'channel' => ChatConversation::CHANNEL_AGENT,
            'title' => $title ?? 'New AI Conversation',
            'status' => ChatConversation::STATUS_ACTIVE,
            'last_message_at' => now(),
        ]);
    }

    /**
     * Send a user message and generate an assistant response.
     *
     * @return array{userMessage: ChatMessage, assistantMessage: ChatMessage}
     */
    public function processMessage(ChatConversation $conversation, User $user, string $userContent): array
    {
        $tenantId = $conversation->tenant_id;
        $userId = $user->id;

        // 1. Create User Message record
        $userMessage = ChatMessage::create([
            'chat_conversation_id' => $conversation->id,
            'role' => ChatMessage::ROLE_USER,
            'content' => $userContent,
        ]);

        // Auto-title conversation on first message if default
        if ($conversation->title === 'New AI Conversation' || blank($conversation->title)) {
            $conversation->update([
                'title' => \Illuminate\Support\Str::limit($userContent, 40),
            ]);
        }

        $conversation->touchLastMessage();

        // 2. Security Check: Prompt Injection
        if ($this->promptInjection->isInjection($userContent, $tenantId, $userId)) {
            $assistantMessage = ChatMessage::create([
                'chat_conversation_id' => $conversation->id,
                'role' => ChatMessage::ROLE_ASSISTANT,
                'content' => 'Action blocked: Prompt injection or system instruction override attempt detected.',
            ]);

            return ['userMessage' => $userMessage, 'assistantMessage' => $assistantMessage];
        }

        // 3. Moderation Check
        if ($this->moderation->isFlagged($userContent, 'strict', $tenantId, $userId)) {
            $assistantMessage = ChatMessage::create([
                'chat_conversation_id' => $conversation->id,
                'role' => ChatMessage::ROLE_ASSISTANT,
                'content' => 'Action blocked: Content flagged by security moderation policies.',
            ]);

            return ['userMessage' => $userMessage, 'assistantMessage' => $assistantMessage];
        }

        // 4. Generate AI Response via OpenAI or fallback engine
        $replyText = $this->generateAssistantResponse($conversation, $userContent);

        // 5. Create Assistant Message record
        $assistantMessage = ChatMessage::create([
            'chat_conversation_id' => $conversation->id,
            'role' => ChatMessage::ROLE_ASSISTANT,
            'content' => $replyText,
        ]);

        return ['userMessage' => $userMessage, 'assistantMessage' => $assistantMessage];
    }

    /**
     * Generate response via OpenAI chat completion or intelligent system fallback.
     */
    private function generateAssistantResponse(ChatConversation $conversation, string $userContent): string
    {
        if ($this->openAi->isConfigured()) {
            try {
                // Build conversation history for LLM
                $history = $conversation->messages()
                    ->take(10)
                    ->get()
                    ->map(fn (ChatMessage $m) => [
                        'role' => $m->role,
                        'content' => (string) $m->content,
                    ])
                    ->toArray();

                $messages = array_merge([
                    [
                        'role' => 'system',
                        'content' => 'You are an intelligent, helpful AI Assistant embedded into the Enterprise Admin Panel. Provide clear, well-formatted markdown responses with code blocks where applicable.',
                    ],
                ], $history);

                $response = $this->openAi->chat($messages, [], [
                    'temperature' => 0.7,
                    'max_tokens' => 1500,
                ]);

                $content = $response['choices'][0]['message']['content'] ?? null;
                if (filled($content)) {
                    return trim((string) $content);
                }
            } catch (Throwable $e) {
                Log::warning('AiChatbotService OpenAI call failed, falling back: '.$e->getMessage());
            }
        }

        // Intelligent System Fallback Response Engine
        return $this->generateFallbackReply($userContent);
    }

    /**
     * Fallback response engine when OpenAI API key is unconfigured or unreachable.
     */
    private function generateFallbackReply(string $prompt): string
    {
        $lower = strtolower($prompt);

        if (str_contains($lower, 'code') || str_contains($lower, 'example') || str_contains($lower, 'php')) {
            return "Here is a code example matching your request:\n\n```php\nnamespace App\\Services;\n\nclass SystemHealth\n{\n    public function check(): bool\n    {\n        return true;\n    }\n}\n```\n\nLet me know if you would like me to customize this further!";
        }

        if (str_contains($lower, 'tenant') || str_contains($lower, 'user')) {
            return "You can manage system tenants and global administrators directly from the **Workspace** and **Administration** sections in the left sidebar menu.";
        }

        return "I am your AI Assistant. I can answer questions, summarize logs, generate code snippets, and help manage your system operations. How can I assist you today?";
    }
}
