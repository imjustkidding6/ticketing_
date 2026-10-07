<?php

declare(strict_types=1);

namespace App\Support\Ai;

use App\Models\AiTicketAnalysis;

/**
 * The validated result of one GENERATE RESOLUTION run (A2-1).
 *
 * Two prose drafts from one chat call: a client-friendly resolution and a standardised
 * internal technical note. The raw decoded model JSON never leaves
 * `ResolutionWriterService` — anything it returned that failed validation is in
 * `rejectedFields` and is never rendered.
 *
 * Neither draft is applied by anything. They reach the ticket only when the engineer
 * presses one of the ticket's own form buttons, which is why this DTO carries text and
 * no id, no status and no form target.
 *
 * Fields are named optional parameters in declaration order, the `TicketAnalysis` rule,
 * so A2-2's `acknowledgement` can be appended without touching a construction site.
 */
final readonly class ResolutionDraft
{
    /**
     * @param  list<string>  $rejectedFields
     * @param  list<AgentRecommendation>  $agentRecommendations
     */
    public function __construct(
        public string $status,
        public ?int $analysisId,
        public ?string $clientResolution = null,
        public ?string $internalNote = null,
        public array $rejectedFields = [],
        public ?string $errorMessage = null,
        public array $agentRecommendations = [],
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
     * The shape the panel reads.
     *
     * Nothing here is a form target or an id the browser posts back as an instruction:
     * both drafts are text the engineer copies into a form the ticket page already has,
     * under their own permissions.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'analysis_id' => $this->analysisId,
            'trigger' => AiTicketAnalysis::TRIGGER_RESOLUTION,
            'client_resolution' => $this->clientResolution,
            'internal_note' => $this->internalNote,
            'rejected_fields' => $this->rejectedFields,
            'agents' => array_map(fn (AgentRecommendation $a) => $a->toArray(), $this->agentRecommendations),
        ];
    }
}
