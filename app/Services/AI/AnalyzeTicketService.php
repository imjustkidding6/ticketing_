<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Enums\PlanFeature;
use App\Exceptions\OpenAiException;
use App\Models\AiAgent;
use App\Models\AiSuggestionVerdict;
use App\Models\AiTicketAnalysis;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\AiAssistantService;
use App\Services\AiUsageTrackerService;
use App\Services\EmbeddingService;
use App\Services\EscalationService;
use App\Services\ModerationService;
use App\Services\OpenAiService;
use App\Services\PlanService;
use App\Services\PromptInjectionService;
use App\Support\Ai\AgentRecommendation;
use App\Support\Ai\TicketAnalysis;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * ANALYZE TICKET — the first OpenAI call in the department-AI-agents feature.
 *
 * Exactly two outbound calls per run, in this order:
 *
 *   1. one embedding, via EmbeddingService::searchSimilarResolvedTickets(), so the chat
 *      prompt carries real resolved tickets instead of the model guessing. (Not
 *      AiAssistantService::searchResolvedTickets(), which is private and needs a
 *      ChatConversation this stateless path does not have.)
 *   2. one strict-JSON chat completion returning every suggestion together.
 *
 * One chat call rather than one per suggestion: the ticket text, the category list and
 * the retrieved context dominate the prompt and are identical for every sub-question, so
 * five calls would pay for them five times for no quality gain. The trade accepted is a
 * single output ceiling and an all-or-nothing parse — hence `response_format:
 * json_object`, the item caps in the prompt, and an explicit `failed` status. A
 * truncated or unparseable reply renders NO suggestion; there is no partial render and
 * no retry loop.
 *
 * **Nothing here writes to `tickets`.** Every output is a suggestion. The engineer's
 * Apply goes through the existing ticket-update endpoint, so a priority change still
 * runs TicketService::guardSlaPolicy() and still re-runs SlaService::assignSla() — a
 * guard throwing inside an AI path would surface to the engineer as "the AI broke the
 * ticket".
 *
 * **No id from the model crosses a boundary.** Categories are offered as service-issued
 * refs (K1…Kn) and validated by identity against the issued map, never with `exists:`.
 * Anything the model invented lands in `rejected_fields` and is never rendered.
 *
 * The plan gate, the `ai_enabled` switch and the `ai_agents_enabled` opt-in belong to the
 * caller (see AiTicketAnalysisController and AnalyzePublicTicketJob) — AppSetting::get()
 * reads the session, and this service must not pretend to be able to.
 *
 * **Two triggers, one code path.** An engineer pressing Analyze and a customer submitting
 * a ticket on the public portal run exactly this service; the only differences are a null
 * `$user` and the recorded `trigger`. P6b added no second analysis path, deliberately —
 * the automatic run is the one with no human in the loop at call time, so it must sit
 * behind the same guardrail chain and the same validators, not beside them.
 */
class AnalyzeTicketService
{
    /**
     * The priority vocabulary, kept identical to UpdateTicketRequest's `in:` rule.
     *
     * @var list<string>
     */
    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    /**
     * Hard cap on proposed troubleshooting checks. The single output ceiling is shared
     * with the summary, so an unbounded list is how the reply gets truncated and the
     * whole analysis becomes `failed`.
     */
    public const MAX_CHECKS = 6;

    /**
     * The polish instruction, extracted rather than copied from
     * AiAssistantService::structureTicketDraft() — the same wording, proven on the create
     * form, plus the two sentences this surface needs: the author is a CUSTOMER, and the
     * original is kept.
     */
    public const POLISH_INSTRUCTION = 'Also rewrite the ticket into a clean, professional form. '
        .'Do NOT invent facts, names, numbers, or causes that are not stated or clearly implied. '
        .'"polished_subject" is a concise, specific one-line title (max ~80 chars, no trailing period). '
        .'"polished_description" is a clear, well-formatted description; when the text supports it, '
        .'organize it under short labelled sections such as "Issue", "Steps to Reproduce", "Expected", '
        .'"Actual" and "Environment". If the text is too vague for a field, keep the original wording '
        .'rather than fabricating. '
        .'The text was written by a customer, not a colleague. Preserve every fact, every error message '
        .'and every quantity exactly; fix only grammar, structure and spelling. '
        .'These are suggestions an engineer will review. The customer\'s original text is kept and is '
        .'not replaced by yours.';

