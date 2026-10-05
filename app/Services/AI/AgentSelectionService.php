<?php

namespace App\Services\AI;

use App\Models\AiAgent;
use App\Models\Department;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Reads a department's AI-agent configuration. Nothing else.
 *
 * Every method here is a query. The service writes nothing, calls no HTTP endpoint and
 * makes no OpenAI request — §4/§30 of the requirement hold for this package, and P6 is
 * the one that spends a token. The architecture is the one §40 insists on:
 * Department → department_ai_agent → AiAgent → ai_agent_skill → Skill, read from the
 * database. No department code, name or id appears anywhere below.
 *
 * **It takes a Department, not a Ticket** (§29 says Ticket). P6's first caller is the
 * "Polish with Nexus AI" panel on the ticket *create* form, where no ticket exists yet
 * and the department is a selected form value; a Ticket parameter would force that caller
 * to fabricate one. A ticket's department is one `->department` away, so the Ticket
 * signature can be added as a thin wrapper if anything ever wants it. This also keeps the
 * service away from `Ticket` entirely, which the non-goals asked for.
 *
 * **The plan gate and the per-tenant opt-in are the caller's.** `feature:ai_agents` is
 * route middleware and `RequiresAiAgentsEnabled::ensureAiAgentsEnabled()` reads
 * `AppSetting::get()`, which resolves the workspace from `session('current_tenant_id')`
 * and silently returns the default when there is none — so a service that may legitimately
 * be called from a command, a seeder or a queue worker cannot consult either and must not
 * pretend to. P3's concern says so explicitly. P6 therefore gates its *routes* and calls
 * this freely; it must not expect a second refusal here, and it must not skip its own.
 */
class AgentSelectionService
{
    /**
     * Weight to score a skill link with when the administrator set none.
     *
     * The seeded catalogue writes `weight = null` on every row, so this is the value
     * nearly every real link scores at today — a ranking that only separates agents
     * because somebody typed weights is a ranking that does not work out of the box.
     */
    private const DEFAULT_WEIGHT = 1;

    /**
     * Multiplier for a skill the administrator marked required.
     *
     * A bonus, never a filter. Treated as a hard filter it would reproduce exactly the
     * failure the prior art warns about — Freshdesk's ticket that "remains unassigned"
     * because nothing matched — and this service's contract is that a configured
     * department never returns nothing.
     */
    private const REQUIRED_SKILL_MULTIPLIER = 2;

    /**
     * The agents assigned to this department, most important first.
     *
     * @return Collection<int, AiAgent>
     */
    public function getDepartmentAgents(Department $department): Collection
    {
        return $this->assignedAgents($department)->get();
    }

    /**
     * The assigned agents the department wants invoked without being asked.
     *
     * @return Collection<int, AiAgent>
     */
    public function getAutoInvokeAgents(Department $department): Collection
    {
        return $this->assignedAgents($department)
            ->where('department_ai_agent.auto_invoke', true)
            ->get();
    }

    /**
     * Every active skill the department's active agents hold, each once.
     *
     * De-duplicated on the skill id, not on the name: two skills may share a display
     * name across categories, and the id is what the pivot joins on. Sorted in memory on
     * (category, name) so two identical calls produce the same list — the union of several
     * agents' individually ordered skill lists has no order of its own, so no `ORDER BY`
     * can produce this. It deliberately matches what `Skill::scopeOrdered()` does, which
     * means the rule now exists in two places: change one and change the other.
     *
     * @return Collection<int, Skill>
     */
    public function getAvailableSkills(Department $department): Collection
    {
        return $this->assignedAgents($department)
            ->with(['skills' => fn ($query) => $this->activeSkills($query, $department)])
            ->get()
            ->flatMap->skills
            ->unique('id')
            ->sortBy([['skill_category', 'asc'], ['name', 'asc']])
            ->values();
    }

    /**
     * Rank the department's agents against a piece of free text.
     *
     * **This is lexical matching, not semantic matching.** There is no embedding call and
     * no model call anywhere in this package: the text is lowercased, stripped to words,
     * and each agent's skills are scored by whether the skill's own slug or name appears
     * in it as a phrase. "mysql query optimisation" will not match a `postgres` skill no
     * matter how close the meaning, and "the database is slow" matches nothing at all.
     * It misfires the other way too, which is the more dangerous direction: the catalogue
     * holds `Documentation`, `Debugging`, `Deployment`, `Networking` and `Git` as
     * whole-word skill names, so "the documentation for the payroll export is missing"
     * ranks the documentation specialist against what is actually a backend bug. Read the
     * list as a suggestion to an engineer, never as a classification.
     * Named the way `SemanticCacheService` names itself as lexical, and for the same
     * reason: a caller that believes this understands the request will trust a list it
     * should be reading sceptically.
     *
     * The score for one agent is, over its matched skills:
     *
     *     Σ proficiency_rank × max(weight, 1) × (is_required ? 2 : 1)
     *
     * — every factor a value an administrator can see on the agent edit screen, so a
     * customer who disagrees with the list can be shown why it came out that way.
     *
     * **Deterministic by construction.** Ties are broken on the agent's position in
     * `getDepartmentAgents()` (assignment priority, then agent priority, then name), which
     * is captured as an index *before* sorting rather than left to the sort being stable.
     * Two identical requests cannot return two orders.
     *
     * **It never returns an empty list for a configured department.** When nothing matches
     * — an empty string, prose with no skill word in it, a department whose agents hold no
     * skills — the department's own ranking is the answer, with any `fallback`-role
     * assignment brought to the front. That is what `DepartmentAiAgent::ROLES`' `fallback`
     * is for, and it is the one documented failure of every product in this space.
     *
     * Each returned agent carries two presentation attributes, `recommendation_score` and
     * `matched_skills` (a list of slugs), so the caller can explain the ranking. They are
     * not columns: **never save a model returned from here** — Eloquent would try to write
     * them.
     *
     * @return Collection<int, AiAgent>
     */
    public function recommendAgents(Department $department, string $text, int $limit = 3): Collection
    {
        $limit = max(1, $limit);

        $agents = $this->assignedAgents($department)
            ->with(['skills' => fn ($query) => $this->activeSkills($query, $department)])
            ->get()
            ->values();

        if ($agents->isEmpty()) {
            return $agents;
        }

        $haystack = $this->normalise($text);

        $scored = $agents->map(function (AiAgent $agent, int $rank) use ($haystack) {
            $matched = $haystack === ''
                ? collect()
                : $agent->skills->filter(fn (Skill $skill) => $this->mentions($haystack, $skill));

            $agent->recommendation_score = (int) $matched->sum(fn (Skill $skill) => $this->scoreFor($skill));
            $agent->matched_skills = $matched->pluck('slug')->values()->all();

            return ['agent' => $agent, 'rank' => $rank];
        });

        $matching = $scored->filter(fn (array $row) => $row['agent']->recommendation_score > 0);

        if ($matching->isEmpty()) {
            return $this->fallbackFor($agents, $limit);
        }

        return $matching
            ->sortBy([
                fn (array $a, array $b) => $b['agent']->recommendation_score <=> $a['agent']->recommendation_score,
                fn (array $a, array $b) => $a['rank'] <=> $b['rank'],
            ])
            ->take($limit)
            ->pluck('agent')
            ->values();
    }

