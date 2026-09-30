<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresAiAgentsEnabled;
use App\Http\Requests\StoreSkillRequest;
use App\Http\Requests\UpdateSkillRequest;
use App\Models\Skill;
use Database\Seeders\Catalogue\SkillCatalogue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * The skill vocabulary an administrator curates (spec §6).
 *
 * One coarse permission for the whole subsystem, checked first, then the per-workspace
 * opt-in. The plan gate lives on the route.
 */
class SkillController extends Controller
{
    use RequiresAiAgentsEnabled;

    public function index(Request $request): View
    {
        $this->checkPermission('manage ai agents');
        $this->ensureAiAgentsEnabled();

        $skills = Skill::query()
            // withCount, never $skill->aiAgents->count() per row: the catalogue is 214
            // skills and a per-row relation read is 214 queries on one page.
            ->withCount('aiAgents')
            ->when($request->search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->when($request->filled('category'), fn ($q) => $q->where('skill_category', $request->input('category')))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->input('status') === 'active'))
            ->ordered()
            ->paginate(25)
            ->withQueryString();

        return view('skills.index', [
            'skills' => $skills,
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function create(): View
    {
        $this->checkPermission('manage ai agents');
        $this->ensureAiAgentsEnabled();

        return view('skills.create', ['categories' => $this->categoryOptions()]);
    }

    public function store(StoreSkillRequest $request): RedirectResponse
    {
        $this->checkPermission('manage ai agents');
        $this->ensureAiAgentsEnabled();

        Skill::create($request->validated());

        return redirect()->route('skills.index')->with('success', 'Skill created successfully.');
    }

    public function edit(Skill $skill): View
    {
        $this->checkPermission('manage ai agents');
        $this->ensureAiAgentsEnabled();

        return view('skills.edit', [
            'skill' => $skill,
            'categories' => $this->categoryOptions(),
            'agentNames' => $skill->deletionBlockers(),
        ]);
    }

    public function update(UpdateSkillRequest $request, Skill $skill): RedirectResponse
    {
        $this->checkPermission('manage ai agents');
        $this->ensureAiAgentsEnabled();

        $skill->update($request->validated());

        return redirect()->route('skills.index')->with('success', 'Skill updated successfully.');
    }

    /**
     * One-click activate/deactivate. Through update(), so LogsActivity records the diff.
     */
    public function toggle(Skill $skill): RedirectResponse
    {
        $this->checkPermission('manage ai agents');
        $this->ensureAiAgentsEnabled();

        $skill->update(['is_active' => ! $skill->is_active]);

        return back()->with('success', $skill->is_active
            ? "Skill \"{$skill->name}\" activated."
            : "Skill \"{$skill->name}\" deactivated.");
    }

    public function destroy(Skill $skill): RedirectResponse
    {
        $this->checkPermission('manage ai agents');
        $this->ensureAiAgentsEnabled();

        // "Delete when safe" as a message a human reads. The model's deleting guard is
        // the backstop and is caught below, so neither path can reach an exception page.
        if ($blockers = $skill->deletionBlockers()) {
            return back()->withErrors([
                'delete' => "Cannot delete \"{$skill->name}\" — it is still held by: ".implode(', ', $blockers).'.',
            ]);
        }

        try {
            // $skill->delete(), never a builder delete: a builder delete fires no model
            // events and bypasses the guard entirely.
            $skill->delete();
        } catch (RuntimeException $e) {
            return back()->withErrors(['delete' => $e->getMessage()]);
        }

        return redirect()->route('skills.index')->with('success', 'Skill deleted successfully.');
    }

    /**
     * The filter vocabulary: the seeded categories plus whatever this workspace invented.
     *
     * @return list<string>
     */
    private function categoryOptions(): array
    {
        $own = Skill::query()
            ->whereNotNull('skill_category')
            ->distinct()
            ->pluck('skill_category')
            ->all();

        $all = array_unique(array_merge(SkillCatalogue::CATEGORIES, $own));
        sort($all);

        return array_values($all);
    }
}
