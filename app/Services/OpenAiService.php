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

        $payload = array_merge([
            'model' => config('services.openai.model', 'gpt-4o-mini'),
            'temperature' => 0.3,
            'max_tokens' => 800,
        ], $options, ['messages' => $messages]);

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
}
