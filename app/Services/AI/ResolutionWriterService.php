<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Exceptions\OpenAiException;
use App\Models\AiSuggestionVerdict;
use App\Models\AiTicketAnalysis;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AiUsageTrackerService;
use App\Services\ModerationService;
use App\Services\OpenAiService;
use App\Services\PromptInjectionService;
use App\Support\Ai\AgentRecommendation;
use App\Support\Ai\ResolutionDraft;
use Illuminate\Support\Str;

/**
 * GENERATE RESOLUTION (A2-1) — the second of the proposal's two AI actions.
 *
 * ONE engineer-triggered chat call producing TWO prose drafts: a client-friendly
 * resolution (suggestion 8) and a standardised internal technical note (suggestion 9).
 * Suggestion 10 is not a third output — it is the fixed four-heading template the
 * internal note follows, which is what makes the resolution history consistent.
 *
 * **No embedding call.** Analyze spends one because triage benefits from precedent; a
 * resolution is written from what THIS engineer did, and retrieved text from another
 * ticket is exactly the invented-fact risk this output can least afford. One call per
 * press, not two.
 *
 * **The input is the engineer's own rough notes and nothing else.** This service never
 * reads non-public ticket comments from the database: an agent's private note is the one
 * thing on a ticket nobody agreed to send to a third party, and the fact that a colleague
 * pressed a button is not consent for the notes they wrote last week. The panel's "pull
 * in my notes" button is client-side — material already on the engineer's screen, moved
 * into a box they then submit, which is an act of theirs.
 *
 * **Nothing here writes to the ticket, ever.** No `changeStatus()`, no `status`, no
 * `closing_remarks`, no `TicketComment`, no assignment, no `assignSla()`, no escalation,
 * no mail, no `TicketHistory`. The drafts reach the ticket only when the engineer presses
 * one of the ticket's own form buttons, under their own permissions, through controllers
 * this package does not touch. There is no server path here that creates a comment, which
 * is the strongest form of "no AI text reaches a customer without a human action".
 *
 * Separate from `AnalyzeTicketService` because the questions differ: Analyze reads the
 * ticket at triage time and answers five closed-vocabulary questions, this reads what the
 * engineer typed at resolution time and answers with prose. The machinery they share is
 * CALLED, never copied — `sanitisedTicketText()`, `departmentOwnedBy()` and
 * `recommendationsFor()` are that one service's, and the verdict writer is the model's.
 */
class ResolutionWriterService
{
    /** The only mode A2-1 produces. A2-2 adds `acknowledgement` on the same endpoint. */
    public const MODE_RESOLUTION = 'resolution';

    /**
     * A DTO-only status, never persisted: the notes precondition refuses BEFORE any run
     * exists, so unlike an injection refusal it writes no `ai_ticket_analyses` row. Kept
     * out of `AiTicketAnalysis::STATUSES` deliberately — that constant enumerates what
     * the column can hold.
     */
    public const STATUS_NOTES_TOO_SHORT = 'notes_too_short';

    /**
     * Suggestion 10, and the only thing standardising the resolution history.
     *
     * `Follow-up: none` is spelled out because an omitted heading is an edit the engineer
     * has to make every time, which is how a standard stops being one.
     */
    public const NOTE_TEMPLATE_INSTRUCTION = 'The internal note must use exactly these four headings, in this order, '
        ."each followed by one short paragraph:\n"
        ."Cause:\nResolution:\nVerification:\nFollow-up:\n"
        .'Write "Follow-up: none" when nothing is outstanding. Never omit a heading.';

    /**
     * The claim discipline. It is first in the prompt and repeated in the schema because
     * a resolution record that invents a cause, a part number or a date is worse than no
     * resolution record.
     */
    public const CLAIM_INSTRUCTION = 'NEVER invent a fact, a cause, a step, a part number, a serial, a name or a date. '
        .'If the engineer notes do not say it, leave it out. Do not guess at what was probably done. '
        .'You are writing up a resolution a human engineer will review before anything is sent or a '
        .'ticket is closed; you never apply a change and you never send anything.';

