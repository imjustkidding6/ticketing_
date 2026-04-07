<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskStatusHistory extends Model
{
    protected $fillable = [
        'task_id',
        'user_id',
        'old_status',
        'new_status',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(TicketTask::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get a human-readable description of the status change.
     */
    public function getStatusChangeDescription(): string
    {
        if ($this->old_status) {
            return ucfirst(str_replace('_', ' ', $this->old_status)).' → '.ucfirst(str_replace('_', ' ', $this->new_status));
        }

        return 'Set to '.ucfirst(str_replace('_', ' ', $this->new_status));
    }
}
