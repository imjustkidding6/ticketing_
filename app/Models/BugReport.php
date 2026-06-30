<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A bug in the product itself, reported by a user through the AI Assistant.
 * Internal staff (is_admin) review these at /admin/bugs and can escalate one to
 * the "AI Programmer" (Claude Code) which opens a fix PR. Status then flows back
 * and the reporting user is notified inside the assistant.
 */
class BugReport extends Model
{
    use BelongsToTenant;

    public const STATUS_NEW = 'new';

    public const STATUS_TRIAGED = 'triaged';

    public const STATUS_ESCALATED = 'escalated';   // sent to the AI Programmer (GitHub issue created)

    public const STATUS_PR_OPENED = 'pr_opened';    // Claude Code opened a fix PR

    public const STATUS_MERGED = 'merged';          // fix merged (shipping)

    public const STATUS_CLOSED = 'closed';

    public const STATUS_REJECTED = 'rejected';

    /** Statuses worth surfacing to the reporter inside the assistant. */
    public const USER_FACING_STATUSES = [self::STATUS_PR_OPENED, self::STATUS_MERGED, self::STATUS_REJECTED];

    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];

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

    protected function casts(): array
    {
        return ['ai_triage' => 'array'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    /** A short human label like "BUG-123". */
    public function reference(): string
    {
        return 'BUG-'.$this->id;
    }

    /** A one-line, user-friendly description of the current status. */
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
