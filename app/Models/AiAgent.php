<?php

namespace App\Models;

use App\Exceptions\AiAgentInUseException;
use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\LogsActivity;
use App\Models\Traits\OrdersByPriority;
use Database\Factories\AiAgentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;

/**
 * A configured AI specialist: a role, a set of skills and an optional system prompt.
 *
 * A configuration row, nothing more — this feature never executes an agent. Model
 * parameters (model name, temperature, max_tokens, enabled_tools) stay on
 * ai_profiles, which AiProfileService already owns; the agent references one rather
 * than restating it.
 */
class AiAgent extends Model
{
    /** @use HasFactory<AiAgentFactory> */
    use BelongsToTenant, HasFactory, LogsActivity, OrdersByPriority;

    /**
     * varchar in the database; this constant is the only enumeration, so adding a
     * role is a code change with no migration (the tickets.status precedent).
     *
     * @var list<string>
     */
    public const ROLE_TYPES = [
        'orchestrator',
        'triage',
        'specialist',
        'analyst',
        'validator',
        'documentation',
        'router',
    ];

    /**
     * The base prompt every agent uses unless an administrator overrides it.
     *
     * Held here rather than copied into each seeded row: storing the text per row
     * would freeze it at seed time, so a wording fix would never reach an existing
     * workspace. A NULL system_prompt means "use this".
     */
    public const DEFAULT_SYSTEM_PROMPT = 'You are an AI specialist working inside a business ticketing system. '
        .'Your responsibility is to analyze the ticket only within your assigned role and skills. '
        .'Do not invent information that is not present in the ticket. Clearly distinguish confirmed '
        .'information from assumptions. If information required for proper analysis is missing, identify '
        .'what information is needed. Your output should be concise, structured, actionable, and appropriate '
        .'for a human support agent. Do not directly modify business records or perform irreversible actions '
        .'unless the system explicitly authorizes that action.';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'ai_profile_id',
        'name',
        'slug',
        'description',
        'role_type',
        'system_prompt',
        'is_active',
        'is_default',
        'priority',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'priority' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Enforced on the write, not in a controller, so every entry point obeys it.
        static::deleting(function (self $agent) {
            $blockers = $agent->deletionBlockers();

            if ($blockers !== []) {
                throw new AiAgentInUseException($agent->name, $blockers);
            }
        });
    }

    /**
     * The model-parameter preset this agent runs under, if the workspace picked one.
     */
    public function aiProfile(): BelongsTo
    {
        return $this->belongsTo(AiProfile::class);
    }

    /**
     * The skills this agent holds, with proficiency.
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'ai_agent_skill')
            ->using(AiAgentSkill::class)
            ->withPivot(['id', 'tenant_id', 'proficiency', 'weight', 'is_required'])
            ->withTimestamps();
    }

    /**
     * The departments this agent is assigned to.
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_ai_agent')
            ->using(DepartmentAiAgent::class)
            ->withPivot(['id', 'tenant_id', 'role', 'priority', 'is_active', 'auto_invoke'])
            ->withTimestamps();
    }

    /**
     * Attach a skill, stamping the tenant and refusing a cross-tenant pair.
     *
     * The sanctioned pivot write: attach()/sync() fire no model events, so nothing
     * would fill tenant_id (NOT NULL, so that fails loudly) and nothing would write
     * the activity log entry an administrator needs to see. Writing through the
     * pivot model gets both.
     */
    public function attachSkill(
        Skill $skill,
        string $proficiency = 'intermediate',
        ?int $weight = null,
        bool $isRequired = false,
    ): AiAgentSkill {
        $this->assertSameTenant($skill->tenant_id, 'skill');

        if (! in_array($proficiency, AiAgentSkill::PROFICIENCIES, true)) {
            throw new InvalidArgumentException("Unknown proficiency [{$proficiency}].");
        }

        return AiAgentSkill::updateOrCreate(
            ['ai_agent_id' => $this->getKey(), 'skill_id' => $skill->getKey()],
            [
                'tenant_id' => $this->tenant_id,
                'proficiency' => $proficiency,
                'weight' => $weight,
                'is_required' => $isRequired,
            ],
        );
    }

    /**
     * Remove a skill from this agent.
     *
     * Goes through the pivot model so the removal is logged; a raw detach() would
     * delete the row silently. A hard delete, deliberately: the unique index would
     * otherwise block re-adding the skill.
     */
    public function detachSkill(Skill $skill): void
    {
        AiAgentSkill::query()
            ->where('ai_agent_id', $this->getKey())
            ->where('skill_id', $skill->getKey())
            ->get()
            ->each->delete();
    }

    /**
     * Attach or update many skills at once, leaving any not mentioned in place.
     *
     * syncWithoutDetaching semantics on purpose: P2's seeder re-runs, and an
     * administrator's own additions have to survive a re-seed.
     *
     * @param  array<int, array<string, mixed>|string>  $skillIdToPivot  skill id => pivot attributes, or id => proficiency
     */
    public function syncSkills(array $skillIdToPivot): void
    {
        // Scope-free but explicitly pinned to this agent's own workspace, so a seeder
        // running without a session resolves ids and a foreign id resolves to nothing.
        $skills = Skill::query()
            ->withoutGlobalScopes()->where('tenant_id', $this->tenant_id)
            ->whereIn('id', array_keys($skillIdToPivot))
            ->get()
            ->keyBy('id');

        foreach ($skillIdToPivot as $skillId => $pivot) {
            $skill = $skills->get($skillId);

            if (! $skill) {
                throw new InvalidArgumentException("Unknown skill [{$skillId}].");
            }

            $pivot = is_string($pivot) ? ['proficiency' => $pivot] : $pivot;

            $this->attachSkill(
                $skill,
                $pivot['proficiency'] ?? 'intermediate',
                $pivot['weight'] ?? null,
                (bool) ($pivot['is_required'] ?? false),
            );
        }
    }

    /**
     * The prompt this agent actually runs with.
     */
    public function effectiveSystemPrompt(): string
    {
        return filled($this->system_prompt) ? $this->system_prompt : self::DEFAULT_SYSTEM_PROMPT;
    }

    /**
     * The departments that block deletion, for the UI to name them.
     *
     * An agent's own skill pivots are not a blocker — they are its private
     * configuration and cascade away with it. See Skill::deletionBlockers() for why
     * the global scope is replaced by an explicit tenant predicate.
     *
     * @return list<string>
     */
    public function deletionBlockers(): array
    {
        return $this->departments()
            ->withoutGlobalScopes()->where('departments.tenant_id', $this->tenant_id)
            ->orderBy('departments.name')
            ->pluck('departments.name')
            ->all();
    }

    /**
     * @param  Builder<AiAgent>  $query
     * @return Builder<AiAgent>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Most important first: priority ascending with 0 last, then name.
     *
     * The direction is the requirement's (Triage 1, Debugger 20, QA 90, triage first)
     * and the 0-last part keeps an unranked agent behind a ranked one. `name` is the
     * deterministic tie-break, so two agents on the same priority never swap places
     * between two identical requests. See OrdersByPriority.
     *
     * @param  Builder<AiAgent>  $query
     * @return Builder<AiAgent>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return self::applyPriorityOrder($query, 'ai_agents.priority')->orderBy('ai_agents.name');
    }

    /**
     * Refuse to pair this agent with a record from another workspace.
     */
    private function assertSameTenant(?int $tenantId, string $what): void
    {
        if ($tenantId !== $this->tenant_id) {
            throw new InvalidArgumentException(
                "Cannot attach a {$what} from another workspace to AI agent [{$this->getKey()}]."
            );
        }
    }
}
