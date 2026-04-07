<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketTask extends Model
{
    /** @use HasFactory<\Database\Factories\TicketTaskFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'ticket_id',
        'task_number',
        'description',
        'status',
        'assigned_to',
        'completed_at',
        'completed_by',
        'notes',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (TicketTask $task) {
            if (empty($task->task_number) && $task->ticket_id) {
                $count = static::withoutGlobalScopes()
                    ->where('ticket_id', $task->ticket_id)
                    ->count();
                $task->task_number = 'T'.($count + 1);
            }
        });
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * @param  Builder<TicketTask>  $query
     * @return Builder<TicketTask>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Check if the task is completed.
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(TaskStatusHistory::class, 'task_id');
    }

    /**
     * Check if the task is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the task status can be changed.
     */
    public function canChangeStatus(): bool
    {
        return $this->status !== 'cancelled';
    }

    /**
     * Update task status and record history.
     */
    public function updateStatus(string $newStatus, User $user, ?string $notes = null): void
    {
        $oldStatus = $this->status;

        $updates = ['status' => $newStatus];

        if ($newStatus === 'completed') {
            $updates['completed_at'] = now();
            $updates['completed_by'] = $user->id;
        } elseif ($oldStatus === 'completed') {
            $updates['completed_at'] = null;
            $updates['completed_by'] = null;
        }

        $this->update($updates);

        TaskStatusHistory::create([
            'task_id' => $this->id,
            'user_id' => $user->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'notes' => $notes,
        ]);
    }

    /**
     * Mark the task as completed.
     */
    public function markAsCompleted(User $user, ?string $notes = null): void
    {
        $this->updateStatus('completed', $user, $notes);
    }

    /**
     * Mark the task as in progress.
     */
    public function markAsInProgress(User $user, ?string $notes = null): void
    {
        $this->updateStatus('in_progress', $user, $notes);
    }

    /**
     * Mark the task as pending.
     */
    public function markAsPending(User $user, ?string $notes = null): void
    {
        $this->updateStatus('pending', $user, $notes);
    }

    /**
     * Mark the task as cancelled.
     */
    public function markAsCancelled(User $user, ?string $notes = null): void
    {
        $this->updateStatus('cancelled', $user, $notes);
    }

    /**
     * Get the progress percentage for the task status.
     */
    public function getStatusProgressPercentage(): int
    {
        return match ($this->status) {
            'pending' => 0,
            'in_progress' => 50,
            'completed' => 100,
            'cancelled' => 0,
            default => 0,
        };
    }

    /**
     * Get the latest status update from history.
     */
    public function getLatestStatusUpdate(): ?TaskStatusHistory
    {
        return $this->statusHistory()->latest()->first();
    }
}
