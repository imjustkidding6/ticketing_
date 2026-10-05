<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresAiAgentsEnabled;
use App\Http\Requests\SyncAiAgentSkillsRequest;
use App\Models\AiAgent;
use App\Models\Skill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of the `ai_agent_skill` pivot from the UI (spec §7).
 *
 * Separate from AiAgentController because it is the one action in the feature that writes
 * a pivot and the one wrapped in a transaction — keeping it apart stops the agent
 * controller acquiring pivot knowledge a later package would then duplicate.
 */
class AiAgentSkillController extends Controller
{
    use RequiresAiAgentsEnabled;

    /**
     * Save the whole skills table for one agent.
     *
     * **`AiAgent::syncSkills()` does not detach.** It loops `attachSkill()`, which is an
     * `updateOrCreate` — deliberate, so a catalogue re-install cannot revert an
     * administrator's tuning. So a "save" that only calls it ships a Remove checkbox that
     * silently does nothing: the row saves, the page reloads, the skill is still there.
     * The removals are therefore computed and applied here, in the same transaction, and
     * through detachSkill() so the pivot's deleted event writes the readable audit label.
     */
    public function sync(SyncAiAgentSkillsRequest $request, AiAgent $ai_agent): RedirectResponse
    {
        $this->checkPermission('manage ai agents');
        $this->ensureAiAgentsEnabled();

        $rows = $request->validated()['skills'] ?? [];

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['skill_id']] = [
                'proficiency' => $row['proficiency'],
                'weight' => $row['weight'] ?? null,
                'is_required' => (bool) ($row['is_required'] ?? false),
            ];
        }

        DB::transaction(function () use ($ai_agent, $map): void {
            $ai_agent->syncSkills($map);

            // The other half of "sync". Anything the agent holds that the payload omits
            // is a removal the administrator asked for.
            $removed = $ai_agent->skills()
                ->whereNotIn('skills.id', array_keys($map) ?: [0])
                ->get();

            foreach ($removed as $skill) {
                // detachSkill(), never a raw detach(): the latter fires no events, so the
                // audit row an administrator needs is never written.
                $ai_agent->detachSkill($skill);
            }
        });

        return redirect()->route('ai-agents.edit', $ai_agent)
            ->with('success', 'Skills updated successfully.');
    }

    /**
     * Remove one skill from one agent.
     */
    public function destroy(AiAgent $ai_agent, Skill $skill): RedirectResponse
    {
        $this->checkPermission('manage ai agents');
        $this->ensureAiAgentsEnabled();

        $ai_agent->detachSkill($skill);

        return redirect()->route('ai-agents.edit', $ai_agent)
            ->with('success', "Removed \"{$skill->name}\" from {$ai_agent->name}.");
    }
}