    public function __construct(
        private readonly OpenAiService $openAi,
        private readonly AnalyzeTicketService $analyze,
        private readonly PromptInjectionService $injection,
        private readonly ModerationService $moderation,
        private readonly AiUsageTrackerService $usage,
    ) {}

    /**
     * Write up one resolution from the engineer's notes.
     *
     * Never throws for a refusal or a bad reply — those are statuses on the returned DTO
     * and (except the notes precondition) rows in `ai_ticket_analyses`. An
     * OpenAiException, including a tripped circuit breaker raised inside
     * `OpenAiService::post()` before any HTTP, propagates: "the provider is down" is a
     * different answer from "we declined".
     *
     * @throws OpenAiException
     */
    public function write(
        Ticket $ticket,
        User $user,
        string $notes,
        string $mode = self::MODE_RESOLUTION,
    ): ResolutionDraft {
        $notes = trim($notes);

        // Precondition, not a guardrail refusal: an empty or three-word note cannot be
        // rewritten, only invented from. Nothing is sent and NO row is written — a
        // refusal to even try is not a run, and a run is what the cost record counts.
        if (mb_strlen($notes) < (int) config('ai.resolution.notes_min_chars', 40)) {
            return new ResolutionDraft(
                status: self::STATUS_NOTES_TOO_SHORT,
                analysisId: null,
                errorMessage: 'Write a few lines about what you did — the AI rewrites your notes, it cannot invent the work.',
            );
        }

        $ticketText = $this->analyze->sanitisedTicketText($ticket);

        // Screened together. The notes routinely carry pasted customer output, so
        // screening is not optional merely because a colleague typed the box. Both
        // services write their own AiModerationLog row, attributable to tenant and user.
        $screened = $notes."\n\n".$ticketText;

        if ($this->injection->isInjection($screened, $ticket->tenant_id, $user->getKey())) {
            return $this->refuse($ticket, $user, AiTicketAnalysis::STATUS_REFUSED_INJECTION);
        }

        if ($this->moderation->isFlagged($screened, 'balanced', $ticket->tenant_id, $user->getKey())) {
            return $this->refuse($ticket, $user, AiTicketAnalysis::STATUS_REFUSED_MODERATION);
        }

        // CircuitBreakerService is NOT invoked here: OpenAiService::post() already wraps
        // every endpoint, and a second ensureAvailable()/recordFailure() pair would
        // double-count one outage.

        // The ONE department resolver, which fails closed to null on a foreign or missing
        // id. A null department means generic instructions, not no output: a write-up is
        // not department-dependent the way a category is.
        $department = $this->analyze->departmentOwnedBy($ticket->tenant, $ticket->department_id);

        // Ranked against the NOTES, not the ticket description: this question is about the
        // work that was done, so the agent whose skills match the work is the one whose
        // expertise should shape the write-up.
        $recommendations = $this->shapingAgents($this->analyze->recommendationsFor($department, $notes));

        $completed = $this->completedWork($ticket);

        $startedAt = microtime(true);

        /** @var array<string, mixed> $response */
        $response = $this->usage->withContext([
            'tenant_id' => $ticket->tenant_id,
            'user_id' => $user->getKey(),
            // The agent-copilot family, as analyze() and suggest() — not the
            // conversational `chat` channel.
            'feature' => 'copilot',
        ], fn () => $this->openAi->chat(
            [
                ['role' => 'system', 'content' => $this->systemPrompt($ticket, $recommendations)],
                ['role' => 'user', 'content' => $this->userPrompt($notes, $ticketText, $completed)],
            ],
            [],
            [
                'max_tokens' => (int) config('ai.resolution.max_tokens', 1500),
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
            ],
        ));

        return $this->persist(
            $ticket,
            $user,
            $response,
            $department,
            $recommendations,
            $this->usage->lastLoggedIds(),
            (int) round((microtime(true) - $startedAt) * 1000),
            $mode,
        );
    }

