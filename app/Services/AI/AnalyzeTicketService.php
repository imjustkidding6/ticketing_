<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Enums\PlanFeature;
use App\Exceptions\OpenAiException;
use App\Models\AgentSchedule;
use App\Models\AiAgent;
use App\Models\AiSuggestionVerdict;
use App\Models\AiTicketAnalysis;
use App\Models\Department;
use App\Models\Tenant;
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
use App\Support\TenantTime;
use App\Support\TicketTechnicalFields;
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
     * The ONE priority rubric. The thresholds, not the adjectives.
     *
     * Why a constant and not workspace data: `sla_policies` holds COMMITMENTS (hours), and
     * no column anywhere holds CRITERIA. The hours are read live and interleaved by
     * priorityRubric(); only the criteria are fixed here, so this constant is not a second
     * source of truth about anything the database knows.
     *
     * Every entry is written as a set of NECESSARY conditions ("ONLY IF"), never as an
     * invitation, so the rubric and the draft surface's "Do not inflate it." push the same
     * way rather than arguing with each other.
     *
     * @var array<string, string> Keyed by priority; only ADMISSIBLE keys are ever emitted.
     */
    public const PRIORITY_CRITERIA = [
        'critical' => 'ONLY IF a production service is down or unusable AND there is no workaround, AND the '
            .'impact is an entire site, department or customer base rather than one person; OR a '
            .'security breach, credential compromise or data loss is stated to be in progress; OR a '
            .'stated external or contractual deadline falls inside the response window below.',
        'high' => 'ONLY IF a team or a business-critical function is significantly impaired, OR one person '
            .'is completely blocked from production work with no workaround, OR a stated deadline '
            .'falls inside the resolution window below. A severe fault that HAS a workaround belongs '
            .'here, not above.',
        'medium' => 'The default for a real fault with a workaround, a non-production or test environment, a '
            .'single person partially impeded, or a request with no stated deadline.',
        'low' => 'A question, a cosmetic or documentation issue, a request for information, or anything the '
            .'reporter describes as not urgent or as needed at some future date.',
    ];

    /**
     * The one sentence that makes the critical/high boundary a decision rule rather than a
     * list of adjectives. Emitted only when BOTH levels are on offer — describing a
     * boundary one side of which cannot be suggested is the suggest-then-refuse defect in
     * miniature.
     */
    public const PRIORITY_BOUNDARY = 'critical and high are separated by exactly two things: BREADTH of impact and the EXISTENCE OF '
        .'A WORKAROUND. Many people or a whole service affected AND no workaround is critical; anything '
        .'narrower, or anything with a workaround however painful, is at most high.';

    /**
     * How uncertainty resolves — DOWNWARD, always, and without naming a level the
     * workspace does not offer.
     *
     * This is what reconciles the rubric with "Do not inflate it.": that instruction was
     * advice about uncertainty with no procedure attached, and the model had to invent
     * one. Here is the procedure.
     */
    public const PRIORITY_UNCERTAINTY = 'If the notes do not clearly establish a level\'s conditions, do not choose that level. Choose '
        .'the HIGHEST LISTED level whose conditions the notes DO clearly establish; if none are '
        .'established, return null. An environment the notes do not name is NOT production. A number '
        .'of affected people the notes do not state is ONE person. Absence of evidence is not evidence '
        .'of severity.';

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

        // An ANNOTATION on the candidates already chosen, never a filter on them: see
        // availabilityFor(). Null here means the signal is not sent at all.
        $availability = $this->availabilityFor($ticket->tenant, $engineers);

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
        ], function () use ($ticket, $sanitised, $categories, $engineers, $recommendations, $availability) {
            $context = $this->embeddings->searchSimilarResolvedTickets(
                $ticket->tenant_id,
                $sanitised,
                (int) config('ai.analyze.context_limit', 5),
            );

            $response = $this->openAi->chat(
                [
                    ['role' => 'system', 'content' => $this->systemPrompt($ticket, $recommendations, $availability !== null)],
                    ['role' => 'user', 'content' => $this->userPrompt($sanitised, $categories, $engineers, $context, $availability, TicketTechnicalFields::factBlock($ticket))],
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
            $department,
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
     *
     * Public since A2-1 — visibility only, no body change. `ResolutionWriterService`
     * sends the same ticket context beside the engineer's notes, and a second copy of
     * this builder is how the `is_public` and `tenant_id` predicates drift apart.
     */
    public function sanitisedTicketText(Ticket $ticket): string
    {
        $parts = [
            'Subject: '.(string) $ticket->subject,
            'Description: '.(string) $ticket->description,
        ];

        // The declared free-text technical details, HERE rather than in a block of their
        // own: this string is what PromptInjectionService and ModerationService are handed,
        // what the input-character budget applies to, and what the embedding is built from.
        // Appending them anywhere else would send stranger-authored prose to OpenAI around
        // the guardrails instead of through them.
        $parts = array_merge($parts, TicketTechnicalFields::freeTextParts($ticket));

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
     * Public since A2-1 — visibility only, no body change. The resolution writer ranks
     * the same agents against the engineer's NOTES rather than the ticket description.
     *
     * @return list<AgentRecommendation>
     */
    public function recommendationsFor(?Department $department, string $text): array
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
        return $this->departmentOwnedBy($ticket->tenant, $ticket->department_id);
    }

    /**
     * The ONE department resolver — verify an id against an EXPLICIT workspace.
     *
     * The body above moved here unchanged so the create-ticket draft, which has a
     * department id straight from the browser and no ticket at all, gets byte-for-byte
     * the same treatment as a ticket's own column: a foreign or missing id is `null`,
     * which every candidate list then reads as "no department" and fails closed on. A
     * second copy of this read is the mistake that got `panelCandidates()` deleted.
     */
    public function departmentOwnedBy(Tenant $tenant, ?int $departmentId): ?Department
    {
        if ($departmentId === null) {
            return null;
        }

        return Department::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->find($departmentId);
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
        return $this->candidatesFor($ticket->tenant, $department, $ticket->current_tier);
    }

    /**
     * The candidate set for an EXPLICIT workspace, department and (optional) tier.
     *
     * The moved body of engineerCandidates(), which is now a one-line delegate: every
     * `$ticket->tenant_id` became `$tenant->getKey()` and `$ticket->current_tier` became
     * the `$currentTier` parameter, and not one predicate changed. The create-ticket
     * draft has no ticket to key this on, and a second copy of a staff-roster query is
     * the one duplication this feature has already paid for once.
     *
     * `$currentTier` is null for a draft — a ticket that does not exist has no tier — so
     * the tier condition below is false and the list is never narrowed. That is the
     * documented behaviour for an unescalated ticket, applied rather than special-cased.
     *
     * @return array<string, User> The C1…Cn ref map.
     */
    public function candidatesFor(Tenant $tenant, ?Department $department, ?string $currentTier = null): array
    {
        if ($department === null) {
            return [];
        }

        $members = User::query()
            // User is NOT auto-scoped. Explicit, and on the workspace we were handed —
            // never one read back out of the session inside this service.
            ->whereHas('tenants', fn ($q) => $q->where('tenant_id', $tenant->getKey()))
            ->whereHas('departments', fn ($q) => $q->where('departments.id', $department->getKey()))
            ->where('is_available', true)
            // `tickets.tenant_id` EXPLICITLY. TenantScope applies no predicate at all when
            // auth()->check() is false, so relying on it was correct only while every
            // caller was a signed-in agent — in the queued automatic run a candidate who
            // belongs to two workspaces would have their open tickets counted across both,
            // and that cross-tenant figure would go into the prompt.
            ->withCount(['tickets' => fn ($q) => $q
                ->where('tickets.tenant_id', $tenant->getKey())
                ->whereIn('status', Ticket::OPEN_STATUSES)])
            ->orderBy('name')
            ->get(['id', 'name', 'support_tier', 'is_available']);

        $tier = $currentTier;

        // Only an ESCALATED ticket narrows by tier. `current_tier` defaults to tier_1 for
        // every ticket and `support_tier` is null for every member who was never tiered,
        // so comparing at the base tier would drop the entire department and omit the
        // suggestion in most workspaces — an untiered member is a tier-1 agent.
        if ($tier !== null
            && $this->escalation->tierRank($tier) > 1
            && $this->plans->tenantHasFeature($tenant, PlanFeature::AgentEscalation)
        ) {
            $members = $members->filter(
                fn (User $member) => $this->escalation->canHandleTier($member, $tier, $tenant)
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
     * Is each issued candidate on shift right now, by the WORKSPACE's clock?
     *
     * A tri-state per candidate — true (on shift), false (has a schedule, not on shift
     * now), null (unknown: no applicable rows) — and a null return for the whole map,
     * which omits the `available` key from the prompt altogether. Three degradation
     * levels, in order, each falling back to the signals the suggestion already had
     * rather than suggesting nobody:
     *
     *   1. the workspace does not hold the `agent_schedule` feature — the rows are then
     *      meaningless data rather than absent data, so the signal is not sent;
     *   2. the feature is on but NO candidate has any schedule row — a uniformly-unknown
     *      column invites the model to reason about it, so the prompt stays byte-identical
     *      to the shape it had before this slice;
     *   3. some candidates are scheduled — the key is sent for every candidate, `null` for
     *      the unscheduled ones.
     *
     * **This never filters the candidate list.** It annotates the list engineerCandidates()
     * already chose. Filtering would hand an entirely off-shift department an EMPTY list
     * and omit suggestion 7 at exactly the hour triage help matters most.
     *
     * **TenantTime::now(), never now().** `start_time`/`end_time` are bare `time` columns a
     * human typed on a workspace screen, so they mean tenant-local wall clock. Comparing
     * them against the UTC clock would put a UTC+8 workspace's 09:00-17:00 shift at
     * 01:00-09:00 UTC and report the whole department off shift during their working day —
     * a wrong suggestion that looks authoritative. TenantTime reads the workspace's own
     * `timezone` setting through the session, which the queued job sets from an explicit
     * Tenant before it calls analyze() and restores in a `finally`. The create-ticket
     * draft needs no such dance: it is a web request whose session already IS the
     * workspace in the URL. The schedule ROWS are still pinned to the explicit Tenant
     * either way — only the clock reads the session (follow-up 22).
     *
     * ONE query for every candidate, not one per candidate: an N+1 inside a prompt builder
     * is paid on every analysis.
     *
     * @param  array<string, User>  $engineers
     * @return array<string, bool|null>|null
     */
    public function availabilityFor(Tenant $tenant, array $engineers): ?array
    {
        if ($engineers === []) {
            return null;
        }

        // Level 1. `agent_schedule` is Business+ and this feature is Enterprise, so it is
        // present in practice — but a plan's feature list is data an administrator can
        // edit, and a workspace without it has no schedule screen to have filled in.
        if (! $this->plans->tenantHasFeature($tenant, PlanFeature::AgentSchedule)) {
            return null;
        }

        $memberIds = array_map(fn (User $member) => (int) $member->getKey(), array_values($engineers));

        // `tenant_id` EXPLICITLY. AgentSchedule is a BelongsToTenant model, which is
        // exactly the thing that will NOT protect this query: TenantScope returns before
        // it reads the session whenever auth()->check() is false, so in the queued
        // automatic run it applies no predicate at all. `notes` is deliberately not
        // selected — it never needs to leave the server.
        $rows = AgentSchedule::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->whereIn('user_id', $memberIds)
            ->get(['user_id', 'day_of_week', 'start_time', 'end_time', 'is_available', 'effective_date', 'end_date'])
            ->groupBy('user_id');

        // Level 2.
        if ($rows->isEmpty()) {
            return null;
        }

        $now = TenantTime::now();
        $dayOfWeek = (int) $now->dayOfWeek;          // 0 = Sunday, matching day_of_week
        $time = $now->format('H:i:s');
        $date = $now->toDateString();                // a tenant-local date, for date columns

        $availability = [];

        // Level 3.
        foreach ($engineers as $ref => $member) {
            $theirs = $rows->get((int) $member->getKey());

            // No rows at all is UNKNOWN; rows that do not cover today is OFF shift. The
            // difference is the whole point of the tri-state.
            $availability[$ref] = $theirs === null || $theirs->isEmpty()
                ? null
                : $theirs->contains(fn (AgentSchedule $row) => $this->isOnShift($row, $dayOfWeek, $time, $date));
        }

        return $availability;
    }

    /**
     * One schedule row against one tenant-local instant.
     */
    private function isOnShift(AgentSchedule $row, int $dayOfWeek, string $time, string $date): bool
    {
        if ($row->is_available !== true || (int) $row->day_of_week !== $dayOfWeek) {
            return false;
        }

        if ($row->effective_date !== null && $row->effective_date->toDateString() > $date) {
            return false;
        }

        if ($row->end_date !== null && $row->end_date->toDateString() < $date) {
            return false;
        }

        $start = $this->wallClock($row->start_time);
        $end = $this->wallClock($row->end_time);

        if ($start === null || $end === null) {
            return false;
        }

        // An overnight shift (22:00-06:00) reads as off shift all night under a naive
        // BETWEEN, and a 24/7 MSP desk is exactly this product's customer.
        return $start > $end
            ? ($time >= $start || $time <= $end)
            : ($time >= $start && $time <= $end);
    }

    /**
     * A `time` column as a comparable HH:MM:SS string. MySQL hands back HH:MM:SS, but a
     * seeder or an import may have stored HH:MM, and '9:00' sorts after '10:00:00' as a
     * string, so the shape is normalised rather than trusted.
     */
    private function wallClock(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim((string) $value), $m) !== 1) {
            return null;
        }

        return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], (int) ($m[3] ?? 0));
    }

    /**
     * The ONE agent-instruction block. Both the analysis and the create-form draft compose
     * these lines; a second copy is how the department's expertise comes to mean two
     * different things on two surfaces.
     *
     * The header deliberately names the PRIORITY judgement. The department's agents are
     * what tell a generic model how much impact this class of problem really has, and
     * before P7 the block was attached to an analysis that never asked them about it.
     *
     * @param  list<AgentRecommendation>  $recommendations
     * @return list<string> Empty when there are no recommendations — the caller appends nothing.
     */
    public static function agentInstructionLines(array $recommendations): array
    {
        if ($recommendations === []) {
            return [];
        }

        // The department's own expertise, which is what makes this a *department* AI
        // agent feature rather than a generic copilot.
        $lines = ['The following specialist instructions come from the AI agents this '
            .'department has configured. Apply them within your analysis, INCLUDING when you '
            .'judge the priority — their expertise is what tells you how much impact this '
            .'class of problem really has:'];

        foreach ($recommendations as $recommendation) {
            $skills = $recommendation->matchedSkills === []
                ? ''
                : ' (relevant skills: '.implode(', ', $recommendation->matchedSkills).')';

            $lines[] = '- '.$recommendation->name
                .($recommendation->roleType === null ? '' : ' ['.$recommendation->roleType.']')
                .$skills.': '.(string) $recommendation->systemPrompt;
        }

        return $lines;
    }

    /**
     * The rubric block for exactly the levels a surface is offering.
     *
     * A bare four-word `PRIORITY OPTIONS` list was the entire rubric this product shipped
     * for a year: the dimensions were named ("scope of impact, whether work is blocked,
     * any stated deadline") and the thresholds never were, so an ambiguous ticket hedged
     * down and the engineer had no way to argue with the answer. This is the thresholds.
     *
     * @param  list<string>  $admissible  The authoritative offered set. NOTHING outside it is
     *                                    described — a level the workspace cannot honour must
     *                                    not be explained to the model any more than it is
     *                                    offered.
     * @param  array<string, array{response: int, resolution: int}>  $targets
     *                                                                         Per-priority commitments, or [] to omit the hours.
     * @return string '' when there is no ladder to describe: a rubric is a ladder, and with
     *                one rung there is no boundary to draw.
     */
    public static function priorityRubric(array $admissible, array $targets = []): string
    {
        $listed = array_values(array_filter(
            array_keys(self::PRIORITY_CRITERIA),
            fn (string $priority) => in_array($priority, $admissible, true),
        ));

        if (count($listed) < 2) {
            return '';
        }

        $lines = [
            'PRIORITY RUBRIC',
            'Use these definitions. They are the thresholds; the specialist instructions above tell you '
                .'which of these conditions this kind of problem actually meets.',
        ];

        // Highest-first, deliberately: the reader of a rubric needs the ceiling and its
        // gates first. `PRIORITY OPTIONS` keeps its own PRIORITIES order, untouched.
        foreach ($listed as $priority) {
            $hours = $targets[$priority] ?? null;

            $lines[] = '- '.$priority
                .($hours === null
                    ? ''
                    : ' (this workspace commits to respond within '.$hours['response'].'h and resolve within '.$hours['resolution'].'h)')
                .': '.self::PRIORITY_CRITERIA[$priority];
        }

        if (in_array('critical', $listed, true) && in_array('high', $listed, true)) {
            $lines[] = self::PRIORITY_BOUNDARY;
        }

        $lines[] = self::PRIORITY_UNCERTAINTY;

        return implode("\n", $lines);
    }

    /**
     * @param  list<AgentRecommendation>  $recommendations
     */
    private function systemPrompt(Ticket $ticket, array $recommendations, bool $withAvailability = false): string
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

        $lines = array_merge($lines, self::agentInstructionLines($recommendations));

        $lines[] = 'Reply with a single JSON object and nothing else, with exactly these keys:'
            ."\n".'{"summary": "string, at most 1200 characters", '
            .'"polished_subject": "string, at most 80 characters, or null", '
            .'"polished_description": "string, structured, or null", '
            .'"category_ref": "one of the supplied category references, or null", '
            .'"priority": "one of low|medium|high|critical, or null", '
            .'"priority_reason": "string, at most 200 characters, or null", '
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
        $lines[] = 'The priority reason must name which rubric condition the ticket meets — the '
            .'breadth of impact and whether a workaround exists — not restate the ticket.';

        $lines[] = 'The engineer references identify nobody: judge them only on the tier and '
            .'the open-ticket load given. Prefer a lighter load at a sufficient tier. The '
            .'reason must not claim to know who the person is.';

        if ($withAvailability) {
            // Only said when the key is actually sent. Describing an attribute the payload
            // does not carry invites the model to reason about data it was never given.
            $lines[] = '"available" may be true, false or null. null means UNKNOWN, not '
                .'unavailable. Prefer an available engineer when one exists; if none is marked '
                .'available, still choose the best candidate on tier and load.';
        }

        return implode("\n\n", $lines);
    }

    /**
     * @param  array<string, TicketCategory>  $categories
     * @param  array<string, User>  $engineers
     * @param  array<int, array{ticket: Ticket, score: float}>  $context
     * @param  array<string, bool|null>|null  $availability  Null omits the key entirely.
     * @param  string  $facts  TicketTechnicalFields::factBlock(), or '' to append nothing.
     */
    private function userPrompt(
        string $sanitised,
        array $categories,
        array $engineers,
        array $context,
        ?array $availability = null,
        string $facts = '',
    ): string {
        $sections = ["TICKET\n".$sanitised];

        // The reporter's own stated facts, immediately after the ticket they are about and
        // well above the rubric that maps them. In the USER message, never the system
        // prompt: reported data in the instruction channel is exactly the confusion
        // PromptInjectionService exists to prevent. Empty when nothing was captured, and
        // then the prompt is byte-identical to the pre-P8 one.
        if ($facts !== '') {
            $sections[] = $facts;
        }

        $sections[] = "CATEGORY OPTIONS (choose one reference or null)\n".($categories === []
            ? '(none available — return null)'
            : implode("\n", array_map(
                fn (string $ref, TicketCategory $category) => $ref.': '.$category->name,
                array_keys($categories),
                array_values($categories),
            )));

        // The same builder the draft surface uses, over THIS surface's offered set — which
        // is all four of PRIORITIES, unfiltered by SLA coverage (a pre-existing shape,
        // re-guarded when the engineer presses Apply; follow-up 39). No hours: this
        // surface's offered set is not narrowed per client tier, so quoting one tier's
        // commitments would be a guess presented as one.
        $rubric = self::priorityRubric(self::PRIORITIES);

        if ($rubric !== '') {
            $sections[] = $rubric;
        }

        $sections[] = 'PRIORITY OPTIONS (choose one or null)'."\n".implode(', ', self::PRIORITIES);

        // JSON, so the shape of what leaves this application is readable in one glance
        // and assertable in one string: a ref, a tier, a count. Nothing else.
        $sections[] = "ENGINEER OPTIONS (choose one reference or null)\n".($engineers === []
            ? '(none available — return null)'
            : implode("\n", array_map(
                fn (array $row) => (string) json_encode($row),
                $this->candidatePayload($engineers, $availability),
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
     * The ONE outbound per-candidate payload — a ref, a tier, a count, and (when the
     * signal exists) one boolean-ish availability key.
     *
     * Extracted from userPrompt()'s inline literal so there is exactly one place in the
     * application that decides what a candidate looks like to OpenAI, and one assertion
     * target for the privacy test. **No id, no name, no email, no shift times, and never
     * `agent_schedules.notes`** — free text a human wrote about a colleague. The ref map
     * is how a reply is resolved back to a person, server-side, by identity.
     *
     * JSON, so the shape of what leaves this application is readable in one glance and
     * assertable in one string.
     *
     * @param  array<string, User>  $engineers
     * @param  array<string, bool|null>|null  $availability  Null omits the key entirely.
     * @return list<array<string, mixed>>
     */
    public function candidatePayload(array $engineers, ?array $availability): array
    {
        $rows = [];

        foreach ($engineers as $ref => $member) {
            $row = [
                'ref' => $ref,
                'tier' => $member->support_tier,
                'open_tickets' => (int) ($member->tickets_count ?? 0),
            ];

            if ($availability !== null) {
                $row['available'] = $availability[$ref] ?? null;
            }

            $rows[] = $row;
        }

        return $rows;
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
        ?Department $department,
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
            // Written on EITHER automatic path; NULL leaves manual runs unconstrained.
            'auto_for_ticket_id' => in_array($trigger, AiTicketAnalysis::AUTOMATIC_TRIGGERS, true) ? $ticket->getKey() : null,
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
        $engineer = $this->validateEngineer($decoded['engineer_ref'] ?? null, $engineers, $ticket, $department, $rejected);
        $engineerReason = $engineer === null ? null : $this->validateReason($decoded['engineer_reason'] ?? null);
        // Bound to `priority` exactly as the engineer's reason is bound to the engineer: a
        // reason for a suggestion that was not made is a sentence about nothing.
        $priorityReason = $priority === null ? null : $this->validateReason($decoded['priority_reason'] ?? null);
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
            // Display only — there is nothing on the ticket to apply a sentence to, so it
            // carries no Apply/Edit/Dismiss control and is NOT an AiSuggestionVerdict type.
            // A verdict row for an inapplicable suggestion would be a false row in the
            // table the pilot's accept-vs-edit metric comes from.
            'priority_reason' => $priorityReason,
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
            if (! in_array($attributes['trigger'] ?? null, AiTicketAnalysis::AUTOMATIC_TRIGGERS, true)) {
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
     * The department compared against is the VERIFIED one from departmentOwnedBy(), not
     * the raw `tickets.department_id` — closing the engineer half of follow-up 13, on the
     * signature the reviewer asked for it when next touched.
     *
     * @param  array<string, User>  $engineers
     * @param  list<string>  $rejected
     */
    private function validateEngineer(mixed $ref, array $engineers, Ticket $ticket, ?Department $department, array &$rejected): ?User
    {
        if ($ref === null || $ref === '') {
            return null;
        }

        if (! is_string($ref) || ! array_key_exists($ref, $engineers)) {
            $rejected[] = AiSuggestionVerdict::TYPE_ENGINEER;

            return null;
        }

        $member = $engineers[$ref];

        $stillEligible = $department !== null
            && (bool) $member->is_available
            && $member->tenants()->where('tenant_id', $ticket->tenant_id)->exists()
            && $member->departments()->where('departments.id', $department->getKey())->exists();

        if (! $stillEligible) {
            $rejected[] = AiSuggestionVerdict::TYPE_ENGINEER;

            return null;
        }

        return $member;
    }

    /**
     * The 200-character single-line clamp both reasons share. Named for the shape rather
     * than for one caller since `priority_reason` joined `engineer_reason`.
     */
    private function validateReason(mixed $reason): ?string
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
            'auto_for_ticket_id' => in_array($trigger, AiTicketAnalysis::AUTOMATIC_TRIGGERS, true) ? $ticket->getKey() : null,
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
