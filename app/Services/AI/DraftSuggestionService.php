<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Exceptions\OpenAiException;
use App\Models\AiSuggestionVerdict;
use App\Models\AiTicketAnalysis;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AiAssistantService;
use App\Services\AiUsageTrackerService;
use App\Services\ModerationService;
use App\Services\OpenAiService;
use App\Services\PromptInjectionService;
use App\Services\TicketService;
use App\Support\Ai\DraftSuggestion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * "Polish with Nexus AI" on the create-ticket form, plus the suggested PRIORITY and the
 * Accept / Edit / Reject capture behind it.
 *
 * It is its own service, beside AnalyzeTicketService and AgentSelectionService, for two
 * reasons: AiAssistantService owns the conversational channel and the public-portal
 * polish and is 1,200 lines already, and every method in AnalyzeTicketService is keyed on
 * a persisted Ticket — which is precisely what does not exist when an engineer is still
 * typing the draft.
 *
 * **ONE OpenAI call.** The ticket text is identical for "rewrite this" and "how urgent is
 * it", so the two new keys are appended to the same strict-JSON schema rather than paid
 * for twice.
 *
 * **Only admissible priorities are ever offered.** The admissible set is computed from
 * TicketService's OWN guards, dry-run (`draftWouldAdmitPriority()`), so a suggestion
 * cannot be satisfied here and refused by `createTicket()` at submit. The model is not
 * asked to be careful — it is never shown the uncovered options, and the reply is
 * re-checked against the same set server-side.
 *
 * **Nothing auto-applies.** The reply pre-fills the priority select; the engineer submits.
 */
class DraftSuggestionService
{
    /** Only ever `create` or `edit`; the edit form wants the text polish and nothing else. */
    public const CONTEXT_CREATE = 'create';

    public const CONTEXT_EDIT = 'edit';

    /**
     * The additional schema this surface asks for, on top of DRAFT_INSTRUCTION.
     *
     * Appended as an explicit extension of the "exactly these keys" sentence above it, so
     * the two blocks do not contradict each other.
     */
    public const PRIORITY_INSTRUCTION = 'IN ADDITION to the keys listed above, return these keys (they extend that list):'."\n"
        .'"priority": the single most appropriate priority for this ticket, chosen ONLY from the PRIORITY OPTIONS given below, or null if the notes do not support a judgement.'."\n"
        .'"priority_reason": one short sentence (max ~140 chars) saying why, in plain words an engineer would use. Null when "priority" is null.'."\n"
        .'Judge urgency from what the notes actually say — scope of impact, whether work is blocked, and any stated deadline. Do not inflate it.';

    public function __construct(
        private readonly OpenAiService $openAi,
        private readonly PromptInjectionService $injection,
        private readonly ModerationService $moderation,
        private readonly AiUsageTrackerService $usage,
        private readonly TicketService $tickets,
    ) {}