    public function __construct(
        private readonly OpenAiService $openAi,
        private readonly EmbeddingService $embeddings,
        private readonly AgentSelectionService $agents,
        private readonly PromptInjectionService $injection,
        private readonly ModerationService $moderation,
        private readonly AiUsageTrackerService $usage,
        private readonly EscalationService $escalation,
        private readonly PlanService $plans,
    ) {}

    /**
     * Analyse one ticket and persist the run.
     *
     * Never throws for a refusal or a bad reply — those are statuses on the returned
     * DTO and rows in `ai_ticket_analyses`. An OpenAiException (including a tripped
     * circuit breaker, which OpenAiService::post() raises before any HTTP) propagates,
     * because "the provider is down" is a different answer from "we declined".
     *
     * `$user` is null for an automatic run: there is no requester, and `trigger` — not
     * `requested_by IS NULL` — is what separates the two populations.
     *
     * @throws OpenAiException
     */
    public function analyze(
        Ticket $ticket,
        ?User $user = null,
        string $trigger = AiTicketAnalysis::TRIGGER_MANUAL,
    ): TicketAnalysis {
        $sanitised = $this->sanitisedTicketText($ticket);

        // Guardrails first, and before any HTTP. On a publicly created ticket the
        // description was written by an unauthenticated stranger, so prompt injection is
        // the normal case on the surface triage pays most on — not a hypothetical. Both
        // services write their own AiModerationLog row, and both are passed the tenant
        // and the user so the row is attributable.
        if ($this->injection->isInjection($sanitised, $ticket->tenant_id, $user?->getKey())) {
            return $this->refuse($ticket, $user, AiTicketAnalysis::STATUS_REFUSED_INJECTION, $trigger);
        }

        if ($this->moderation->isFlagged($sanitised, 'balanced', $ticket->tenant_id, $user?->getKey())) {
            return $this->refuse($ticket, $user, AiTicketAnalysis::STATUS_REFUSED_MODERATION, $trigger);
        }

        // CircuitBreakerService is NOT invoked here: OpenAiService::post() already wraps
        // every endpoint, and a second ensureAvailable()/recordFailure() pair would
        // double-count a single outage.

        // Resolved ONCE, explicitly against the ticket's tenant, and passed down rather
        // than re-read from the relation in three places. A foreign department makes all
        // three candidate lists empty, so every suggestion that depends on the department
        // is omitted instead of answered from another workspace.
        $department = $this->departmentFor($ticket);

        $recommendations = $this->recommendationsFor($department, $sanitised);
        $categories = $this->categoryCandidates($ticket, $department);
        $engineers = $this->engineerCandidates($ticket, $department);

        $startedAt = microtime(true);

        /** @var array{context: array<int, array{ticket: Ticket, score: float}>, response: array<string, mixed>} $calls */
        $calls = $this->usage->withContext([
            'tenant_id' => $ticket->tenant_id,
            // Null on the automatic path, and correct: nobody asked for it.
            'user_id' => $user?->getKey(),
            // The chat call is the agent-copilot family, not the conversational channel.
            // The embedding call keeps its own `embed`, which post() derives from the
            // endpoint and applyContext() leaves alone.
            'feature' => 'copilot',
        ], function () use ($ticket, $sanitised, $categories, $engineers, $recommendations) {
            $context = $this->embeddings->searchSimilarResolvedTickets(
                $ticket->tenant_id,
                $sanitised,
                (int) config('ai.analyze.context_limit', 5),
            );

            $response = $this->openAi->chat(
                [
                    ['role' => 'system', 'content' => $this->systemPrompt($ticket, $recommendations)],
                    ['role' => 'user', 'content' => $this->userPrompt($sanitised, $categories, $engineers, $context)],
                ],
                [],
                [
                    'max_tokens' => (int) config('ai.analyze.max_tokens', 1200),
                    'temperature' => 0.2,
                    'response_format' => ['type' => 'json_object'],
                ],
            );

            return ['context' => $context, 'response' => $response];
        });

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
        $usageLogIds = $this->usage->lastLoggedIds();
        $contextTickets = $this->presentContext($calls['context']);

        return $this->persist(
            $ticket,
            $user,
            $calls['response'],
            $categories,
            $engineers,
            $recommendations,
            $contextTickets,
            $usageLogIds,
            $latencyMs,
            $trigger,
        );
    }

    /**
     * Has this ticket already had its one automatic analysis?
     *
     * The cheap guard that short-circuits before any OpenAI spend. It is NOT the
     * correctness guarantee — the unique index on `auto_for_ticket_id` is, because two
     * workers reaching this check in the same millisecond both pass it.
     */
    public function alreadyAutoAnalysed(Ticket $ticket): bool
    {
        return AiTicketAnalysis::withoutGlobalScopes()
            ->where('tenant_id', $ticket->tenant_id)
            ->where('auto_for_ticket_id', $ticket->getKey())
            ->exists();
    }

