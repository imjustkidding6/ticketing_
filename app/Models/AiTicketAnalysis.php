<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\LogsActivity;
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

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'ticket_id',
        'requested_by',
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
            AiSuggestionVerdict::TYPE_CONTEXT => isset($this->context_ticket_ids) && is_array($this->context_ticket_ids)
                ? implode(',', $this->context_ticket_ids)
                : null,
            default => null,
        };

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