    /**
     * Polish a draft and, on the create form, suggest a priority the workspace can honour.
     *
     * Never throws for a refusal — that is `refusal` on the returned DTO, and the engineer
     * is shown it. An OpenAiException (including a tripped circuit breaker, which
     * OpenAiService::post() raises before any HTTP) propagates, because "the provider is
     * down" is a different answer from "we declined".
     *
     * @param  string  $context  self::CONTEXT_CREATE | self::CONTEXT_EDIT
     *
     * @throws OpenAiException
     */
    public function suggest(
        Tenant $tenant,
        ?User $user,
        string $subject,
        string $description,
        ?int $clientId = null,
        string $context = self::CONTEXT_CREATE,
    ): DraftSuggestion {
        $subject = trim($subject);
        $description = Str::limit(trim($description), (int) config('ai.draft.input_chars', 8000), '');

        // Guardrails first, and before any HTTP. The description on this form is routinely
        // PASTED CUSTOMER TEXT, so injection screening is not optional merely because a
        // colleague pressed the button. Both services are local (regex / keyword scans,
        // no HTTP) and both write their own attributable AiModerationLog row.
        $screened = trim($subject."\n".$description);

        if ($this->injection->isInjection($screened, (int) $tenant->getKey(), $user?->getKey())) {
            return $this->refuse($subject, $description, 'That text looks like an attempt to instruct the AI rather than describe a ticket, so it was not sent.');
        }

        if ($this->moderation->isFlagged($screened, 'balanced', (int) $tenant->getKey(), $user?->getKey())) {
            return $this->refuse($subject, $description, 'That text was flagged by the content filter, so it was not sent to the AI.');
        }

        // CircuitBreakerService is NOT invoked here: OpenAiService::post() already wraps
        // every endpoint, and a second ensureAvailable()/recordFailure() pair would
        // double-count a single outage.

        $wantsPriority = $context === self::CONTEXT_CREATE;
        $admissible = $wantsPriority ? $this->admissiblePriorities($tenant, $clientId) : [];
        $gap = $wantsPriority ? $this->priorityGap($tenant, $clientId, $admissible) : null;

        $startedAt = microtime(true);

        /** @var array<string, mixed> $response */
        $response = $this->usage->withContext([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user?->getKey(),
            // The agent-copilot family, matching analyze(). Before P6c this surface logged
            // tenant_id = null, user_id = null, so its spend was uncomputable.
            'feature' => 'copilot',
        ], fn () => $this->openAi->chat(
            [
                ['role' => 'system', 'content' => $this->systemPrompt($tenant, $admissible, $wantsPriority)],
                ['role' => 'user', 'content' => "ROUGH NOTES\nSubject: ".$subject."\n\nDescription:\n".$description],
            ],
            [],
            [
                'max_tokens' => (int) config('ai.draft.max_tokens', 800),
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
            ],
        ));

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
        $usageLogIds = $this->usage->lastLoggedIds();

        $decoded = json_decode((string) ($response['choices'][0]['message']['content'] ?? ''), true);

        if (! is_array($decoded)) {
            // Unparseable or truncated. The original text back, unchanged, which is what
            // this button has always done on a bad reply.
            return new DraftSuggestion(subject: $subject, description: $description);
        }

        $rejected = [];
        $priority = $wantsPriority
            ? $this->validatePriority($decoded['priority'] ?? null, $admissible, $rejected)
            : null;

        $model = (string) ($response['model'] ?? config('services.openai.model', 'gpt-5'));
        $usageTokens = $response['usage'] ?? [];

        $suggestion = new DraftSuggestion(
            subject: trim((string) ($decoded['subject'] ?? '')) ?: $subject,
            description: trim((string) ($decoded['description'] ?? '')) ?: $description,
            tasks: $this->validateTasks($decoded['tasks'] ?? []),
            priority: $priority,
            priorityReason: $priority === null ? null : $this->validateReason($decoded['priority_reason'] ?? null),
            priorityGap: $gap,
            rejectedFields: $rejected,
            model: $model,
            promptTokens: (int) ($usageTokens['prompt_tokens'] ?? 0),
            completionTokens: (int) ($usageTokens['completion_tokens'] ?? 0),
            latencyMs: $latencyMs,
            usageLogIds: $usageLogIds,
            // The same per-model rate table the usage logs use; a second cost ladder here
            // is exactly the drift CLAUDE.md warns about.
            estimatedCost: (float) $this->usage->estimateCost($model, (int) ($usageTokens['prompt_tokens'] ?? 0), (int) ($usageTokens['completion_tokens'] ?? 0)),
        );

        // Only the create form can produce a ticket to hang the capture on.
        return $wantsPriority && $user !== null
            ? $this->issueToken($tenant, $user, $suggestion)
            : $suggestion;
    }

