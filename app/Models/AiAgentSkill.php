<?php

namespace App\Models;

use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * An agent's claim on a skill, with how well it holds it.
 *
 * A real Pivot model with an id and timestamps rather than an anonymous join row:
 * "skill attached to agent" is a configuration change an administrator has to be
 * able to find in the audit log, and LogsActivity needs a key to hang the subject off.
 */
class AiAgentSkill extends Pivot
{
    use LogsActivity;

    protected $table = 'ai_agent_skill';

    public $incrementing = true;

    /**
     * varchar in the database, so this constant is the only enumeration — adding a
     * level is a code change with no migration (the tickets.status precedent).
     *
     * @var list<string>
     */
    public const PROFICIENCIES = ['basic', 'intermediate', 'advanced', 'expert'];

    /**
     * Rank the levels for P5's scoring. Ordering on the map, never on the string:
     * alphabetically 'expert' sorts below 'intermediate'.
     *
     * @var array<string, int>
     */
    public const PROFICIENCY_RANK = [
        'basic' => 1,
        'intermediate' => 2,
        'advanced' => 3,
        'expert' => 4,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'ai_agent_id',
        'skill_id',
        'proficiency',
        'weight',
        'is_required',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'is_required' => 'boolean',
        ];
    }

    public function aiAgent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class);
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    /**
     * What this row is called on the audit-log screen.
     *
     * ActivityLogger::labelFor() looks for ticket_number|name|subject|title|email and a
     * pivot has none of them, so without this the screen reads "AiAgentSkill #1 deleted."
     * — and because the row is hard-deleted, the id resolves to nothing afterwards and
     * the entry is permanently unreadable. The two parents are what an administrator is
     * actually auditing, so the label names them.
     */
    public function getNameAttribute(): string
    {
        return sprintf(
            '%s → %s',
            $this->aiAgent?->name ?? 'AI agent #'.$this->ai_agent_id,
            $this->skill?->name ?? 'skill #'.$this->skill_id,
        );
    }

    /**
     * How strongly this agent holds the skill, for ranking.
     */
    public function proficiencyRank(): int
    {
        return self::PROFICIENCY_RANK[$this->proficiency] ?? 0;
    }
}
