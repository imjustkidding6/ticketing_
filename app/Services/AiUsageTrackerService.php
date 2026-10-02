<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiUsageLog;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiUsageTrackerService
{
    /**
     * Ambient attributes merged into every log() call for the duration of a closure.
     *
     * @var array<string, mixed>
     */
    private array $context = [];

    /** @var list<int> */
    private array $loggedIds = [];

    /** @var list<int> */
    private array $lastLoggedIds = [];

    /**
     * Attribute every usage log written inside $callback to a tenant, a user and a
     * product surface.
     *
     * Why this exists: OpenAiService::post() is the only writer, and it knows the
     * endpoint but not the caller — so every copilot call to date logged
     * `tenant_id = null`, `user_id = null`, which makes "AI cost per workspace"
     * uncomputable. The caller is the only layer that knows.
     *
     * **The previous context is restored in a `finally`.** This is the
     * TenantMailService::configure() lesson in a second place: it is global runtime
     * state on a singleton, so a queue worker handling two tenants would otherwise bill
     * one workspace's tokens to the other the moment a call threw. The restore is
     * unconditional, including on an exception.
     *
     * @template TReturn
     *
     * @param  array<string, mixed>  $context
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function withContext(array $context, Closure $callback): mixed
    {
        $previousContext = $this->context;
        $previousIds = $this->loggedIds;

        $this->context = array_merge($this->context, $context);
        $this->loggedIds = [];

        try {
            return $callback();
        } finally {
            $written = $this->loggedIds;
            // Reported as "this context's logs", so two analyses in one request do not
            // see each other's ids...
            $this->lastLoggedIds = $written;
            // ...while an enclosing context still collects them, so nesting loses none.
            $this->loggedIds = array_values(array_unique(array_merge($previousIds, $written)));
            $this->context = $previousContext;
        }
    }

    /**
     * The ids of the usage logs written inside the most recently closed withContext().
     *
     * `ai_ticket_analyses.usage_log_ids` is built from this, which is how cost per
     * ticket joins to `ai_usage_logs` instead of being guessed from timestamps.
     *
     * @return list<int>
     */
    public function lastLoggedIds(): array
    {
        return $this->lastLoggedIds;
    }

    /**
     * Record an AI request execution usage log.
     *
     * @param  array<string, mixed>  $data
     */
    public function log(array $data): AiUsageLog
    {
        $data = $this->applyContext($data);

        $promptTokens = (int) ($data['prompt_tokens'] ?? 0);
        $completionTokens = (int) ($data['completion_tokens'] ?? 0);
        $totalTokens = $promptTokens + $completionTokens;

        $model = (string) ($data['model'] ?? config('services.openai.model', 'gpt-5'));
        $cost = $this->estimateCost($model, $promptTokens, $completionTokens);

        try {
            $log = AiUsageLog::create([
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

            if ($log->exists) {
                $this->loggedIds[] = (int) $log->getKey();
            }

            return $log;
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
     * Merge the ambient context into one log call.
     *
     * Defaults only: an explicit key in $data always wins, because the writer knows
     * more about the individual call than the caller that opened the context.
     *
     * `feature` is the one exception, and deliberately so. OpenAiService::post() derives
     * it from the endpoint, which gives exactly two values: `embed` for /embeddings and
     * `chat` for everything else. `embed` is a real answer and is left alone; `chat` is
     * the fallback for "a chat completion of some kind", so a context that names the
     * product surface (`copilot`) replaces it. Without this, every surface built on
     * chat/completions would be indistinguishable in the cost reports.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyContext(array $data): array
    {
        if ($this->context === []) {
            return $data;
        }

        $contextFeature = $this->context['feature'] ?? null;
        $merged = array_merge($this->context, $data);

        if ($contextFeature !== null && ($data['feature'] ?? 'chat') === 'chat') {
            $merged['feature'] = $contextFeature;
        }

        return $merged;
    }

    /**
     * Estimated cost in USD, from the per-model rates in config.
     *
     * Rates were previously hard-coded at figures that predate the models in use
     * — gpt-5 completions were priced at a fifth of their actual rate.
     *
     * Public so a caller that persists its own cost figure (AnalyzeTicketService writes
     * one onto ai_ticket_analyses) uses this ladder rather than a second copy of it.
     */
    public function estimateCost(string $model, int $promptTokens, int $completionTokens): float
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