    /**
     * Subject + description + PUBLIC comments, nothing else.
     *
     * Internal notes are excluded from embeddings retrieval today and stay excluded
     * here; an agent's private note is the one thing on a ticket nobody agreed to send
     * to a third party. No client name, no client email, no author name and no id is
     * included — the privacy boundary is asserted by test, on the outbound body.
     */
    private function sanitisedTicketText(Ticket $ticket): string
    {
        $parts = [
            'Subject: '.(string) $ticket->subject,
            'Description: '.(string) $ticket->description,
        ];

        $comments = $ticket->comments()
            // `ticket_id` already pins these rows to a ticket this service resolved against
            // an explicit tenant, but TicketComment is a BelongsToTenant model and the scope
            // adds nothing in a worker, so the predicate is stated rather than assumed.
            ->where('tenant_id', $ticket->tenant_id)
            ->where('is_public', true)
            ->oldest()
            ->pluck('content')
            ->filter()
            ->all();

        if ($comments !== []) {
            $parts[] = "Public replies:\n- ".implode("\n- ", array_map('strval', $comments));
        }

        return mb_substr(implode("\n\n", $parts), 0, (int) config('ai.analyze.input_chars', 8000));
    }

    /**
     * The department's agents, flattened into DTOs immediately.
     *
     * `auto_invoke` is honoured as "whose expertise shapes this analysis without the
     * engineer picking", not as "run unasked" — the engineer still presses Analyze.
     * Falls back to the lexical recommendation when no agent is marked auto-invoke.
     *
     * No AiAgent leaves this method: the two pseudo-attributes P5 writes onto those
     * models are not columns, and the model has LogsActivity.
     *
     * @return list<AgentRecommendation>
     */
    private function recommendationsFor(?Department $department, string $text): array
    {
        if ($department === null) {
            return [];
        }

        /** @var Collection<int, AiAgent> $agents */
        $agents = $this->agents->getAutoInvokeAgents($department);

        if ($agents->isEmpty()) {
            $agents = $this->agents->recommendAgents($department, $text, 3);
        }

        return $agents->map(fn (AiAgent $agent) => AgentRecommendation::fromModel($agent))->values()->all();
    }

    /**
     * The ticket's department, resolved against the TICKET's tenant explicitly.
     *
     * `$ticket->department` goes through `Department`'s global `TenantScope`, and
     * `TenantScope::getCurrentTenantId()` returns null — applying NO predicate — whenever
     * `auth()->check()` is false. In the queued automatic run there is no authenticated
     * user, so the relation would resolve a department belonging to ANOTHER workspace
     * whenever `tickets.department_id` held a foreign id. That matters beyond the
     * department row itself: `AgentSelectionService::assignedAgents()` pins the AI agents
     * to `$department->tenant_id`, so a foreign department would put another paying
     * workspace's agent names and system prompts into this tenant's prompt and onto its
     * stored analysis. Setting `session('current_tenant_id')` does NOT fix this — the
     * session is not what the scope reads.
     *
     * Fails closed: a department that is not this tenant's is treated as no department at
     * all, which omits the category suggestion, the engineer suggestion and the agent
     * instructions rather than answering from the wrong workspace.
     */
    private function departmentFor(Ticket $ticket): ?Department
    {
        if ($ticket->department_id === null) {
            return null;
        }

        return Department::withoutGlobalScopes()
            ->where('tenant_id', $ticket->tenant_id)
            ->find($ticket->department_id);
    }

    /**
     * True when the ticket's department really belongs to the ticket's workspace.
     *
     * Public so the queued job can refuse the whole run before spending anything: once
     * the portal's tenant-scoped validation rules are in place this state is impossible,
     * and a ticket that still reaches it is not a ticket to analyse.
     */
    public function departmentIsOwnedByTicketTenant(Ticket $ticket): bool
    {
        return $ticket->department_id === null || $this->departmentFor($ticket) !== null;
    }