    /**
     * Narrow the department's recommendations to the roles that govern a write-up.
     *
     * `documentation` and `validator` exist in `AiAgent::ROLE_TYPES` for exactly this
     * moment. A FILTER over the DTO list the one recommender already returned — never a
     * second query, which is the duplication that got `panelCandidates()` deleted. When
     * the department has neither role the list is returned unchanged, because a department
     * with only domain agents should still have its domain shape the note.
     *
     * @param  list<AgentRecommendation>  $recommendations
     * @return list<AgentRecommendation>
     */
    public function shapingAgents(array $recommendations): array
    {
        $shaping = array_values(array_filter(
            $recommendations,
            fn (AgentRecommendation $a) => in_array($a->roleType, ['documentation', 'validator'], true),
        ));

        return $shaping === [] ? $recommendations : $shaping;
    }

    /**
     * The titles of the work this ticket records as done.
     *
     * The cheapest truth available to a "standardise documentation" output: completed
     * tasks are what the engineer actually did. Pinned to the ticket's own workspace
     * explicitly — `ticket_id` already narrows these rows, but `TicketTask` is a
     * BelongsToTenant model whose scope adds no predicate without a session, so the
     * predicate is stated rather than assumed.
     *
     * @return list<string>
     */
    private function completedWork(Ticket $ticket): array
    {
        return $ticket->tasks()
            ->where('tenant_id', $ticket->tenant_id)
            ->where('status', 'completed')
            ->oldest()
            ->pluck('description')
            ->filter()
            ->map('strval')
            ->values()
            ->all();
    }

    /**
     * @param  list<AgentRecommendation>  $recommendations
     */
    private function systemPrompt(Ticket $ticket, array $recommendations): string
    {
        $lines = [
            'You are the resolution writer for '.$ticket->tenant->displayName().'.',
            self::CLAIM_INSTRUCTION,
        ];

        if ($ticket->client_id === null) {
            // A portal submission can have no client record and a submitter nobody can
            // see. No client identity is sent to OpenAI on ANY ticket; this says so in
            // words, so the model cannot fill the gap with a plausible name.
            $lines[] = 'The recipient of the client-facing text is UNIDENTIFIED: this ticket was '
                .'submitted through the public portal and carries no client record. Use no salutation '
                .'and no sign-off, name no person and no company, and never invent a recipient. '
                .'Write it so it reads correctly to whoever opens it.';
        } else {
            $lines[] = 'You are given no name, email or account detail for the recipient and must not '
                .'invent one. Use no salutation and no sign-off.';
        }

        if ($recommendations !== []) {
            // The department's own expertise, which is what makes this a *department* AI
            // agent feature rather than a generic copilot.
            $lines[] = 'The following specialist instructions come from the AI agents this '
                .'department has configured. Apply them to the write-up:';

            foreach ($recommendations as $recommendation) {
                $lines[] = '- '.$recommendation->name
                    .($recommendation->roleType === null ? '' : ' ['.$recommendation->roleType.']')
                    .': '.(string) $recommendation->systemPrompt
                    .' '.$this->roleGuidance($recommendation->roleType);
            }
        }

        $lines[] = self::NOTE_TEMPLATE_INSTRUCTION;

        $lines[] = 'Reply with a single JSON object and nothing else, with exactly these keys:'
            ."\n".'{"client_resolution": "string, at most '.(int) config('ai.resolution.client_chars', 2000)
            .' characters, addressed to the customer, plain prose, no salutation and no signature, '
            .'describing what was wrong and what was done in terms they can read", '
            .'"internal_note": "string, at most '.(int) config('ai.resolution.internal_chars', 2500)
            .' characters, the four fixed headings, written for a colleague"}';

        return implode("\n\n", $lines);
    }

