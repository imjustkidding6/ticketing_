<?php

namespace App\Models;

use App\Exceptions\SkillInUseException;
use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\LogsActivity;
use Database\Factories\SkillFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A capability an AI agent can hold — "MySQL", "Root Cause Analysis".
 *
 * Per-workspace, not global (human decision 2026-09-30 Q1): an administrator
 * renaming their "MySQL" skill must not rename another customer's. The
 * `unique(tenant_id, slug)` index is therefore the join key for a detected skill
 * label, and the only one — there is no synonym table and no name matching.
 */
class Skill extends Model
{
    /** @use HasFactory<SkillFactory> */
    use BelongsToTenant, HasFactory, LogsActivity;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'skill_category',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // In the model rather than a controller so the rule holds for every entry
        // point — the admin screen, a seeder, tinker, a future API. The controller
        // asks deletionBlockers() first to render a validation error; this is the
        // backstop, not the UX.
        static::deleting(function (self $skill) {
            $blockers = $skill->deletionBlockers();

            if ($blockers !== []) {
                throw new SkillInUseException($skill->name, $blockers);
            }
        });
    }

    /**
     * The agents holding this skill.
     */
    public function aiAgents(): BelongsToMany
    {
        return $this->belongsToMany(AiAgent::class, 'ai_agent_skill')
            ->using(AiAgentSkill::class)
            ->withPivot(['id', 'tenant_id', 'proficiency', 'weight', 'is_required'])
            ->withTimestamps();
    }

    /**
     * The names of the agents that block deletion, for the UI to name them.
     *
     * "Safe to delete" means zero referencing rows — an inactive agent counts,
     * because deleting the skill would silently edit that agent's configuration.
     * The global scope is swapped for an explicit predicate on this skill's own
     * tenant_id rather than the session's: a guard consulted from a seeder or tinker
     * must fail closed, not find nothing and wave the delete through.
     *
     * @return list<string>
     */
    public function deletionBlockers(): array
    {
        return $this->aiAgents()
            ->withoutGlobalScopes()->where('ai_agents.tenant_id', $this->tenant_id)
            ->orderBy('ai_agents.name')
            ->pluck('ai_agents.name')
            ->all();
    }

    /**
     * @param  Builder<Skill>  $query
     * @return Builder<Skill>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Skill>  $query
     * @return Builder<Skill>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('skill_category')->orderBy('name');
    }
}
