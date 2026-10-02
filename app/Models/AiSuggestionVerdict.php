<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The engineer's verdict on ONE suggestion — the pilot's primary quality metric.
 *
 * One row per suggestion, not per analysis: an engineer routinely accepts the summary,
 * edits the checks and rejects the priority in one sitting, and "accept vs edit rate"
 * is asked per draft. A single verdict per analysis cannot express that and a JSON blob
 * of verdicts cannot be grouped by in SQL.
 *
 * `rejected` is only ever an explicit Dismiss. A closed tab is not a rejection —
 * inferring one would make the headline metric a function of browser behaviour.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $ai_ticket_analysis_id
 * @property int $ticket_id
 * @property int|null $decided_by
 * @property string $suggestion_type
 * @property string $verdict
 */
class AiSuggestionVerdict extends Model
{
    use BelongsToTenant;

    public const TYPE_SUMMARY = 'summary';

    public const TYPE_CATEGORY = 'category';

    public const TYPE_PRIORITY = 'priority';

    public const TYPE_CHECKS = 'checks';

    public const TYPE_ENGINEER = 'engineer';

    public const TYPE_CONTEXT = 'context';

    /**
     * varchar columns, so these constants are the only enumeration (the
     * `Ticket::STATUSES` rule).
     *
     * `checks` and `engineer` are listed because they are the same metric and the same
     * table; the suggestions themselves arrive in P6a-2. Until then a verdict on either
     * is refused for the same reason as any other type the analysis did not offer —
     * see AiTicketAnalysisController::verdict().
     *
     * @var list<string>
     */
    public const TYPES = [
        self::TYPE_SUMMARY,
        self::TYPE_CATEGORY,
        self::TYPE_PRIORITY,
        self::TYPE_CHECKS,
        self::TYPE_ENGINEER,
        self::TYPE_CONTEXT,
    ];

    public const VERDICT_ACCEPTED = 'accepted';

    public const VERDICT_EDITED = 'edited';

    public const VERDICT_REJECTED = 'rejected';

    /** @var list<string> */
    public const VERDICTS = [
        self::VERDICT_ACCEPTED,
        self::VERDICT_EDITED,
        self::VERDICT_REJECTED,
    ];

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'ai_ticket_analysis_id',
        'ticket_id',
        'decided_by',
        'suggestion_type',
        'verdict',
        'suggested_hash',
        'applied_hash',
        'edit_ratio',
        'minutes_saved',
        'decided_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'edit_ratio' => 'float',
            'minutes_saved' => 'integer',
            'decided_at' => 'datetime',
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
     * @return BelongsTo<AiTicketAnalysis, $this>
     */
    public function analysis(): BelongsTo
    {
        return $this->belongsTo(AiTicketAnalysis::class, 'ai_ticket_analysis_id');
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
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * Whitespace-collapsed, trimmed. Case is preserved: a capitalisation change IS an
     * edit, and normalising it away would report it as an acceptance.
     */
    public static function normalise(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    public static function hash(?string $value): ?string
    {
        $value = $value === null ? null : self::normalise($value);

        return $value === null || $value === '' ? null : hash('sha256', $value);
    }

    /**
     * Levenshtein distance over the suggested length, 0..1.
     *
     * PHP's levenshtein() refuses strings over 255 bytes, so both sides are cut to 255
     * first. The figure is a coarse "how much did they change it", not a diff, and a
     * 1,200-character summary that diverges does so inside its first 255 characters
     * often enough for the ratio to stay useful.
     */
    public static function editRatio(?string $suggested, ?string $applied): ?float
    {
        if ($suggested === null || $applied === null) {
            return null;
        }

        $a = mb_strcut(self::normalise($suggested), 0, 255);
        $b = mb_strcut(self::normalise($applied), 0, 255);

        if ($a === '') {
            return null;
        }

        // strlen, not mb_strlen: levenshtein() counts bytes, and dividing a byte
        // distance by a character count would read over 1.0 on any accented text.
        // Capped at 1.0 so "replaced wholesale" and "replaced with something longer"
        // are the same verdict, which is all the metric asks.
        return round(min(1.0, levenshtein($a, $b) / strlen($a)), 4);
    }
}
