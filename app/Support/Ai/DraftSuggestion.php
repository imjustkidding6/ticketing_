<?php

declare(strict_types=1);

namespace App\Support\Ai;

/**
 * The validated result of ONE "Polish with Nexus AI" press on a ticket draft.
 *
 * The raw decoded model JSON never leaves `DraftSuggestionService`: every field here has
 * been validated, and anything the model invented is in `rejectedFields` instead — and is
 * never rendered. `toArray()` is the JSON contract the create and edit forms read, and it
 * is a strict SUPERSET of what the button returned before: `subject`, `description` and
 * `tasks` keep their exact meanings, and everything new is nullable.
 *
 * A refusal is a DTO too (`refusal` set, every suggestion null), so the controller shapes
 * a 422 from the result rather than from an exception — the engineer is in the loop here
 * and is shown why the text was declined.
 *
 * Trailing named optional parameters, the `TicketAnalysis` rule, so P6c-2's engineer keys
 * can be appended without breaking anything that constructs this.
 */
final readonly class DraftSuggestion
{
    /**
     * @param  list<string>  $tasks
     * @param  array{missing: list<string>, basis: string, message: string}|null  $priorityGap
     * @param  list<string>  $rejectedFields
     * @param  list<int>  $usageLogIds
     */
    public function __construct(
        public string $subject,
        public string $description,
        public array $tasks = [],
        public ?string $priority = null,
        public ?string $priorityReason = null,
        public ?array $priorityGap = null,
        public array $rejectedFields = [],
        public ?string $draftToken = null,
        public ?string $refusal = null,
        public string $model = '',
        public int $promptTokens = 0,
        public int $completionTokens = 0,
        public int $latencyMs = 0,
        public array $usageLogIds = [],
        public float $estimatedCost = 0.0,
    ) {}

    public function refused(): bool
    {
        return $this->refusal !== null;
    }

    /**
     * The shape the partial reads.
     *
     * Nothing here is an id the browser posts back as a suggestion: the priority is one of
     * four words the form already offers, and `draft_token` is an opaque cache key
     * re-scoped to the workspace and the engineer it was issued to — never an id, and
     * never the suggestion data itself, or accept-rate would be forgeable.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'subject' => $this->subject,
            'description' => $this->description,
            'tasks' => $this->tasks,
            'priority' => $this->priority,
            'priority_reason' => $this->priorityReason,
            'priority_gap' => $this->priorityGap,
            'rejected_fields' => $this->rejectedFields,
            'draft_token' => $this->draftToken,
        ];
    }
}
