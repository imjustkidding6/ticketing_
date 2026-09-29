<?php

namespace App\Models\Traits;

use Illuminate\Contracts\Database\Query\Builder;

trait OrdersByPriority
{
    /**
     * Order by `priority` the one way this feature means it: **ascending, with 0 last**.
     *
     * The requirement's own worked example is Software Triage 1, Debugger 20, QA 90 with
     * triage first, so a lower number is more important. But `priority` defaults to 0,
     * and a plain ascending sort would put every agent nobody has ranked ahead of the
     * triage agent somebody did — so 0 is read as "unranked" and sorted last:
     *
     *     ORDER BY priority = 0, priority ASC, <tie-break>
     *
     * One implementation on purpose. The same rule governs `ai_agents.priority` and
     * `department_ai_agent.priority`, and the two silently disagreeing is the kind of
     * ordering bug that only surfaces packages later.
     *
     * @param  Builder  $query
     */
    public static function applyPriorityOrder($query, ?string $column = null)
    {
        $column = $column ?? (new static)->qualifyColumn('priority');

        return $query->orderByRaw("$column = 0")->orderBy($column);
    }
}