    /**
     * The categories the model may choose from, keyed by a service-issued ref.
     *
     * Scoped to the tenant EXPLICITLY and to the ticket's own department — the
     * category/department pairing the portal fix closed. A ticket with no department, or
     * one whose department belongs to another workspace, gets an EMPTY list and no
     * category suggestion at all, rather than being offered another workspace's
     * categories; widening is how a suggestion becomes a leak.
     *
     * @return array<string, TicketCategory>
     */
    private function categoryCandidates(Ticket $ticket, ?Department $department): array
    {
        if ($department === null) {
            return [];
        }

        $candidates = [];
        $index = 1;

        foreach (
            TicketCategory::query()
                ->active()
                // `tenant_id` EXPLICITLY. The department predicate only contains the
                // tenant while `tickets.department_id` is known to be this workspace's,
                // and TenantScope adds nothing in a session-less worker — so without this
                // a forged department id offered another workspace's category names to
                // the model and stored them on this tenant's analysis.
                ->where('tenant_id', $ticket->tenant_id)
                ->where('department_id', $department->getKey())
                ->orderBy('name')
                ->get(['id', 'name', 'department_id', 'tenant_id']) as $category
        ) {
            $candidates['K'.$index++] = $category;
        }

        return $candidates;
    }

    /**
     * The engineers the model may choose from, keyed by a service-issued ref.
     *
     * THE highest-risk query in this feature: `User` carries no global scope, so the
     * `tenants` pivot predicate below — pinned to the TICKET's tenant, never
     * `session('current_tenant_id')` — is the only thing separating workspaces. The
     * session is the wrong source here: this service is answering for the ticket it was
     * handed, and a resolver that infers the workspace is the `TenantTime::timezone()`
     * mistake.
     *
     * **Department membership fails closed.** A ticket with no department, a department
     * belonging to another workspace, or a department with no members, yields an EMPTY list and suggestion 7 is omitted
     * entirely. Widening to the workspace would turn a triage suggestion into a staff
     * directory, which is precisely what an AI panel must not become.
     *
     * Tier narrowing uses EscalationService's ladder (moved there from
     * EscalationController for this) and only applies when the Enterprise
     * `agent_escalation` feature is on — without it `support_tier` is meaningless data.
     *
     * @return array<string, User>
     */
    private function engineerCandidates(Ticket $ticket, ?Department $department): array
    {
        if ($department === null) {
            return [];
        }

        $members = User::query()
            // User is NOT auto-scoped. Explicit, and on the ticket's tenant.
            ->whereHas('tenants', fn ($q) => $q->where('tenant_id', $ticket->tenant_id))
            ->whereHas('departments', fn ($q) => $q->where('departments.id', $department->getKey()))
            ->where('is_available', true)
            // `tickets.tenant_id` EXPLICITLY. TenantScope applies no predicate at all when
            // auth()->check() is false, so relying on it was correct only while every
            // caller was a signed-in agent — in the queued automatic run a candidate who
            // belongs to two workspaces would have their open tickets counted across both,
            // and that cross-tenant figure would go into the prompt.
            ->withCount(['tickets' => fn ($q) => $q
                ->where('tickets.tenant_id', $ticket->tenant_id)
                ->whereIn('status', Ticket::OPEN_STATUSES)])
            ->orderBy('name')
            ->get(['id', 'name', 'support_tier', 'is_available']);

        $tier = $ticket->current_tier;

        // Only an ESCALATED ticket narrows by tier. `current_tier` defaults to tier_1 for
        // every ticket and `support_tier` is null for every member who was never tiered,
        // so comparing at the base tier would drop the entire department and omit the
        // suggestion in most workspaces — an untiered member is a tier-1 agent.
        if ($tier !== null
            && $this->escalation->tierRank($tier) > 1
            && $this->plans->tenantHasFeature($ticket->tenant, PlanFeature::AgentEscalation)
        ) {
            $members = $members->filter(
                fn (User $member) => $this->escalation->canHandleTier($member, $tier, $ticket->tenant)
            );
        }

        $candidates = [];
        $index = 1;

        foreach ($members as $member) {
            $candidates['C'.$index++] = $member;
        }

        return $candidates;
    }

