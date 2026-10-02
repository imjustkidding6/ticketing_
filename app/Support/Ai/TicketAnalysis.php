<?php

declare(strict_types=1);

namespace App\Support\Ai;

use App\Models\AiTicketAnalysis;

/**
 * The validated result of one ANALYZE TICKET run.
 *
 * The raw decoded model JSON never leaves `AnalyzeTicketService`: every field here has
 * been validated, and anything the model invented is in `rejectedFields` instead — and
 * is never rendered. A refusal or a failure is a DTO too, carrying `status` and no
 * suggestions, so the controller shapes its response from the result rather than from
 * an exception.
 */
final readonly class TicketAnalysis
{
    /**
     * @param  list<array{id: int, ticket_number: string, subject: string, score: float}>  $contextTickets
     * @param  list<string>  $rejectedFields
     * @param  list<AgentRecommendation>  $agentRecommendations
     */
    public function __construct(
        public string $status,
        public ?int $analysisId,
        public ?string $summary = null,
        public ?int $categoryId = null,
        public ?string $categoryName = null,
        public ?string $priority = null,
        public array $contextTickets = [],
        public array $rejectedFields = [],
        public array $agentRecommendations = [],
        public ?string $errorMessage = null,
    ) {}

    public function succeeded(): bool
    {
        return $this->status === AiTicketAnalysis::STATUS_SUCCEEDED;
    }

    public function refused(): bool
    {
        return in_array($this->status, [
            AiTicketAnalysis::STATUS_REFUSED_INJECTION,
            AiTicketAnalysis::STATUS_REFUSED_MODERATION,
        ], true);
    }

    /**
     * The shape the panel reads. Nothing here is an id the browser posts back as a
     * suggestion: the engineer's Apply goes through the existing ticket-update
     * endpoint, which re-validates everything through `ScopesRulesToTenant`.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'analysis_id' => $this->analysisId,
            'summary' => $this->summary,
            'category' => $this->categoryId === null ? null : [
                'id' => $this->categoryId,
                'name' => $this->categoryName,
            ],
            'priority' => $this->priority,
            'context_tickets' => $this->contextTickets,
            'rejected_fields' => $this->rejectedFields,
            'agents' => array_map(fn (AgentRecommendation $a) => $a->toArray(), $this->agentRecommendations),
        ];
    }
}
