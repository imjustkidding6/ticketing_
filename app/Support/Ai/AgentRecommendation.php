<?php

declare(strict_types=1);

namespace App\Support\Ai;

use App\Models\AiAgent;

/**
 * A department AI agent, flattened at the service boundary.
 *
 * P5's `AgentSelectionService::recommendAgents()` writes `recommendation_score` and
 * `matched_skills` onto the `AiAgent` models it returns as pseudo-attributes — no such
 * columns exist. `AiAgent` also has `LogsActivity`, so handing one of those models to
 * anything that saves throws `Unknown column 'recommendation_score'` and writes a
 * spurious activity diff. P5's reviewer left that protected by a comment only; this is
 * the first consumer that moves the recommendations past the view (into the prompt and
 * into `ai_ticket_analyses.agent_recommendations`), so it is where the boundary belongs.
 *
 * Nothing downstream of `AnalyzeTicketService` sees an `AiAgent`.
 */
final readonly class AgentRecommendation
{
    /**
     * @param  list<string>  $matchedSkills
     */
    public function __construct(
        public int $agentId,
        public string $name,
        public ?string $roleType,
        public ?string $systemPrompt,
        public float $score,
        public array $matchedSkills,
    ) {}

    public static function fromModel(AiAgent $agent): self
    {
        return new self(
            agentId: (int) $agent->getKey(),
            name: (string) $agent->name,
            roleType: $agent->role_type,
            systemPrompt: $agent->effectiveSystemPrompt(),
            // Both pseudo-attributes are absent unless recommendAgents() set them —
            // getAutoInvokeAgents() does not, and did not score anything.
            score: (float) ($agent->recommendation_score ?? 0),
            matchedSkills: array_values(array_map('strval', $agent->matched_skills ?? [])),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'agent_id' => $this->agentId,
            'name' => $this->name,
            'role_type' => $this->roleType,
            'score' => $this->score,
            'matched_skills' => $this->matchedSkills,
        ];
    }
}