    /**
     * @param  list<AgentRecommendation>  $recommendations
     */
    private function systemPrompt(Ticket $ticket, array $recommendations): string
    {
        $lines = [
            'You are the triage assistant for '.$ticket->tenant->displayName().'.',
            AiAssistantService::SUMMARY_INSTRUCTION,
            'You are advising a human support engineer. Every output is a SUGGESTION that a '
                .'human will review before anything changes. You never apply a change.',
            'NEVER invent an id, a reference, a name or an email address. Choose only from the '
                .'references supplied in the user message, or return null for that field.',
            'Do not restate the ticket. Do not address the customer.',
        ];

        if ($recommendations !== []) {
            // The department's own expertise, which is what makes this a *department* AI
            // agent feature rather than a generic copilot.
            $lines[] = 'The following specialist instructions come from the AI agents this '
                .'department has configured. Apply them within your analysis:';

            foreach ($recommendations as $recommendation) {
                $skills = $recommendation->matchedSkills === []
                    ? ''
                    : ' (relevant skills: '.implode(', ', $recommendation->matchedSkills).')';

                $lines[] = '- '.$recommendation->name
                    .($recommendation->roleType === null ? '' : ' ['.$recommendation->roleType.']')
                    .$skills.': '.(string) $recommendation->systemPrompt;
            }
        }

        $lines[] = 'Reply with a single JSON object and nothing else, with exactly these keys:'
            ."\n".'{"summary": "string, at most 1200 characters", '
            .'"polished_subject": "string, at most 80 characters, or null", '
            .'"polished_description": "string, structured, or null", '
            .'"category_ref": "one of the supplied category references, or null", '
            .'"priority": "one of low|medium|high|critical, or null", '
            .'"checks": ["an imperative troubleshooting step, 2 to '.self::MAX_CHECKS.' items"], '
            .'"engineer_ref": "one of the supplied engineer references, or null", '
            .'"engineer_reason": "string, at most 200 characters, or null"}';

        $lines[] = self::POLISH_INSTRUCTION;

        $lines[] = 'The checks are a proposed troubleshooting checklist for the engineer: '
            .'imperative phrases, one action each, ordered cheapest-to-verify first. Do not '
            .'number them and do not address the customer.';

        // The refs are deliberately opaque: the engineer candidates carry no name, no
        // email and no id, so "pick the best person" cannot be answered by recognising a
        // name and an invented ref resolves to nobody.
        $lines[] = 'The engineer references identify nobody: judge them only on the tier and '
            .'the open-ticket load given. Prefer a lighter load at a sufficient tier. The '
            .'reason must not claim to know who the person is.';

        return implode("\n\n", $lines);
    }

    /**
     * @param  array<string, TicketCategory>  $categories
     * @param  array<string, User>  $engineers
     * @param  array<int, array{ticket: Ticket, score: float}>  $context
     */
    private function userPrompt(string $sanitised, array $categories, array $engineers, array $context): string
    {
        $sections = ["TICKET\n".$sanitised];

        $sections[] = "CATEGORY OPTIONS (choose one reference or null)\n".($categories === []
            ? '(none available — return null)'
            : implode("\n", array_map(
                fn (string $ref, TicketCategory $category) => $ref.': '.$category->name,
                array_keys($categories),
                array_values($categories),
            )));

        $sections[] = 'PRIORITY OPTIONS (choose one or null)'."\n".implode(', ', self::PRIORITIES);

        // JSON, so the shape of what leaves this application is readable in one glance
        // and assertable in one string: a ref, a tier, a count. Nothing else.
        $sections[] = "ENGINEER OPTIONS (choose one reference or null)\n".($engineers === []
            ? '(none available — return null)'
            : implode("\n", array_map(
                fn (string $ref, User $member) => (string) json_encode([
                    'ref' => $ref,
                    'tier' => $member->support_tier,
                    'open_tickets' => (int) ($member->tickets_count ?? 0),
                ]),
                array_keys($engineers),
                array_values($engineers),
            )));

        $sections[] = "SIMILAR RESOLVED TICKETS (for your reasoning; do not quote references)\n"
            .($context === []
                ? '(none found)'
                : implode("\n", array_map(
                    fn (array $row) => '- '.Str::limit((string) $row['ticket']->subject, 160, '')
                        .': '.Str::limit((string) $row['ticket']->closing_remarks ?: (string) $row['ticket']->description, 400, ''),
                    $context,
                )));

        return implode("\n\n", $sections);
    }

    /**
     * @param  array<int, array{ticket: Ticket, score: float}>  $context
     * @return list<array{id: int, ticket_number: string, subject: string, score: float}>
     */
    private function presentContext(array $context): array
    {
        return array_values(array_map(fn (array $row) => [
            'id' => (int) $row['ticket']->getKey(),
            'ticket_number' => (string) $row['ticket']->ticket_number,
            'subject' => (string) $row['ticket']->subject,
            'score' => (float) $row['score'],
        ], $context));
    }

