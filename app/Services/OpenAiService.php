<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\OpenAiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Service for interacting with OpenAI API.
 * Encapsulates low-level HTTP requests for Chat Completions, Embeddings, and Web Search.
 * Holds no tenant or domain logic.
 */
class OpenAiService
{
    private readonly ?string $apiKey;

    private readonly string $baseUrl;

    private readonly string $model;

    private readonly string $embeddingModel;

    private readonly int $timeout;

    private readonly string $searchModel;

    private readonly string $reasoningEffort;

    private readonly int $maxOutputTokens;

    /**
     * Create a new OpenAiService instance.
     * Configuration parameters default to config/services.php if not explicitly passed.
     */
    public function __construct(
        ?string $apiKey = null,
        ?string $baseUrl = null,
        ?string $model = null,
        ?string $embeddingModel = null,
        ?int $timeout = null,
        ?string $searchModel = null,
        ?string $reasoningEffort = null,
        ?int $maxOutputTokens = null,
    ) {
        $this->apiKey = $apiKey ?? (string) config('services.openai.api_key', '');
        $this->baseUrl = rtrim($baseUrl ?? (string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
        $this->model = $model ?? (string) config('services.openai.model', 'gpt-5');
        $this->embeddingModel = $embeddingModel ?? (string) config('services.openai.embed_model', 'text-embedding-3-small');
        $this->timeout = $timeout ?? (int) config('services.openai.timeout', 60);
        $this->searchModel = $searchModel ?? (string) config('services.openai.search_model', 'gpt-4o-mini-search-preview');
        $this->reasoningEffort = $reasoningEffort ?? (string) config('services.openai.reasoning_effort', 'low');
        $this->maxOutputTokens = $maxOutputTokens ?? (int) config('services.openai.max_output_tokens', 6000);
    }

    /**
     * Determine if OpenAI API key is configured.
     */
    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    /**
     * Call /chat/completions API and return decoded response.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @param  array<string, mixed>  $options  Overrides (model, temperature, max_tokens, tool_choice, response_format, ...)
     * @return array<string, mixed>
     *
     * @throws OpenAiException
     */
    public function chat(array $messages, array $tools = [], array $options = []): array
    {
        $this->ensureConfigured();

        $selectedModel = (string) ($options['model'] ?? $this->model);

        $payload = array_merge([
            'model' => $selectedModel,
            'temperature' => 0.3,
            'max_tokens' => 800,
        ], $options, ['messages' => $messages]);

        // Adapt payload for reasoning models (gpt-5 family, o-series)
        if ($this->isReasoningModel($selectedModel)) {
            $requested = (int) ($payload['max_tokens'] ?? 0);
            $payload['max_completion_tokens'] = max($requested, $this->maxOutputTokens);
            unset($payload['max_tokens'], $payload['temperature']);

            if (filled($this->reasoningEffort)) {
                $payload['reasoning_effort'] = $this->reasoningEffort;
            }
        }

        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] ??= 'auto';
        }

        return $this->post('/chat/completions', $payload);
    }

    /**
     * Call Embeddings API and return vector representation.
     *
     * @return array<int, float>
     *
     * @throws OpenAiException
     */
    public function embed(string $text): array
    {
        $this->ensureConfigured();

        $response = $this->post('/embeddings', [
            'model' => $this->embeddingModel,
            'input' => mb_substr($text, 0, 8000),
        ]);

        /** @var array<int, float>|null $embedding */
        $embedding = $response['data'][0]['embedding'] ?? null;

        if ($embedding === null || ! is_array($embedding)) {
            throw new OpenAiException('OpenAI embedding response format invalid: missing vector data.');
        }

        return $embedding;
    }

    /**
     * Run a live web search using OpenAI's search capability.
     *
     * @return array<string, mixed>
     *
     * @throws OpenAiException
     */
    public function webSearch(string $query): array
    {
        $this->ensureConfigured();

        return $this->post('/chat/completions', [
            'model' => $this->searchModel,
            'messages' => [['role' => 'user', 'content' => $query]],
            'web_search_options' => (object) [],
        ]);
    }

    /**
     * Determine if a given model is a reasoning model (gpt-5 family or o-series).
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
     * Assert that the OpenAI API key is configured.
     *
     * @throws OpenAiException
     */
    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new OpenAiException('OpenAI API key is not configured.');
        }
    }

    /**
     * Send a JSON POST request to the specified OpenAI endpoint using Laravel's HTTP Client.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws OpenAiException
     */
    private function post(string $endpoint, array $payload): array
    {
        $circuit = app(CircuitBreakerService::class);
        $circuit->ensureAvailable();

        $url = $this->baseUrl.'/'.ltrim($endpoint, '/');
        $startTime = microtime(true);

        try {
            /** @var Response $response */
            $response = Http::withToken((string) $this->apiKey)
                ->timeout($this->timeout)
                ->acceptJson()
                ->asJson()
                ->retry(2, 200, throw: false)
                ->post($url, $payload);

            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            if ($response->failed()) {
                $circuit->recordFailure();
                $status = $response->status();
                $body = $response->body();
                throw new OpenAiException("OpenAI API request to [{$endpoint}] failed with status {$status}: {$body}");
            }

            $circuit->recordSuccess();

            /** @var array<string, mixed>|null $data */
            $data = $response->json();

            if ($data === null && $response->body() !== '') {
                throw new OpenAiException("OpenAI API request to [{$endpoint}] returned malformed JSON response.");
            }

            $resultData = $data ?? [];
            $usage = $resultData['usage'] ?? [];

            app(AiUsageTrackerService::class)->log([
                'model' => (string) ($payload['model'] ?? $this->model),
                'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
                'latency_ms' => $durationMs,
                'response_status' => 'success',
                'feature' => str_contains($endpoint, 'embed') ? 'embed' : 'chat',
            ]);

            return $resultData;
        } catch (OpenAiException $e) {
            $circuit->recordFailure();
            throw $e;
        } catch (ConnectionException $e) {
            $circuit->recordFailure();
            throw new OpenAiException("OpenAI connection failed or timed out after {$this->timeout}s: {$e->getMessage()}", previous: $e);
        } catch (RequestException $e) {
            $circuit->recordFailure();
            throw new OpenAiException("OpenAI HTTP request error: {$e->getMessage()}", previous: $e);
        } catch (Throwable $e) {
            $circuit->recordFailure();
            throw new OpenAiException("Unexpected error communicating with OpenAI: {$e->getMessage()}", previous: $e);
        }
    }
}
