<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RequiresAiAgentsEnabled;
use App\Models\AiAgent;
use App\Models\AppSetting;
use App\Models\DepartmentAiAgent;
use App\Models\Skill;
use App\Models\Tenant;
use App\Services\AI\AiAgentCatalogueInstaller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The AI agents hub: the opt-in switch, the counts and the catalogue install.
 *
 * The one place in the feature that does **not** require `ai_agents_enabled` — it is where
 * the switch lives, so gating it would make the feature impossible to turn back on. The
 * plan gate (`feature:ai_agents`) and the permission still apply.
 *
 * The three things here are not resource CRUD, so they live apart from SkillController and
 * AiAgentController rather than making either of them grow a settings action.
 */
class AiAgentConfigController extends Controller
{
    use RequiresAiAgentsEnabled;

    public function __construct(private readonly AiAgentCatalogueInstaller $installer) {}

    public function setup(): View
    {
        $this->checkPermission('manage ai agents');

        return view('ai-agents.setup', [
            'enabled' => (bool) AppSetting::get('ai_agents_enabled', false),
            // The command refuses to run without --tenant or --all, so the notice has to carry
            // the id or a copy-paste errors out.
            'tenantId' => $this->currentTenant()->id,
            'skillCount' => Skill::query()->count(),
            'agentCount' => AiAgent::query()->count(),
            // The pivots carry tenant_id but no global scope (they are Pivot models, read
            // through an already-scoped parent everywhere else), so a direct count has to
            // say which workspace it means.
            'assignmentCount' => DepartmentAiAgent::query()
                ->where('tenant_id', session('current_tenant_id'))->count(),
        ]);
    }

    public function saveSetup(Request $request): RedirectResponse
    {
        $this->checkPermission('manage ai agents');

        $validated = $request->validate(['ai_agents_enabled' => ['nullable', 'boolean']]);

        // AppSetting::set() reads session('current_tenant_id') and returns silently
        // without it; this route is always behind the tenant middleware, so it is set.
        AppSetting::set('ai_agents_enabled', (bool) ($validated['ai_agents_enabled'] ?? false), 'boolean', 'ai');

        return redirect()->route('ai-agents.setup')->with('success', 'AI agent settings saved.');
    }

    /**
     * A dry run first, always: a button that writes a few thousand rows should say how
     * many before it does.
     */
    public function cataloguePreview(): View
    {
        $this->checkPermission('manage ai agents');
        // Gated like the resource screens: only the switch itself is exempt.
        $this->ensureAiAgentsEnabled();

        $tenant = $this->currentTenant();

        return view('ai-agents.catalogue-preview', [
            'summary' => $this->installer->installFor($tenant, dryRun: true),
            'tenantId' => $tenant->id,
        ]);
    }

    public function installCatalogue(): RedirectResponse
    {
        $this->checkPermission('manage ai agents');
        $this->ensureAiAgentsEnabled();

        $summary = $this->installer->installFor($this->currentTenant());

        // Create-if-absent, so a second press is safe. Saying "created 0 of everything"
        // would read as a failure; the honest answer is that nothing needed doing.
        $message = $summary->createdNothing()
            ? 'Everything in the default catalogue is already installed — nothing was changed.'
            : $summary->describe();

        return redirect()->route('ai-agents.setup')
            ->with('success', $message)
            ->with('missing_department_codes', $summary->missingDepartmentCodes);
    }

    /**
     * The installer takes a Tenant, which carries no global scope of its own.
     */
    private function currentTenant(): Tenant
    {
        $tenant = Tenant::find(session('current_tenant_id'));

        abort_unless($tenant, 403);

        return $tenant;
    }
}