    /**
     * Validate the reply and write the row.
     *
     * @param  array<string, mixed>  $response
     * @param  array<string, TicketCategory>  $categories
     * @param  array<string, User>  $engineers
     * @param  list<AgentRecommendation>  $recommendations
     * @param  list<array{id: int, ticket_number: string, subject: string, score: float}>  $contextTickets
     * @param  list<int>  $usageLogIds
     * @param  int  $latencyMs  Wall-clock time for both calls together, which is what an
     *                          engineer waited; the per-call figures are in ai_usage_logs.
     */
    private function persist(
        Ticket $ticket,
        ?User $user,
        array $response,
        array $categories,
        array $engineers,
        array $recommendations,
        array $contextTickets,
        array $usageLogIds,
        int $latencyMs,
        string $trigger,
    ): TicketAnalysis {
        $usage = $response['usage'] ?? [];
        $base = [
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->getKey(),
            'requested_by' => $user?->getKey(),
            'trigger' => $trigger,
            // Written only on the automatic path; NULL leaves manual runs unconstrained.
            'auto_for_ticket_id' => $trigger === AiTicketAnalysis::TRIGGER_AUTO ? $ticket->getKey() : null,
            'department_id' => $ticket->department_id,
            'model' => (string) ($response['model'] ?? config('services.openai.model', 'gpt-5')),
            'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
            'latency_ms' => $latencyMs,
            'agent_recommendations' => array_map(fn (AgentRecommendation $r) => $r->toArray(), $recommendations),
            'context_ticket_ids' => array_column($contextTickets, 'id'),
            'usage_log_ids' => $usageLogIds,
        ];
        // The same per-model rate table the usage logs use. A second cost ladder here
        // is exactly the drift CLAUDE.md warns about for Ticket::STATUSES.
        $base['estimated_cost'] = $this->usage->estimateCost($base['model'], $base['prompt_tokens'], $base['completion_tokens']);

        $content = $response['choices'][0]['message']['content'] ?? null;
        $decoded = is_string($content) ? json_decode($content, true) : null;

        if (! is_array($decoded)) {
            // Unparseable or truncated. A reasoning model whose hidden tokens ate the
            // output budget lands here, and the honest answer is "nothing", not half a
            // panel the engineer cannot tell is half.
            $analysis = $this->createAnalysis($ticket, $base + [
                'status' => AiTicketAnalysis::STATUS_FAILED,
                'suggestions' => null,
                'rejected_fields' => [],
                'error_message' => 'The model did not return parseable JSON.',
            ]);

            return new TicketAnalysis(
                status: AiTicketAnalysis::STATUS_FAILED,
                analysisId: (int) $analysis->getKey(),
                contextTickets: $contextTickets,
                agentRecommendations: $recommendations,
                errorMessage: 'The AI returned a response we could not read.',
                trigger: $trigger,
            );
        }

        $rejected = [];

        $summary = $this->validateSummary($decoded['summary'] ?? null);
        $category = $this->validateCategory($decoded['category_ref'] ?? null, $categories, $ticket, $rejected);
        $priority = $this->validatePriority($decoded['priority'] ?? null, $rejected);
        $checks = $this->validateChecks($decoded['checks'] ?? null, $rejected);
        $engineer = $this->validateEngineer($decoded['engineer_ref'] ?? null, $engineers, $ticket, $rejected);
        $engineerReason = $engineer === null ? null : $this->validateEngineerReason($decoded['engineer_reason'] ?? null);
        $polishedSubject = $this->validatePolishedText(
            $decoded['polished_subject'] ?? null,
            (string) $ticket->subject,
            (int) config('ai.analyze.polished_subject_chars', 255),
            AiSuggestionVerdict::TYPE_SUBJECT,
            $rejected,
        );
        $polishedDescription = $this->validatePolishedText(
            $decoded['polished_description'] ?? null,
            (string) $ticket->description,
            (int) config('ai.analyze.polished_description_chars', 8000),
            AiSuggestionVerdict::TYPE_DESCRIPTION,
            $rejected,
        );

        $suggestions = [
            'summary' => $summary,
            'polished_subject' => $polishedSubject,
            'polished_description' => $polishedDescription,
            'category' => $category === null ? null : ['id' => (int) $category->getKey(), 'name' => (string) $category->name],
            'priority' => $priority,
            'checks' => $checks === [] ? null : $checks,
            'engineer' => $engineer === null ? null : [
                'id' => (int) $engineer->getKey(),
                'name' => (string) $engineer->name,
                'reason' => $engineerReason,
            ],
        ];

        $analysis = $this->createAnalysis($ticket, $base + [
            'status' => AiTicketAnalysis::STATUS_SUCCEEDED,
            'suggestions' => $suggestions,
            'rejected_fields' => $rejected,
        ]);

        return new TicketAnalysis(
            status: AiTicketAnalysis::STATUS_SUCCEEDED,
            analysisId: (int) $analysis->getKey(),
            summary: $summary,
            categoryId: $category === null ? null : (int) $category->getKey(),
            categoryName: $category === null ? null : (string) $category->name,
            priority: $priority,
            contextTickets: $contextTickets,
            rejectedFields: $rejected,
            agentRecommendations: $recommendations,
            checks: $checks,
            engineerId: $engineer === null ? null : (int) $engineer->getKey(),
            engineerName: $engineer === null ? null : (string) $engineer->name,
            engineerReason: $engineerReason,
            engineerCandidates: $this->presentCandidates($engineers),
            polishedSubject: $polishedSubject,
            polishedDescription: $polishedDescription,
            trigger: $trigger,
        );
    }

