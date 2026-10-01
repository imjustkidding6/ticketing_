<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresAiAgentsEnabled;
use App\Http\Requests\StoreDepartmentAiAgentRequest;
use App\Http\Requests\UpdateDepartmentAiAgentRequest;
use App\Models\AiAgent;
use App\Models\AppSetting;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The AI Agents tab on a department (spec §8, §33).
 *
 * Assignment is configured here rather than in a module of its own: a department's roster
 * is a property of the department, and the human settled it that way. The agent and skill
 * definition screens stay where they are — a skill is reusable across agents and
 * departments, so it cannot live inside one department's page — and this tab links to them.
 *
 * **No route resolves a `department_ai_agent` row by its own id.** The pivot carries
 * `tenant_id` but not BelongsToTenant, so `DepartmentAiAgent::find($id)` is unscoped and an
 * `.../assignments/{assignment}` route shape would be a cross-tenant IDOR. Every action here
 * is keyed on the two parents instead — `{department}` and `{ai_agent}` are both
 * BelongsToTenant, so another workspace's id 404s in the router before any code runs, and
 * the assignment is then looked up through the department's own relation.
 */
class DepartmentAiAgentController extends Controller
{
    use RequiresAiAgentsEnabled;

    /**
     * The tab itself.
     *
     * Readable with `update departments` (this is the department edit screen); changing the
     * roster needs `manage ai agents`, the permission the definition screens already use.
     */
    public function index(Department $department): View
    {
        $this->checkPermission('update departments');

        $canManage = $this->userCan('manage ai agents');
        $enabled = (bool) AppSetting::get('ai_agents_enabled', false);

        return view('departments.ai-agents', [
            'department' => $department,
            'canManage' => $canManage,
            'aiAgentsEnabled' => $enabled,
            'assignments' => $department->aiAgentsRanked()->get(),
            // One at a time, from the agents not already assigned. Inactive agents are
            // offered: an admin may stage a roster before switching the agent on.
            'assignableAgents' => $canManage && $enabled
                ? AiAgent::query()
                    ->whereNotIn('id', $department->aiAgents()->pluck('ai_agents.id'))
                    ->ordered()
                    ->get(['id', 'name', 'role_type'])
                : collect(),
        ]);
    }

    public function store(StoreDepartmentAiAgentRequest $request, Department $department): RedirectResponse
    {
        $this->checkPermission('manage ai agents');
        $this->ensureAiAgentsEnabled();

        $validated = $request->validated();
        $agent = AiAgent::query()->findOrFail($validated['ai_agent_id']);

        // assignAiAgent(), never attach(): it stamps tenant_id (the column is NOT NULL, so a
        // raw attach dies), refuses a cross-tenant pair, and goes through the pivot model so
        // the assignment reaches the activity log with a readable label.
        $department->assignAiAgent(
            $agent,
            $validated['role'],
            (int) ($validated['priority'] ?? 0),
            (bool) ($validated['is_active'] ?? false),
            (bool) ($validated['auto_invoke'] ?? false),
        );

        return redirect()->route('departments.ai-agents.index', $department)
            ->with('success', "Assigned \"{$agent->name}\" to {$department->name}.");
    }

    /**
     * Change one assignment's role, priority, active and auto-invoke flags.
     *
     * assignAiAgent() is an updateOrCreate, which resets all four — exactly right for a form
     * that edits all four, and the reason this is not used by anything that merely re-adds.
     */
    public function update(UpdateDepartmentAiAgentRequest $request, Department $department, AiAgent $ai_agent): RedirectResponse
    {
        $this->checkPermission('manage ai agents');
        $this->ensureAiAgentsEnabled();

        $this->ensureAssigned($department, $ai_agent);

        $validated = $request->validated();

        $department->assignAiAgent(
            $ai_agent,
            $validated['role'],
            (int) ($validated['priority'] ?? 0),
            (bool) ($validated['is_active'] ?? false),
            (bool) ($validated['auto_invoke'] ?? false),
        );

        return redirect()->route('departments.ai-agents.index', $department)
            ->with('success', "Updated \"{$ai_agent->name}\" for {$department->name}.");
    }

    public function destroy(Department $department, AiAgent $ai_agent): RedirectResponse
    {
        $this->checkPermission('manage ai agents');
        $this->ensureAiAgentsEnabled();

        $this->ensureAssigned($department, $ai_agent);

        // removeAiAgent(), never detach(): a raw detach fires no model events, so the
        // pivot's deleted event never writes the 'Department → Agent' audit row.
        $department->removeAiAgent($ai_agent);

        return redirect()->route('departments.ai-agents.index', $department)
            ->with('success', "Removed \"{$ai_agent->name}\" from {$department->name}.");
    }

    /**
     * Both parents are tenant-scoped, so this is not an isolation check — it is what keeps an
     * edit or a delete against an agent that is not on this department from silently creating
     * an assignment through updateOrCreate.
     */
    private function ensureAssigned(Department $department, AiAgent $agent): void
    {
        abort_unless(
            $department->aiAgents()->whereKey($agent->getKey())->exists(),
            404,
            'That agent is not assigned to this department.',
        );
    }
}
