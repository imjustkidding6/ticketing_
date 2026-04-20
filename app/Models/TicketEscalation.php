<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketEscalation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'ticket_id',
        'escalated_by',
        'escalated_from_user_id',
        'escalated_to_user_id',
        'from_tier',
        'to_tier',
        'trigger_type',
        'reason',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function escalatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_by');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to_user_id');
    }
}
