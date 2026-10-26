<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\LogsActivity;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use BelongsToTenant, HasFactory, LogsActivity;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'email',
        'color',
        'is_active',
        'is_default',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the categories for this department.
     */
    public function categories(): HasMany
    {
        return $this->hasMany(TicketCategory::class);
    }

    /**
     * Get the tickets for this department.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * The AI agents assigned to this department.
     */
    public function aiAgents(): BelongsToMany
    {
        return $this->belongsToMany(AiAgent::class, 'department_ai_agent')
            ->using(DepartmentAiAgent::class)
            ->withPivot(['id', 'tenant_id', 'role', 'priority', 'is_active', 'auto_invoke'])
            ->withTimestamps();
    }

    /**
     * The assigned agents in the order they should be offered.
     *
     * Assignment priority first, then the agent's own, both by the one rule in
     * OrdersByPriority (ascending, 0 last), then name. Supplied here rather than left to
     * each reader: the pivot priority and the agent priority sorting different ways round
     * is the ordering bug nobody notices until a customer disagrees with a list.
     */
    public function aiAgentsRanked(): BelongsToMany
    {
        $relation = $this->aiAgents();

        DepartmentAiAgent::applyPriorityOrder($relation, 'department_ai_agent.priority');
        AiAgent::applyPriorityOrder($relation, 'ai_agents.priority');

        return $relation->orderBy('ai_agents.name');
    }

    /**
     * Assign an AI agent to this department, stamping the tenant.
     *
     * The sanctioned pivot write: attach() fires no model events, so nothing would
     * fill tenant_id (NOT NULL, so a raw attach fails loudly rather than writing an
     * unscoped row) and the assignment would never reach the activity log. Keyed on
     * (department, agent) via updateOrCreate, so re-assigning updates rather than
     * duplicating and a re-seed is idempotent.
     */
    public function assignAiAgent(
        AiAgent $agent,
        string $role = 'support',
        int $priority = 0,
        bool $isActive = true,
        bool $autoInvoke = false,
    ): DepartmentAiAgent {
        if ($agent->tenant_id !== $this->tenant_id) {
            throw new InvalidArgumentException(
                "Cannot assign an AI agent from another workspace to department [{$this->getKey()}]."
            );
        }

        if (! in_array($role, DepartmentAiAgent::ROLES, true)) {
            throw new InvalidArgumentException("Unknown department agent role [{$role}].");
        }

        return DepartmentAiAgent::updateOrCreate(
            ['department_id' => $this->getKey(), 'ai_agent_id' => $agent->getKey()],
            [
                'tenant_id' => $this->tenant_id,
                'role' => $role,
                'priority' => $priority,
                'is_active' => $isActive,
                'auto_invoke' => $autoInvoke,
            ],
        );
    }

    /**
     * Remove an AI agent from this department.
     *
     * Through the pivot model so the removal is logged, and a hard delete so the
     * unique index does not block re-assigning the same agent later.
     */
    public function removeAiAgent(AiAgent $agent): void
    {
        DepartmentAiAgent::query()
            ->where('department_id', $this->getKey())
            ->where('ai_agent_id', $agent->getKey())
            ->get()
            ->each->delete();
    }

    /**
     * Scope a query to only include active departments.
     *
     * @param  Builder<Department>  $query
     * @return Builder<Department>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to order by sort_order.
     *
     * @param  Builder<Department>  $query
     * @return Builder<Department>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
