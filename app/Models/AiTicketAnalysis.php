<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One ANALYZE TICKET run: what was asked, what it cost, what came back.
 *
 * A refusal and a failure are rows too. A workspace whose model keeps inventing
 * category refs is then visible in `rejected_fields` rather than only in a support
 * ticket, and a refused run is visible beside the `AiModerationLog` that explains it.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $ticket_id
 * @property int|null $requested_by
 * @property string $trigger
 * @property int|null $auto_for_ticket_id
 * @property int|null $department_id
 * @property string $status
 * @property array<string, mixed>|null $suggestions
 * @property array<int, array<string, mixed>>|null $agent_recommendations
 * @property array<int, int>|null $context_ticket_ids
 * @property array<int, int>|null $usage_log_ids
 * @property array<int, string>|null $rejected_fields
 */
class AiTicketAnalysis extends Model
{
    use BelongsToTenant, LogsActivity;

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_REFUSED_INJECTION = 'refused_injection';

    public const STATUS_REFUSED_MODERATION = 'refused_moderation';

    public const STATUS_FAILED = 'failed';

    /** An engineer pressed Analyze. */
    public const TRIGGER_MANUAL = 'manual';

    /** A customer submitted a ticket on the public portal (P6b). */
    public const TRIGGER_AUTO = 'auto';

    /**
     * An engineer polished a draft on the create form, and the ticket was then created
     * (P6c). Its own population deliberately: a draft row's verdicts are derived from
     * what LANDED on the new ticket, while the manual/auto populations record what the
     * engineer ATTEMPTED to apply. Mixing them would average two different metrics.
     */
    public const TRIGGER_DRAFT = 'draft';

    /**
     * An engineer pressed Generate resolution on a ticket they had just worked (A2-1).
     *
     * Its own population, and `auto_for_ticket_id` is deliberately left NULL on it: an
     * engineer legitimately re-runs after doing more work, so MANY resolution rows per
     * ticket are expected. The unique index on that column is the one-automatic-analysis
     * guarantee and this trigger must not borrow it.
     *
     * Like `manual` and `auto`, and unlike `draft`, its verdicts are written when the
     * engineer presses a copy control — i.e. they count applications ATTEMPTED. See
     * follow-up 23 before averaging the four populations.
     */
    public const TRIGGER_RESOLUTION = 'resolution';

    /**
     * varchar in the database, so this constant is the only enumeration — the
     * `Ticket::STATUSES` rule. Adding a refusal reason needs no migration.
     *
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_SUCCEEDED,
        self::STATUS_REFUSED_INJECTION,
        self::STATUS_REFUSED_MODERATION,
        self::STATUS_FAILED,
    ];

    /**
     * The trigger vocabulary, varchar-backed for the same reason as STATUSES.
     *
     * Deliberately NOT inferred from `requested_by IS NULL`: that column is
     * `nullOnDelete()`, so deleting a user would silently migrate their manual
     * analyses into the auto population and corrupt the pilot's headline metric.
     *
     * @var list<string>
     */
    public const TRIGGERS = [
        self::TRIGGER_MANUAL,
        self::TRIGGER_AUTO,
        self::TRIGGER_DRAFT,
        self::TRIGGER_RESOLUTION,
    ];

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'ticket_id',
        'requested_by',
        'trigger',
        'auto_for_ticket_id',
        'department_id',
        'status',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'estimated_cost',
        'latency_ms',
        'suggestions',
        'agent_recommendations',
        'context_ticket_ids',
        'usage_log_ids',
        'rejected_fields',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'suggestions' => 'array',
            'agent_recommendations' => 'array',
            'context_ticket_ids' => 'array',
            'usage_log_ids' => 'array',
            'rejected_fields' => 'array',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'estimated_cost' => 'decimal:6',
            'latency_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasMany<AiSuggestionVerdict, $this>
     */
    public function verdicts(): HasMany
    {
        return $this->hasMany(AiSuggestionVerdict::class);
    }