    /**
     * The one query every method above starts from.
     *
     * **"Active" is two flags, and both of them have to be true.** `ai_agents.is_active`
     * is the agent being switched on at all; `department_ai_agent.is_active` is *this*
     * department still using it. They are independent decisions — an administrator
     * retires an agent globally, or stands one down in one department while another keeps
     * it — and a reader who filters only one gets a list that looks entirely plausible and
     * is wrong. Criteria 18 and 19 are the two halves, deliberately asserted in both
     * directions.
     *
     * Ordering comes from `Department::aiAgentsRanked()` and is not re-derived here. That
     * method exists so the pivot priority and the agent priority cannot sort different
     * ways round (`OrdersByPriority`: ascending, 0 last, name as the tie-break); a
     * hand-rolled `orderBy` in this service would be the second implementation it was
     * written to prevent.
     *
     * The explicit tenant predicate replaces the global scope rather than adding to it:
     * `TenantScope` is a no-op without an authenticated session, and this service is
     * reachable from a command or a queue worker where there is none. Pinning to the
     * *department's* own tenant_id — the shape `AiAgent::deletionBlockers()` uses — means
     * the filter holds with or without a session and fails closed, instead of quietly
     * degrading to the pivot join being the only thing keeping workspaces apart.
     */
    private function assignedAgents(Department $department): BelongsToMany
    {
        return $department->aiAgentsRanked()
            ->withoutGlobalScopes()
            ->where('ai_agents.tenant_id', $department->tenant_id)
            ->where('ai_agents.is_active', true)
            ->where('department_ai_agent.is_active', true);
    }

    /**
     * An agent's skills, narrowed the same way: active, and this workspace's.
     *
     * Criterion 3 — a deactivated skill stays attached to its agents and disappears from
     * selection — is enforced here, in the one place both skill-reading methods share.
     *
     * @param  Builder<Skill>  $query
     */
    private function activeSkills($query, Department $department)
    {
        return $query
            ->withoutGlobalScopes()
            ->where('skills.tenant_id', $department->tenant_id)
            ->where('skills.is_active', true);
    }

    /**
     * Lowercase, punctuation to spaces, single-spaced and space-padded.
     *
     * Padded so a phrase test can require word boundaries with plain `str_contains`:
     * without it, "sql" matches "mysql" and a Documentation agent outranks a Database one
     * on a coincidence of substrings.
     */
    private function normalise(string $text): string
    {
        $text = Str::lower(Str::ascii($text));
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? '';

        return trim($text) === '' ? '' : ' '.trim(preg_replace('/\s+/', ' ', $text) ?? '').' ';
    }

    /**
     * Does the text name this skill — by slug or by display name, whole words only?
     */
    private function mentions(string $haystack, Skill $skill): bool
    {
        foreach ([$skill->slug, $skill->name] as $label) {
            $needle = $this->normalise((string) $label);

            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * One matched skill's contribution, read entirely off the pivot row.
     */
    private function scoreFor(Skill $skill): int
    {
        $pivot = $skill->pivot;

        $weight = max((int) ($pivot->weight ?? self::DEFAULT_WEIGHT), self::DEFAULT_WEIGHT);
        $required = $pivot->is_required ? self::REQUIRED_SKILL_MULTIPLIER : 1;

        return $pivot->proficiencyRank() * $weight * $required;
    }

    /**
     * Nothing matched: the department's own ranking, fallback assignments first.
     *
     * The scores stay at 0 and `matched_skills` stays empty, so a caller can tell a
     * fallback list from a matched one and say so rather than presenting a guess as a
     * finding.
     *
     * @param  Collection<int, AiAgent>  $agents
     * @return Collection<int, AiAgent>
     */
    private function fallbackFor(Collection $agents, int $limit): Collection
    {
        return $agents
            ->sortBy(fn (AiAgent $agent, int $rank) => [$agent->pivot->role === 'fallback' ? 0 : 1, $rank])
            ->take($limit)
            ->values();
    }
}
