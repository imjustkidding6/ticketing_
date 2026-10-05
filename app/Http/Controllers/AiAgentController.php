<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresAiAgentsEnabled;
use App\Http\Requests\StoreAiAgentRequest;
use App\Http\Requests\UpdateAiAgentRequest;
use App\Models\AiAgent;
use App\Models\AiProfile;
use App\Models\Skill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use RuntimeException;

/**
 * The agent roster an administrator curates (spec §6, §33).
 *
 * The edit screen is the detail screen: General Information, Skills and System Prompt on
 * one page. Department Assignments is a later package's card on the same view.
 */
class AiAgentController extends Controller
{
    use RequiresAiAgentsEnabled;

    public function index(Request $request): View
    {
        $this->checkPermission('manage ai agents');
        $this->ensureAiAgentsEnabled();

        $agents = AiAgent::query()
            ->withCount(['skills', 'departments'])
            ->when($request->search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->when($request->filled('role_type'), fn ($q) => $q->where('role_type', $request->input('role_type')))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->input('status') === 'active'))
            // scopeOrdered(), never a local orderBy('priority'): ascending with 0 last is
            // one rule and OrdersByPriority is its one implementation.
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('ai-agents.index', ['agents' => $agents]);
    }

    public function create(): View
    {
        $this->checkPermission('manage ai agents');
        $this->ensureAiAgentsEnabled();

        return view('ai-agents.create', ['profiles' => $this->profiles()]);
    }

    public function store(StoreAiAgentRequest $request): RedirectResponse
    {
        $this->checkPermission('manage ai agents');
        $this->ensureAiAgentsEnabled();

        $agent = AiAgent::create($request->validated());

        // The skills editor lives on the edit screen, so a new agent lands there.
        return redirect()->route('ai-agents.edit', $agent)
            ->with('success', 'AI agent created. Add its skills below.');
    }

    public function edit(AiAgent $ai_agent): View
    {
        $this->checkPermission('manage ai agents');
        $this->ensureAiAgentsEnabled();

        $ai_agent->load('skills');

        return view('ai-agents.edit', [
            'agent' => $ai_agent,
            'profiles' => $this->profiles(),
            // A grouped single <select> of the skills not yet held — one at a time, never
            // a 214-option multi-select. Inactive skills are excluded: deactivating a
            // skill keeps existing pivots but takes it out of the picker.
            'availableSkills' => Skill::query()
                ->active()
                ->whereNotIn('id', $ai_agent->skills->pluck('id'))
                ->ordered()
                ->get(['id', 'name', 'skill_category']),
            'departmentNames' => $ai_agent->deletionBlockers(),
        ]);
    }

    public function update(UpdateAiAgentRequest $request, AiAgent $ai_agent): RedirectResponse
    {
        $this->checkPermission('manage ai agents');
        $this->ensureAiAgentsEnabled();

        $ai_agent->update($request->validated());

        return redirect()->route('ai-agents.edit', $ai_agent)
            ->with('success', 'AI agent updated successfully.');
    }

    public function toggle(AiAgent $ai_agent): RedirectResponse
    {
        $this->checkPermission('manage ai agents');
        $this->ensureAiAgentsEnabled();

        $ai_agent->update(['is_active' => ! $ai_agent->is_active]);

        return back()->with('success', $ai_agent->is_active
            ? "Agent \"{$ai_agent->name}\" activated."
            : "Agent \"{$ai_agent->name}\" deactivated.");
    }

    public function destroy(AiAgent $ai_agent): RedirectResponse
    {
        $this->checkPermission('manage ai agents');
        $this->ensureAiAgentsEnabled();

        if ($blockers = $ai_agent->deletionBlockers()) {
            return back()->withErrors([
                'delete' => "Cannot delete \"{$ai_agent->name}\" — it is still assigned to: ".implode(', ', $blockers).'.',
            ]);
        }

        try {
            // Never AiAgent::query()->whereKey(...)->delete(): the deleting guard is a
            // model event and a builder delete would remove an assigned agent silently.
            $ai_agent->delete();
        } catch (RuntimeException $e) {
            return back()->withErrors(['delete' => $e->getMessage()]);
        }

        return redirect()->route('ai-agents.index')->with('success', 'AI agent deleted successfully.');
    }

    /**
     * The model-parameter presets. Global platform configuration, not tenant-owned.
     *
     * @return Collection<int, AiProfile>
     */
    private function profiles()
    {
        return AiProfile::query()->orderBy('name')->get(['id', 'name']);
    }
}
