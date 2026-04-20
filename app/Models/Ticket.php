<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Ticket extends Model
{
    /** @use HasFactory<\Database\Factories\TicketFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'ticket_number',
        'subject',
        'description',
        'priority',
        'status',
        'in_progress_at',
        'client_id',
        'category_id',
        'department_id',
        'created_by',
        'assigned_to',
        'sla_policy_id',
        'response_due_at',
        'resolution_due_at',
        'first_response_at',
        'closed_at',
        'closing_remarks',
        'is_billable',
        'billable_amount',
        'billable_description',
        'billed_at',
        'parent_ticket_id',
        'is_merged',
        'merged_into_ticket_id',
        'merged_at',
        'reopened_count',
        'hold_started_at',
        'total_hold_time_minutes',
        'current_tier',
        'escalation_count',
        'last_escalated_at',
        'is_spam',
        'marked_spam_at',
        'marked_spam_by',
        'spam_reason',
        'metadata',
        'tracking_token',
        'attachments',
        'incident_date',
        'preferred_service_date',
        'is_false_alarm',
        'deleted_by',
        'deletion_reason',
        'sla_breach_notified_at',
        'merge_metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_due_at' => 'datetime',
            'resolution_due_at' => 'datetime',
            'first_response_at' => 'datetime',
            'in_progress_at' => 'datetime',
            'closed_at' => 'datetime',
            'billed_at' => 'datetime',
            'merged_at' => 'datetime',
            'hold_started_at' => 'datetime',
            'last_escalated_at' => 'datetime',
            'sla_breach_notified_at' => 'datetime',
            'marked_spam_at' => 'datetime',
            'is_billable' => 'boolean',
            'is_merged' => 'boolean',
            'is_spam' => 'boolean',
            'is_false_alarm' => 'boolean',
            'incident_date' => 'datetime',
            'preferred_service_date' => 'datetime',
            'billable_amount' => 'decimal:2',
            'metadata' => 'array',
            'attachments' => 'array',
            'merge_metadata' => 'array',
            'reopened_count' => 'integer',
            'total_hold_time_minutes' => 'integer',
            'escalation_count' => 'integer',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket) {
            if (empty($ticket->ticket_number)) {
                $ticket->ticket_number = static::generateTicketNumber();
            }

            if (empty($ticket->status)) {
                $ticket->status = 'open';
            }
        });
    }

    /**
     * Generate a unique ticket number.
     */
    public static function generateTicketNumber(): string
    {
        do {
            $number = 'TKT-'.now()->format('mdY').'-'.strtoupper(Str::random(7));
        } while (static::withoutGlobalScopes()->where('ticket_number', $number)->exists());

        return $number;
    }

    // ─── Relationships ───────────────────────────────────

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function parentTicket(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_ticket_id');
    }

    public function childTickets(): HasMany
    {
        return $this->hasMany(self::class, 'parent_ticket_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TicketAssignment::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(TicketTask::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(TicketHistory::class);
    }

    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(TicketEscalation::class);
    }

    public function serviceReports(): HasMany
    {
        return $this->hasMany(ServiceReport::class);
    }

    public function mergedIntoTicket(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_ticket_id');
    }

    public function mergedTickets(): HasMany
    {
        return $this->hasMany(self::class, 'merged_into_ticket_id');
    }

    public function deletedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // ─── Scopes ──────────────────────────────────────────

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeBillable(Builder $query): Builder
    {
        return $query->where('is_billable', true);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeBilled(Builder $query): Builder
    {
        return $query->whereNotNull('billed_at');
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeUnbilled(Builder $query): Builder
    {
        return $query->where('is_billable', true)->whereNull('billed_at');
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'assigned', 'in_progress', 'on_hold']);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereIn('status', ['closed', 'cancelled']);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeNotSpam(Builder $query): Builder
    {
        return $query->where('is_spam', false);
    }

    // ─── Helpers ─────────────────────────────────────────

    /**
     * Check if the resolution is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->resolution_due_at
            && now()->gt($this->resolution_due_at)
            && ! in_array($this->status, ['closed', 'cancelled']);
    }

    /**
     * Check if the response is overdue.
     */
    public function isResponseOverdue(): bool
    {
        return $this->response_due_at
            && now()->gt($this->response_due_at)
            && $this->first_response_at === null
            && ! in_array($this->status, ['closed', 'cancelled']);
    }

    /**
     * Start hold time tracking.
     */
    public function startHold(): void
    {
        $this->update(['hold_started_at' => now()]);
    }

    /**
     * End hold time tracking and accumulate duration.
     */
    public function endHold(): void
    {
        if ($this->hold_started_at) {
            $holdMinutes = (int) now()->diffInMinutes($this->hold_started_at);
            $this->update([
                'total_hold_time_minutes' => $this->total_hold_time_minutes + $holdMinutes,
                'hold_started_at' => null,
            ]);
        }
    }

    /**
     * Get total hold time in minutes (including current hold if active).
     */
    public function getTotalHoldTimeMinutes(): int
    {
        $total = $this->total_hold_time_minutes ?? 0;

        if ($this->hold_started_at) {
            $total += (int) now()->diffInMinutes($this->hold_started_at);
        }

        return $total;
    }

    /**
     * Get effective resolution time in hours (excluding hold time).
     */
    public function getEffectiveResolutionTimeHours(): ?float
    {
        if (! $this->closed_at) {
            return null;
        }

        $totalHours = $this->created_at->diffInMinutes($this->closed_at) / 60;
        $holdHours = $this->getTotalHoldTimeMinutes() / 60;

        return round(max(0, $totalHours - $holdHours), 1);
    }

    /**
     * Get effective response time in hours (excluding hold time).
     */
    public function getEffectiveResponseTimeHours(): ?float
    {
        if (! $this->first_response_at) {
            return null;
        }

        $totalHours = $this->created_at->diffInMinutes($this->first_response_at) / 60;
        $holdHours = $this->getTotalHoldTimeMinutes() / 60;

        return round(max(0, $totalHours - $holdHours), 1);
    }

    /**
     * Get resolution time from in_progress_at to closed_at in hours.
     */
    public function getWorkResolutionTimeHours(): ?float
    {
        if (! $this->closed_at || ! $this->in_progress_at) {
            return null;
        }

        $totalHours = $this->in_progress_at->diffInMinutes($this->closed_at) / 60;
        $holdHours = $this->getTotalHoldTimeMinutes() / 60;

        return round(max(0, $totalHours - $holdHours), 1);
    }

    /**
     * Format hours into a human-readable string.
     */
    public static function formatHours(?float $hours): string
    {
        if ($hours === null) {
            return '-';
        }

        if ($hours < 1) {
            return round($hours * 60).'m';
        }

        if ($hours < 24) {
            return round($hours, 1).'h';
        }

        $days = floor($hours / 24);
        $remainingHours = round($hours - ($days * 24), 1);

        return $days.'d '.$remainingHours.'h';
    }

    /**
     * Merge this ticket into another ticket.
     */
    public function mergeInto(Ticket $target, User $mergedBy): void
    {
        $this->update([
            'is_merged' => true,
            'merged_into_ticket_id' => $target->id,
            'merged_at' => now(),
            'merge_metadata' => [
                'merged_by' => $mergedBy->id,
                'merged_at' => now()->toISOString(),
                'original_status' => $this->status,
            ],
        ]);
    }

    /**
     * Reopen a closed/cancelled ticket.
     */
    public function reopen(User $reopenedBy): void
    {
        $this->update([
            'status' => 'open',
            'closed_at' => null,
            'reopened_count' => $this->reopened_count + 1,
        ]);
    }

    /**
     * Add a history entry.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function addToHistory(
        string $action,
        ?string $fieldName = null,
        mixed $oldValue = null,
        mixed $newValue = null,
        ?array $metadata = null,
        ?User $user = null,
    ): TicketHistory {
        return TicketHistory::create([
            'ticket_id' => $this->id,
            'user_id' => $user?->id ?? auth()->id(),
            'action' => $action,
            'field_name' => $fieldName,
            'old_value' => $oldValue ? (string) $oldValue : null,
            'new_value' => $newValue ? (string) $newValue : null,
            'metadata' => $metadata,
        ]);
    }
}