    /**
     * Record what the engineer actually submitted, against the ticket that landed.
     *
     * Called AFTER createTicket() succeeds, so `ai_ticket_analyses.ticket_id` and
     * `ai_suggestion_verdicts.ticket_id` stay NOT NULL and the whole P6a pipeline — the
     * hash comparison, `edit_ratio`, `minutes_saved`, the one-row-per-suggestion unique
     * index — applies unchanged.
     *
     * The verdicts here are DERIVED FROM WHAT LANDED on the ticket, not from a button the
     * engineer pressed. For this population "accepted" therefore means the application
     * landed; for the `manual`/`auto` populations it means an application was attempted.
     * The two are not the same metric — see the build notes.
     *
     * Returns null when there is nothing to record (no token, a stale or flushed cache
     * entry, or a token issued to another workspace or another user).
     */
    public function recordOutcome(Ticket $ticket, User $user, ?string $token): ?AiTicketAnalysis
    {
        if ($token === null || $token === '') {
            return null;
        }

        $key = $this->cacheKey((int) $ticket->tenant_id, (int) $user->getKey(), $token);
        $entry = Cache::get($key);

        // The token is validated as a KEY, never as an id: it is re-keyed on the session
        // tenant and the authenticated user, so a token issued elsewhere simply misses and
        // a tampered one finds nothing. There is no row to look it up in and nothing to
        // enumerate.
        if (! is_array($entry)) {
            return null;
        }

        Cache::forget($key);

        $suggestions = is_array($entry['suggestions'] ?? null) ? $entry['suggestions'] : [];
        $prefilled = is_array($entry['prefilled'] ?? null) ? $entry['prefilled'] : [];

        $analysis = AiTicketAnalysis::create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->getKey(),
            'requested_by' => $user->getKey(),
            'trigger' => AiTicketAnalysis::TRIGGER_DRAFT,
            // Null: the unique index on it belongs to the one-automatic-run-per-ticket
            // rule, which a draft is not.
            'auto_for_ticket_id' => null,
            'department_id' => $ticket->department_id,
            'status' => AiTicketAnalysis::STATUS_SUCCEEDED,
            'model' => (string) ($entry['model'] ?? ''),
            'prompt_tokens' => (int) ($entry['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($entry['completion_tokens'] ?? 0),
            'estimated_cost' => $entry['estimated_cost'] ?? 0,
            // The suggest-time figure, not submit-to-now: the row's created_at is submit
            // time, but the latency an engineer actually waited is exact.
            'latency_ms' => (int) ($entry['latency_ms'] ?? 0),
            'suggestions' => $suggestions,
            'context_ticket_ids' => [],
            'usage_log_ids' => is_array($entry['usage_log_ids'] ?? null) ? $entry['usage_log_ids'] : [],
            'rejected_fields' => is_array($entry['rejected_fields'] ?? null) ? $entry['rejected_fields'] : [],
        ]);

        $landed = [
            AiSuggestionVerdict::TYPE_PRIORITY => (string) $ticket->priority,
            AiSuggestionVerdict::TYPE_SUBJECT => (string) $ticket->subject,
            AiSuggestionVerdict::TYPE_DESCRIPTION => (string) $ticket->description,
        ];

        foreach ($landed as $type => $applied) {
            if ($analysis->suggestedValueFor($type) === null) {
                continue;
            }

            // Not pre-filled means the engineer was never offered it in the form, so an
            // equal value is a coincidence rather than an acceptance.
            $wasPrefilled = (bool) ($prefilled[$type] ?? false);

            AiSuggestionVerdict::record($analysis, $type, $applied, ! $wasPrefilled, (int) $user->getKey());
        }

        return $analysis;
    }

    /**
     * The priorities createTicket() would admit for this tenant/client, in PRIORITIES order.
     *
     * @return list<string>
     */
    public function admissiblePriorities(Tenant $tenant, ?int $clientId): array
    {
        return array_values(array_filter(
            AnalyzeTicketService::PRIORITIES,
            fn (string $priority) => $this->tickets->draftWouldAdmitPriority($tenant, $clientId, $priority),
        ));
    }

    /**
     * The gap, in words, between what the engineer can pick and what the workspace covers.
     *
     * Silently suggesting only `low` reads as a weak AI rather than as a missing SLA
     * policy, which is why the narrowing is always explained and never merely applied.
     *
     * @param  list<string>  $admissible
     * @return array{missing: list<string>, basis: string, message: string}|null
     */
    private function priorityGap(Tenant $tenant, ?int $clientId, array $admissible): ?array
    {
        $missing = array_values(array_diff(AnalyzeTicketService::PRIORITIES, $admissible));

        if ($missing === []) {
            return null;
        }

        if ($clientId === null) {
            // With no client the guard is evaluated against client_tier = null, i.e.
            // catch-all policies only — which on a per-tier workspace covers nothing.
            return [
                'missing' => $missing,
                'basis' => 'no-client',
                'message' => $admissible === []
                    ? __('Pick the client first — priority suggestions follow its SLA tier.')
                    : __('Pick the client first — without it only workspace-wide SLA policies apply.'),
            ];
        }

        $tier = $this->clientTierLabel($tenant, $clientId);

        return [
            'missing' => $missing,
            'basis' => 'tier:'.($tier ?? 'untiered'),
            'message' => __('No SLA policy covers :missing for this client — Settings → SLA.', [
                'missing' => $this->listWords($missing),
            ]),
        ];
    }

    private function clientTierLabel(Tenant $tenant, int $clientId): ?string
    {
        return Client::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->find($clientId)?->tier;
    }

    /**
     * @param  list<string>  $words
     */
    private function listWords(array $words): string
    {
        if (count($words) < 2) {
            return implode('', $words);
        }

        $last = array_pop($words);

        return implode(', ', $words).' '.__('or').' '.$last;
    }

    /**
     * @param  list<string>  $admissible
     */
    private function systemPrompt(Tenant $tenant, array $admissible, bool $wantsPriority): string
    {
        $prompt = AiAssistantService::draftSystemPrompt($tenant);

        if (! $wantsPriority) {
            return $prompt;
        }

        $prompt .= "\n\n".self::PRIORITY_INSTRUCTION;

        // An empty set means the workspace has no SLA policy this draft could satisfy, so
        // the only honest suggestion is none — the form keeps its own default and the gap
        // sentence explains why. Failing closed, as every candidate list in this feature does.
        $prompt .= "\n\nPRIORITY OPTIONS (choose one or null)\n"
            .($admissible === [] ? '(none available — return null)' : implode(', ', $admissible));

        return $prompt;
    }

