<?php

namespace App\Models;

use App\Models\Traits\LogsActivity;
use App\Models\Traits\OrdersByPriority;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A department's assignment of an AI agent, with the part the agent plays there.
 *
 * The same agent can sit in two departments under different roles and priorities,
 * so the role belongs on the assignment and not on the agent.
 */
class DepartmentAiAgent extends Pivot
{
    use LogsActivity, OrdersByPriority;

    protected $table = 'department_ai_agent';

    public $incrementing = true;

    /**
     * varchar in the database; this constant is the only enumeration.
     *
     * 'fallback' is load-bearing rather than decorative: P5 must return a
     * department's agents when nothing matches, and the fallback goes first.
     *
     * @var list<string>
     */
    public const ROLES = ['primary', 'specialist', 'validator', 'support', 'fallback'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'department_id',
        'ai_agent_id',
        'role',
        'priority',
        'is_active',
        'auto_invoke',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'is_active' => 'boolean',
            'auto_invoke' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function aiAgent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class);
    }

    /**
     * What this row is called on the audit-log screen — see AiAgentSkill::getNameAttribute()
     * for why a pivot needs one at all.
     */
    public function getNameAttribute(): string
    {
        return sprintf(
            '%s → %s',
            $this->department?->name ?? 'department #'.$this->department_id,
            $this->aiAgent?->name ?? 'AI agent #'.$this->ai_agent_id,
        );
    }
}
