<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAgentAssignment extends Model
{
    protected $fillable = [
        'client_id',
        'agent_id',
        'assignment_month',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'assignment_month' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * @param  Builder<ClientAgentAssignment>  $query
     * @return Builder<ClientAgentAssignment>
     */
    public function scopeCurrentMonth(Builder $query): Builder
    {
        return $query->where('assignment_month', now()->startOfMonth()->toDateString());
    }

    /**
     * @param  Builder<ClientAgentAssignment>  $query
     * @return Builder<ClientAgentAssignment>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