    /**
     * What a role type governs, said once so the model is not left to guess.
     */
    private function roleGuidance(?string $roleType): string
    {
        return match ($roleType) {
            'documentation' => 'Those instructions govern the internal note\'s structure and completeness.',
            'validator' => 'Those instructions govern claim discipline: assert nothing the notes do not support.',
            default => 'Apply those instructions as general domain guidance.',
        };
    }

    /**
     * @param  list<string>  $completed
     */
    private function userPrompt(string $notes, string $ticketText, array $completed): string
    {
        $sections = [
            "ENGINEER NOTES (the only record of what was actually done)\n".$notes,
            "TICKET\n".$ticketText,
            "COMPLETED WORK\n".($completed === [] ? '(none recorded)' : '- '.implode("\n- ", $completed)),
        ];

        return mb_substr(implode("\n\n", $sections), 0, (int) config('ai.resolution.input_chars', 8000));
    }

    /**
     * Validate the reply and write the one capture row.
     *
     * `AnalyzeTicketService::persist()` is private and keyed on Analyze's suggestion
     * shape; the ~15 duplicated lines here are attribute assignment. What is NOT
     * duplicated is the verdict logic or the cost ladder, which are the two places drift
     * would corrupt the pilot.
     *
     * @param  array<string, mixed>  $response
     * @param  list<AgentRecommendation>  $recommendations
     * @param  list<int>  $usageLogIds
     */
    private function persist(
        Ticket $ticket,
        User $user,
        array $response,
        ?Department $department,
        array $recommendations,
        array $usageLogIds,
        int $latencyMs,
        string $mode,
    ): ResolutionDraft {
        $usage = $response['usage'] ?? [];
        $base = [
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->getKey(),
            'requested_by' => $user->getKey(),
            'trigger' => AiTicketAnalysis::TRIGGER_RESOLUTION,
            // Left NULL deliberately: many resolution rows per ticket are legal and
            // expected. The unique index on this column is the one-automatic-analysis
            // guarantee and this trigger must not borrow it.
            'auto_for_ticket_id' => null,
            'department_id' => $ticket->department_id,
            'model' => (string) ($response['model'] ?? config('services.openai.model', 'gpt-5')),
            'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
            'latency_ms' => $latencyMs,
            'agent_recommendations' => array_map(fn (AgentRecommendation $r) => $r->toArray(), $recommendations),
            'usage_log_ids' => $usageLogIds,
        ];
        // The same per-model rate table the usage logs use. A second cost ladder here is
        // exactly the drift CLAUDE.md warns about for Ticket::STATUSES.
        $base['estimated_cost'] = $this->usage->estimateCost($base['model'], $base['prompt_tokens'], $base['completion_tokens']);

        $content = $response['choices'][0]['message']['content'] ?? null;
        $decoded = is_string($content) ? json_decode($content, true) : null;

        if (! is_array($decoded)) {
            return $this->fail($base, $recommendations, 'The model did not return parseable JSON.');
        }

        $rejected = [];
        $client = $this->validateProse(
            $decoded['client_resolution'] ?? null,
            (int) config('ai.resolution.client_chars', 2000),
            AiSuggestionVerdict::TYPE_CLIENT_RESOLUTION,
            $rejected,
        );
        $internal = $this->validateProse(
            $decoded['internal_note'] ?? null,
            (int) config('ai.resolution.internal_chars', 2500),
            AiSuggestionVerdict::TYPE_INTERNAL_NOTE,
            $rejected,
        );

        // Only the two keys above are read. An `acknowledgement` key is A2-2's and is
        // IGNORED in this mode, so a model that volunteers one cannot smuggle a second
        // client-facing draft past the panel's confirm step; every other unknown
        // top-level key is ignored for the same reason.

        if ($client === null && $internal === null) {
            // Not a succeeded run with nothing in it: an empty panel wearing a success
            // state reads as "the AI had nothing to say about your work".
            return $this->fail($base, $recommendations, 'The model returned no usable draft.', $rejected);
        }

        $analysis = AiTicketAnalysis::create($base + [
            'status' => AiTicketAnalysis::STATUS_SUCCEEDED,
            'suggestions' => [
                'client_resolution' => $client,
                'internal_note' => $internal,
                'mode' => $mode,
            ],
            'rejected_fields' => $rejected,
        ]);

        return new ResolutionDraft(
            status: AiTicketAnalysis::STATUS_SUCCEEDED,
            analysisId: (int) $analysis->getKey(),
            clientResolution: $client,
            internalNote: $internal,
            rejectedFields: $rejected,
            agentRecommendations: $recommendations,
        );
    }