    /**
     * The validated value this analysis offered for one suggestion type, normalised
     * the same way the verdict hashes it.
     *
     * Read from the stored row, never from the request: the verdict endpoint must not
     * let the browser tell the server what the AI suggested, or the accept/edit metric
     * becomes a number the client controls.
     */
    public function suggestedValueFor(string $type): ?string
    {
        $suggestions = $this->suggestions ?? [];

        $value = match ($type) {
            AiSuggestionVerdict::TYPE_SUMMARY => $suggestions['summary'] ?? null,
            AiSuggestionVerdict::TYPE_CATEGORY => $suggestions['category']['name'] ?? null,
            AiSuggestionVerdict::TYPE_PRIORITY => $suggestions['priority'] ?? null,
            AiSuggestionVerdict::TYPE_CHECKS => isset($suggestions['checks']) && is_array($suggestions['checks'])
                ? implode("\n", $suggestions['checks'])
                : null,
            AiSuggestionVerdict::TYPE_ENGINEER => $suggestions['engineer']['name'] ?? null,
            // P6b. The polished text is a suggestion like any other, so the hash
            // comparison, the edit ratio and the one-row-per-suggestion unique index all
            // apply to it with no new mechanism.
            AiSuggestionVerdict::TYPE_SUBJECT => $suggestions['polished_subject'] ?? null,
            AiSuggestionVerdict::TYPE_DESCRIPTION => $suggestions['polished_description'] ?? null,
            // A2-1. Prose rather than a field value, but the same mechanism: the hash
            // comparison decides accepted vs edited, and the suggested text is read from
            // the stored row so the browser cannot tell the server what the AI wrote.
            AiSuggestionVerdict::TYPE_CLIENT_RESOLUTION => $suggestions['client_resolution'] ?? null,
            AiSuggestionVerdict::TYPE_INTERNAL_NOTE => $suggestions['internal_note'] ?? null,
            AiSuggestionVerdict::TYPE_CONTEXT => isset($this->context_ticket_ids) && is_array($this->context_ticket_ids)
                ? implode(',', $this->context_ticket_ids)
                : null,
            default => null,
        };

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeAuto(Builder $query): Builder
    {
        return $query->where('trigger', self::TRIGGER_AUTO);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('trigger', self::TRIGGER_DRAFT);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeResolution(Builder $query): Builder
    {
        return $query->where('trigger', self::TRIGGER_RESOLUTION);
    }

    /**
     * The stored row in the SAME shape TicketAnalysis::toArray() produces.
     *
     * One contract, so the panel reads the same keys whether the data arrived from the
     * Analyze fetch or was hydrated into the page from an automatic run. Two shapes for
     * one panel is how the two paths drift.
     *
     * @return array<string, mixed>
     */
    public function toPanelArray(): array
    {
        $suggestions = $this->suggestions ?? [];

        return [
            'status' => $this->status,
            'analysis_id' => (int) $this->getKey(),
            'trigger' => $this->trigger,
            'summary' => $suggestions['summary'] ?? null,
            'polished_subject' => $suggestions['polished_subject'] ?? null,
            'polished_description' => $suggestions['polished_description'] ?? null,
            'category' => $suggestions['category'] ?? null,
            'priority' => $suggestions['priority'] ?? null,
            'checks' => is_array($suggestions['checks'] ?? null) ? $suggestions['checks'] : [],
            'engineer' => $suggestions['engineer'] ?? null,
            // NO `engineer_candidates` key, deliberately — and this is the one place
            // toPanelArray() diverges in shape from TicketAnalysis::toArray().
            //
            // P6a-2's review pinned the invariant that the ticket page carries no
            // server-rendered engineer roster, because such a copy is not scoped by the
            // service's own query. A second copy here was exactly that: a security-
            // sensitive query duplicated, and one that omitted the tier narrowing
            // `engineerCandidates()` applies, so an escalated ticket's hydrated picker
            // offered members the service had excluded and whose verdict would then 422.
            //
            // The panel reads `result.engineer_candidates || []`, so the "Edit & apply"
            // picker simply does not appear on a hydrated analysis; it returns the moment
            // the engineer presses Analyze and the service issues the list itself.
            'context_tickets' => $this->panelContextTickets(),
            'rejected_fields' => is_array($this->rejected_fields) ? $this->rejected_fields : [],
            'agents' => is_array($this->agent_recommendations) ? $this->agent_recommendations : [],
        ];
    }

    /**
     * @return list<array{id: int, ticket_number: string, subject: string, score: float}>
     */
    private function panelContextTickets(): array
    {
        $ids = is_array($this->context_ticket_ids) ? $this->context_ticket_ids : [];

        if ($ids === []) {
            return [];
        }

        return Ticket::query()
            // This analysis's own tenant, stated rather than inherited. It is reached from
            // a request today, but it is the only query in this file that would otherwise
            // rely on a scope whose sibling method explains why that is not the discipline.
            ->where('tenant_id', $this->tenant_id)
            ->whereIn('id', $ids)
            ->get(['id', 'ticket_number', 'subject'])
            ->map(fn (Ticket $ticket) => [
                'id' => (int) $ticket->getKey(),
                'ticket_number' => (string) $ticket->ticket_number,
                'subject' => (string) $ticket->subject,
                // The cosine score is not stored on the row; the panel only prints the
                // number and the subject, so 0.0 is honest rather than invented.
                'score' => 0.0,
            ])
            ->values()
            ->all();
    }
}