    /**
     * Write the row, surviving the automatic path's unique-index race — and ONLY that.
     *
     * Two workers reaching alreadyAutoAnalysed() in the same millisecond both pass it;
     * `ai_analyses_auto_for_ticket_unique` is what actually stops the second row. A race
     * is not an error the engineer should see, so the existing analysis is returned
     * instead of failing the job.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createAnalysis(Ticket $ticket, array $attributes): AiTicketAnalysis
    {
        try {
            return AiTicketAnalysis::create($attributes);
        } catch (UniqueConstraintViolationException $e) {
            // The AUTO path only. Recovering on the manual path would hand an engineer's
            // fresh Analyze the ticket's old automatic analysis whenever their insert failed
            // for an unrelated reason (a deadlock, a lock-wait timeout, a bad FK), and the
            // verdict they then posted would be written under the AUTO parent — mixing the
            // two populations `trigger` exists to keep apart.
            if (($attributes['trigger'] ?? null) !== AiTicketAnalysis::TRIGGER_AUTO) {
                throw $e;
            }

            $existing = AiTicketAnalysis::withoutGlobalScopes()
                ->where('tenant_id', $ticket->tenant_id)
                ->where('auto_for_ticket_id', $ticket->getKey())
                ->first();

            if ($existing === null) {
                throw $e;
            }

            report($e);

            return $existing;
        }
    }

    /**
     * One validator for both polished fields — the same rules, different ceilings.
     *
     * The cap is hard, not advisory: the polished subject is bounded by the WIDTH of
     * `tickets.subject` and the description by `applied_value`'s 8,000, so a long
     * suggestion is truncated here rather than 422-ing the apply after the verdict row
     * has already been written.
     *
     * A value byte-identical to the original is OMITTED, not offered: nothing was
     * improved, and an Apply that changes nothing would bank minutes_saved for no work.
     *
     * @param  list<string>  $rejected
     */
    private function validatePolishedText(
        mixed $value,
        string $original,
        int $limit,
        string $type,
        array &$rejected,
    ): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            $rejected[] = $type;