    /**
     * One prose validator for both drafts — same rules, different ceilings.
     *
     * Line endings are normalised and runs of blank lines collapsed, but WHITESPACE IS
     * NOT collapsed: prose needs its paragraphs. The verdict comparison applies
     * `AiSuggestionVerdict::normalise()` as it does for every other type, so the hash is
     * unaffected by that choice.
     *
     * The cap is hard: `client_chars` IS `closing_remarks`' `max:2000`, so a long draft
     * is cut here rather than 422-ing the engineer's own form after the panel offered it.
     *
     * @param  list<string>  $rejected
     */
    private function validateProse(mixed $value, int $limit, string $type, array &$rejected): ?string
    {
        if ($value === null) {
            $rejected[] = $type;

            return null;
        }

        if (! is_string($value)) {
            $rejected[] = $type;

            return null;
        }

        $clean = trim((string) preg_replace("/\n{3,}/u", "\n\n", str_replace(["\r\n", "\r"], "\n", $value)));

        if ($clean === '') {
            $rejected[] = $type;

            return null;
        }

        return Str::limit($clean, $limit, '');
    }

    /**
     * A failure is a row too: a workspace whose model keeps truncating is then visible in
     * the data rather than only in a support ticket. No partial render ever.
     *
     * @param  array<string, mixed>  $base
     * @param  list<AgentRecommendation>  $recommendations
     * @param  list<string>  $rejected
     */
    private function fail(
        array $base,
        array $recommendations,
        string $reason,
        array $rejected = [],
    ): ResolutionDraft {
        $analysis = AiTicketAnalysis::create($base + [
            'status' => AiTicketAnalysis::STATUS_FAILED,
            'suggestions' => null,
            'rejected_fields' => $rejected,
            'error_message' => $reason,
        ]);

        return new ResolutionDraft(
            status: AiTicketAnalysis::STATUS_FAILED,
            analysisId: (int) $analysis->getKey(),
            rejectedFields: $rejected,
            errorMessage: 'The AI could not write up this resolution.',
            agentRecommendations: $recommendations,
        );
    }

    /**
     * A guardrail refusal KEEPS its row, unlike the notes precondition: a run was
     * attempted, it was declined, and the `AiModerationLog` the guardrail wrote beside it
     * is the explanation.
     */
    private function refuse(Ticket $ticket, User $user, string $status): ResolutionDraft
    {
        $analysis = AiTicketAnalysis::create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->getKey(),
            'requested_by' => $user->getKey(),
            'trigger' => AiTicketAnalysis::TRIGGER_RESOLUTION,
            'auto_for_ticket_id' => null,
            'department_id' => $ticket->department_id,
            'status' => $status,
            'error_message' => $status === AiTicketAnalysis::STATUS_REFUSED_INJECTION
                ? 'Refused: the notes or ticket text matched a prompt-injection pattern.'
                : 'Refused: the notes or ticket text was flagged by content moderation.',
        ]);

        return new ResolutionDraft(
            status: $status,
            analysisId: (int) $analysis->getKey(),
            errorMessage: 'This text cannot be sent for a resolution write-up.',
        );
    }
}