    /**
     * @param  list<string>  $admissible
     * @param  list<string>  $rejected
     */
    private function validatePriority(mixed $priority, array $admissible, array &$rejected): ?string
    {
        if ($priority === null || $priority === '') {
            return null;
        }

        // Membership of the set THIS call issued, re-checked server-side: the model's word
        // for which priority is covered is not evidence, and a priority outside the set is
        // the one thing that would turn this button into a dead end at submit.
        if (! is_string($priority) || ! in_array(strtolower($priority), $admissible, true)) {
            $rejected[] = AiSuggestionVerdict::TYPE_PRIORITY;

            return null;
        }

        return strtolower($priority);
    }

    /**
     * Normalised exactly as structureTicketDraft() normalises them, so the create form's
     * `tasks[]` inputs cannot receive a shape they have not already seen.
     *
     * @return list<string>
     */
    private function validateTasks(mixed $tasks): array
    {
        $out = [];

        foreach ((array) $tasks as $task) {
            $task = trim((string) (is_array($task) ? ($task['title'] ?? $task['task'] ?? '') : $task));
            if ($task !== '') {
                $out[] = Str::limit($task, 250, '');
            }
        }

        return array_slice($out, 0, 8);
    }

    private function validateReason(mixed $reason): ?string
    {
        $reason = is_string($reason) ? trim($reason) : '';

        return $reason === '' ? null : Str::limit($reason, 200, '');
    }

    private function refuse(string $subject, string $description, string $message): DraftSuggestion
    {
        // No analysis row: there is no ticket to hang one on, and a refusal is not a
        // suggestion. The AiModerationLog the guardrail services write is the record.
        return new DraftSuggestion(subject: $subject, description: $description, refusal: $message);
    }

    /**
     * Park the payload the capture will need under an opaque server-generated token.
     *
     * The cache, not a hidden JSON field: a client that authors the suggestion authors the
     * accept-rate too. And the cache, not a `ticket_drafts` table: this payload is worth
     * nothing the moment the ticket exists, and a table would need a tenant column, a
     * scope, a pruning command and a retention answer to hold it for two minutes. The
     * cost of that choice is honest — a cache flush between polish and submit loses the
     * measurement row, and loses nothing else, which is why no ticket data goes through it.
     */
    private function issueToken(Tenant $tenant, User $user, DraftSuggestion $suggestion): DraftSuggestion
    {
        $token = Str::random(32);

        Cache::put(
            $this->cacheKey((int) $tenant->getKey(), (int) $user->getKey(), $token),
            [
                // The shape AiTicketAnalysis::suggestedValueFor() already reads.
                'suggestions' => [
                    'priority' => $suggestion->priority,
                    'polished_subject' => $suggestion->subject,
                    'polished_description' => $suggestion->description,
                ],
                // Which of them the partial actually wrote into the form. A suggestion the
                // engineer was never shown must not be able to record an acceptance.
                'prefilled' => [
                    AiSuggestionVerdict::TYPE_PRIORITY => $suggestion->priority !== null,
                    AiSuggestionVerdict::TYPE_SUBJECT => $suggestion->subject !== '',
                    AiSuggestionVerdict::TYPE_DESCRIPTION => $suggestion->description !== '',
                ],
                'rejected_fields' => $suggestion->rejectedFields,
                'model' => $suggestion->model,
                'prompt_tokens' => $suggestion->promptTokens,
                'completion_tokens' => $suggestion->completionTokens,
                'estimated_cost' => $suggestion->estimatedCost,
                'latency_ms' => $suggestion->latencyMs,
                'usage_log_ids' => $suggestion->usageLogIds,
            ],
            now()->addMinutes((int) config('ai.draft.token_ttl_minutes', 120)),
        );

        return new DraftSuggestion(
            subject: $suggestion->subject,
            description: $suggestion->description,
            tasks: $suggestion->tasks,
            priority: $suggestion->priority,
            priorityReason: $suggestion->priorityReason,
            priorityGap: $suggestion->priorityGap,
            rejectedFields: $suggestion->rejectedFields,
            draftToken: $token,
            model: $suggestion->model,
            promptTokens: $suggestion->promptTokens,
            completionTokens: $suggestion->completionTokens,
            latencyMs: $suggestion->latencyMs,
            usageLogIds: $suggestion->usageLogIds,
            estimatedCost: $suggestion->estimatedCost,
        );
    }

    private function cacheKey(int $tenantId, int $userId, string $token): string
    {
        return "ai:draft:{$tenantId}:{$userId}:{$token}";
    }
}
