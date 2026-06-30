<?php

namespace App\Services;

use App\Exceptions\OpenAiException;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the OpenAI Chat Completions API.
 * Holds no tenant logic — see AiAssistantService for orchestration.
 */
class OpenAiService
{
    public function isConfigured(): bool
    {
        return filled(config('services.openai.api_key'));
    }

    /**
     * Whether the given model is a reasoning model (gpt-5 family or o-series),
     * which uses a different request-parameter contract. The non-reasoning chat
     * variant `gpt-5-chat-latest` is intentionally excluded.
     */
    private function isReasoningModel(string $model): bool
    {
        $model = strtolower($model);

        if (str_starts_with($model, 'gpt-5-chat')) {
            return false;
        }

        return str_starts_with($model, 'gpt-5')
            || (bool) preg_match('/^o[1-9]/', $model);
    }

    /**
     * Call /chat/completions and return the decoded response.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @param  array<string, mixed>  $options  Overrides (model, temperature, max_tokens, tool_choice, ...)
     * @return array<string, mixed>
     *
     * @throws OpenAiException
     */
    public function chat(array $messages, array $tools = [], array $options = []): array
    {
        if (! $this->isConfigured()) {
            throw new OpenAiException('OpenAI API key is not configured.');
        }

        $model = (string) ($options['model'] ?? config('services.openai.model', 'gpt-4o-mini'));

        $payload = array_merge([
            'model' => $model,
            'temperature' => 0.3,
            'max_tokens' => 800,
        ], $options, ['messages' => $messages]);

        // Reasoning models (gpt-5 family, o-series) have a different parameter contract:
        // they reject `temperature` (only the default is allowed) and use
        // `max_completion_tokens` instead of `max_tokens`. They also spend hidden
        // reasoning tokens against that budget, so give them generous headroom.
        if ($this->isReasoningModel($model)) {
            $requested = (int) ($payload['max_tokens'] ?? 0);
            $floor = (int) config('services.openai.max_output_tokens', 6000);
            $payload['max_completion_tokens'] = max($requested, $floor);
            unset($payload['max_tokens'], $payload['temperature']);

            $effort = config('services.openai.reasoning_effort');
            if (filled($effort)) {
                $payload['reasoning_effort'] = $effort;
            }
        }

        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] ??= 'auto';
        }

        $response = Http::withToken((string) config('services.openai.api_key'))
            ->timeout(45)
            ->retry(2, 200, throw: false)
            ->post(rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/').'/chat/completions', $payload);

        if ($response->failed()) {
            throw new OpenAiException('OpenAI request failed ('.$response->status().'): '.$response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * Run a live web search using OpenAI's built-in web-search model.
     * These models do not accept temperature/tools, so this is a separate call.
     *
     * @return array<string, mixed>
     *
     * @throws OpenAiException
     */
    public function webSearch(string $query): array
    {
        if (! $this->isConfigured()) {
            throw new OpenAiException('OpenAI API key is not configured.');
        }

        $response = Http::withToken((string) config('services.openai.api_key'))
            ->timeout(45)
            ->retry(2, 200, throw: false)
            ->post(rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/').'/chat/completions', [
                'model' => config('services.openai.search_model', 'gpt-4o-mini-search-preview'),
                'messages' => [['role' => 'user', 'content' => $query]],
                'web_search_options' => (object) [],
            ]);

        if ($response->failed()) {
            throw new OpenAiException('OpenAI web search failed ('.$response->status().'): '.$response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * Embed text into a vector (for learning from resolved tickets via semantic search).
     *
     * @return array<int, float>
     *
     * @throws OpenAiException
     */
    public function embed(string $text): array
    {
        if (! $this->isConfigured()) {
            throw new OpenAiException('OpenAI API key is not configured.');
        }

        $response = Http::withToken((string) config('services.openai.api_key'))
            ->timeout(45)
            ->retry(2, 200, throw: false)
            ->post(rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/').'/embeddings', [
                'model' => config('services.openai.embed_model', 'text-embedding-3-small'),
                'input' => mb_substr($text, 0, 8000),
            ]);

        if ($response->failed()) {
            throw new OpenAiException('OpenAI embedding failed ('.$response->status().'): '.$response->body());
        }

        return $response->json('data.0.embedding') ?? [];
    }
}
