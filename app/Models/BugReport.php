<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Model representing a bug report filed directly or via the AI Assistant.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $reported_by
 * @property int|null $chat_conversation_id
 * @property string $title
 * @property string $description
 * @property string|null $steps_to_reproduce
 * @property string|null $area
 * @property string $severity
 * @property string $status
 * @property int|null $github_issue_number
 * @property string|null $github_pr_url
 * @property array<string, mixed>|null $ai_triage
 * @property string|null $user_notified_status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read User|null $user
 * @property-read User|null $reporter
 * @property-read ChatConversation|null $conversation
 * @property-read ChatConversation|null $chatConversation
 */
class BugReport extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_NEW = 'new';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_TRIAGED = 'triaged';

    public const STATUS_ESCALATED = 'escalated';

    public const STATUS_PR_OPENED = 'pr_opened';

    public const STATUS_MERGED = 'merged';

    public const STATUS_REJECTED = 'rejected';

    public const SEVERITY_LOW = 'low';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_CRITICAL = 'critical';

    public const LOW = 'low';

    public const MEDIUM = 'medium';

    public const HIGH = 'high';

    public const CRITICAL = 'critical';

    /** Statuses worth surfacing to the reporter inside the assistant. */
    public const USER_FACING_STATUSES = [self::STATUS_PR_OPENED, self::STATUS_MERGED, self::STATUS_REJECTED];

    public const SEVERITIES = [self::SEVERITY_LOW, self::SEVERITY_MEDIUM, self::SEVERITY_HIGH, self::SEVERITY_CRITICAL];

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'reported_by',
        'chat_conversation_id',
        'title',
        'description',
        'steps_to_reproduce',
        'area',
        'severity',
        'status',
        'github_issue_number',
        'github_pr_url',
        'ai_triage',
        'user_notified_status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ai_triage' => 'array',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * @return BelongsTo<ChatConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    /**
     * @return BelongsTo<ChatConversation, $this>
     */
    public function chatConversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    /**
     * Generate formatted bug reference such as BUG-2026-000001.
     */
    public function reference(): string
    {
        $year = $this->created_at ? $this->created_at->format('Y') : date('Y');
        $id = $this->id ?? 0;

        return sprintf('BUG-%s-%06d', $year, $id);
    }

    /**
     * A one-line, user-friendly description of the current status.
     */
    public function userFacingMessage(): string
    {
        return match ($this->status) {
            self::STATUS_PR_OPENED => "A fix for the bug you reported ({$this->reference()}) has been prepared and is under review.",
            self::STATUS_MERGED => "Good news — the fix for the bug you reported ({$this->reference()}) has been completed and is being shipped.",
            self::STATUS_REJECTED => "Thanks for reporting {$this->reference()}. After review, this wasn't something we could fix in code — our team may follow up.",
            default => "Your bug report {$this->reference()} has been received.",
        };
    }
}