            return null;
        }

        $clean = trim((string) preg_replace('/[ \t]+/u', ' ', $value));

        if ($clean === '') {
            return null;
        }

        $clean = Str::limit($clean, $limit, '');

        return $clean === trim($original) ? null : $clean;
    }

    private function validateSummary(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $summary = trim((string) preg_replace('/[ \t]+/u', ' ', $value));

        return $summary === '' ? null : Str::limit($summary, (int) config('ai.analyze.summary_chars', 1200), '');
    }

    /**
     * Identity against the issued map — not `exists:`, because no model-supplied id ever
     * reaches the database. The resolved row is re-checked against the tenant and the
     * department before it is returned, so a ref that somehow resolved to the wrong row
     * still cannot be offered.
     *
     * @param  array<string, TicketCategory>  $categories
     * @param  list<string>  $rejected
     */
    private function validateCategory(mixed $ref, array $categories, Ticket $ticket, array &$rejected): ?TicketCategory
    {
        if ($ref === null || $ref === '') {
            return null;
        }

        if (! is_string($ref) || ! array_key_exists($ref, $categories)) {
            $rejected[] = AiSuggestionVerdict::TYPE_CATEGORY;

            return null;
        }

        $category = $categories[$ref];

        if ((int) $category->tenant_id !== (int) $ticket->tenant_id
            || (int) $category->department_id !== (int) $ticket->department_id
        ) {
            $rejected[] = AiSuggestionVerdict::TYPE_CATEGORY;

            return null;
        }

        return $category;
    }

    /**
     * @param  list<string>  $rejected
     */
    private function validatePriority(mixed $priority, array &$rejected): ?string
    {
        if ($priority === null || $priority === '') {
            return null;
        }

        if (! is_string($priority) || ! in_array(strtolower($priority), self::PRIORITIES, true)) {
            $rejected[] = AiSuggestionVerdict::TYPE_PRIORITY;

            return null;
        }

        return strtolower($priority);
    }

    /**
     * Normalised the same way polishTaskList() normalises a proposed checklist, so
     * applying these through `tickets.tasks.polish-apply` cannot produce a shape that
     * endpoint has not already seen.
     *
     * A non-list reply is a rejection; individual empty items are simply dropped.
     *
     * @param  list<string>  $rejected
     * @return list<string>
     */
    private function validateChecks(mixed $checks, array &$rejected): array
    {
        if ($checks === null) {
            return [];
        }

        if (! is_array($checks)) {
            $rejected[] = AiSuggestionVerdict::TYPE_CHECKS;

            return [];
        }

        $clean = [];

        foreach ($checks as $check) {
            if (! is_scalar($check)) {
                continue;
            }

            $text = trim((string) preg_replace('/\s+/u', ' ', (string) $check));
            $text = trim($text, " \t\"'");
            $text = rtrim($text, '.');

            if ($text !== '') {
                $clean[] = Str::limit($text, 250, '');
            }
        }

        return array_slice(array_values(array_unique($clean)), 0, self::MAX_CHECKS);
    }

    /**
     * Identity against the refs this call issued — the same rule as the category, and
     * for a sharper reason: an id arriving from the model and trusted here would be a
     * cross-tenant user lookup. There is no `exists:` anywhere in this path.
     *
     * The resolved user is re-run through the candidate predicates (tenant membership,
     * department membership, availability) before being returned, so a ref that somehow
     * resolved to the wrong row still cannot be suggested.
     *
     * @param  array<string, User>  $engineers
     * @param  list<string>  $rejected
     */
    private function validateEngineer(mixed $ref, array $engineers, Ticket $ticket, array &$rejected): ?User
    {
        if ($ref === null || $ref === '') {
            return null;
        }

        if (! is_string($ref) || ! array_key_exists($ref, $engineers)) {
            $rejected[] = AiSuggestionVerdict::TYPE_ENGINEER;

            return null;
        }

        $member = $engineers[$ref];

        $stillEligible = $ticket->department_id !== null
            && (bool) $member->is_available
            && $member->tenants()->where('tenant_id', $ticket->tenant_id)->exists()
            && $member->departments()->where('departments.id', $ticket->department_id)->exists();

        if (! $stillEligible) {
            $rejected[] = AiSuggestionVerdict::TYPE_ENGINEER;

            return null;
        }

        return $member;
    }

    private function validateEngineerReason(mixed $reason): ?string
    {
        if (! is_string($reason)) {
            return null;
        }

        $clean = trim((string) preg_replace('/\s+/u', ' ', $reason));

        return $clean === '' ? null : Str::limit($clean, 200, '');
    }

    /**
     * The candidate set, for the panel's "Edit & apply" picker only.
     *
     * This is NOT what goes to OpenAI — that carries a ref, a tier and a count. The
     * names here go to the engineer's own browser, which `TicketController::show()`
     * already hands the whole tenant roster, so this is a narrower set than the page
     * already holds; it is still tenant- and department-scoped at the query, and the
     * JSON body is asserted on.
     *
     * @param  array<string, User>  $engineers
     * @return list<array{id: int, name: string, tier: string|null}>
     */
    private function presentCandidates(array $engineers): array
    {
        return array_values(array_map(fn (User $member) => [
            'id' => (int) $member->getKey(),
            'name' => (string) $member->name,
            'tier' => $member->support_tier === null ? null : (string) $member->support_tier,
        ], array_values($engineers)));
    }

    /**
     * A refusal is a row, not an exception: a workspace being refused repeatedly is a
     * thing to see in the data next to the AiModerationLog that explains it.
     */
    private function refuse(Ticket $ticket, ?User $user, string $status, string $trigger): TicketAnalysis
    {
        $analysis = $this->createAnalysis($ticket, [
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->getKey(),
            'requested_by' => $user?->getKey(),
            'trigger' => $trigger,
            'auto_for_ticket_id' => $trigger === AiTicketAnalysis::TRIGGER_AUTO ? $ticket->getKey() : null,
            'department_id' => $ticket->department_id,
            'status' => $status,
            'error_message' => $status === AiTicketAnalysis::STATUS_REFUSED_INJECTION
                ? 'Refused: the ticket text matched a prompt-injection pattern.'
                : 'Refused: the ticket text was flagged by content moderation.',
        ]);

        return new TicketAnalysis(
            status: $status,
            analysisId: (int) $analysis->getKey(),
            errorMessage: 'This ticket\'s text cannot be sent for analysis.',
            trigger: $trigger,
        );
    }
}
