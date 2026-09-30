<?php

namespace App\Services\AI;

use App\Models\AiAgent;
use App\Models\Department;
use App\Models\DepartmentAiAgent;
use App\Models\Skill;
use App\Models\Tenant;
use App\Services\ActivityLogger;
use Database\Seeders\Catalogue\AgentCatalogue;
use Database\Seeders\Catalogue\SkillCatalogue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Installs the default skill/agent catalogue into one workspace.
 *
 * The only writer for the catalogue, so the console command, the db:seed wrapper and
 * P3's "install the defaults" button all get identical behaviour rather than three
 * loops that drift (§38: domain logic in a service).
 *
 * **On demand, never at registration.** Several hundred rows per workspace for an
 * Enterprise feature that ships switched off is not something every signup should pay
 * for, and CLAUDE.md asks the registration transaction to stay small. Nor on the
 * feature toggle: a switch that also bulk-writes can half-apply, and then nobody can
 * tell "off" from "on but empty".
 *
 * **Create-if-absent, never update.** Every value the catalogue sets is one an
 * administrator may edit afterwards, so an `updateOrCreate` re-run is a silent revert
 * of their work. The two traps this class exists to avoid, both in helpers whose names
 * suggest otherwise: `AiAgent::syncSkills()` loops `attachSkill()`, which is an
 * `updateOrCreate` and therefore overwrites `proficiency`/`weight`/`is_required` on a
 * pair that already exists; and `Department::assignAiAgent()` is an `updateOrCreate`
 * that resets `role`, `priority`, `is_active` and `auto_invoke`. Both are correct for
 * their own callers. Here they are only ever reached for a pair that does not exist.
 *
 * No plan check: this is a staff action, and a plan downgrade must not make existing
 * configuration unwritable. The gate is on P3's routes and views, where a customer
 * meets the feature. No OpenAI call, no HTTP call of any kind — the catalogue is data.
 */
class AiAgentCatalogueInstaller
{
    /**
     * Every seeded agent-skill link is written at this proficiency, with a null weight.
     *
     * Uniform on purpose: the spec assigns no proficiencies, and inventing ~370 of them
     * would be fabricated configuration an administrator then has to audit — and P5's
     * ranking would be resting on numbers nobody chose.
     */
    private const SEEDED_PROFICIENCY = 'advanced';

    /**
     * Install (or, with $dryRun, report) the catalogue for one workspace.
     */
    public function installFor(Tenant $tenant, bool $dryRun = false): CatalogueInstallSummary
    {
        if ($dryRun) {
            return $this->run($tenant, dryRun: true);
        }

        // One transaction (§38) so a half-installed catalogue is not a state anyone has
        // to reason about. withoutEvents rather than $activityLogDisabled: that property
        // is read from a model *instance*, and firstOrCreate builds its instances
        // internally, so there is nowhere to set it. ~700 "Skill created." rows would
        // bury the first real edit and make the audit screen useless on day one; the one
        // summary entry below is the audit record for an install.
        //
        // Consequence: withoutEvents also silences BelongsToTenant's stamping hook, so
        // every write below passes tenant_id literally. There is no session here anyway.
        $summary = DB::transaction(
            fn (): CatalogueInstallSummary => Model::withoutEvents(
                fn (): CatalogueInstallSummary => $this->run($tenant, dryRun: false),
            ),
        );

        // After the commit, so an aborted install leaves no entry claiming success.
        if (! $summary->createdNothing()) {
            $this->recordInstall($tenant, $summary);
        }

        return $summary;
    }

    /**
     * The one pass over the catalogue. With $dryRun it reads and counts, and writes nothing.
     */
    private function run(Tenant $tenant, bool $dryRun): CatalogueInstallSummary
    {
        $skillIds = $this->existingSkillIds($tenant);
        $skillsCreated = 0;

        foreach (SkillCatalogue::SKILLS as $skill) {
            if (isset($skillIds[$skill['slug']])) {
                continue;
            }

            $skillsCreated++;

            if ($dryRun) {
                continue;
            }

            // Scope-free but pinned to this workspace in the same statement — the seeder
            // form, and the form the isolation guardrail accepts.
            $skillIds[$skill['slug']] = Skill::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $skill['slug']],
                [
                    'name' => $skill['name'],
                    'skill_category' => $skill['skill_category'],
                    'is_active' => true,
                ],
            )->id;
        }

        $departments = $this->departmentsByCode($tenant);
        $agents = $this->existingAgentsBySlug($tenant);

        $agentsCreated = 0;
        $pivotsCreated = 0;
        $assignmentsCreated = 0;
        $assignmentsSkipped = 0;
        $missingCodes = [];

        foreach (AgentCatalogue::AGENTS as $definition) {
            $agent = $agents[$definition['slug']] ?? null;

            if (! $agent) {
                $agentsCreated++;

                if (! $dryRun) {
                    $agent = AiAgent::withoutGlobalScopes()->firstOrCreate(
                        ['tenant_id' => $tenant->id, 'slug' => $definition['slug']],
                        [
                            'name' => $definition['name'],
                            'description' => $definition['description'],
                            'role_type' => $definition['role_type'],
                            'is_active' => true,
                            'is_default' => $definition['is_default'],
                            // Unranked: the ranking lives on the assignment, so there is
                            // one number per decision. Also leaves P1's 0-last ordering
                            // rule genuinely exercised rather than hidden behind a copy.
                            'priority' => 0,
                            // NULL, so effectiveSystemPrompt() returns §28's default and a
                            // wording fix reaches every agent nobody has edited.
                            'system_prompt' => null,
                        ],
                    );
                }
            }

            $pivotsCreated += $this->attachMissingSkills($agent, $definition['skills'], $skillIds, $dryRun);

            $department = $departments[$definition['department_code']] ?? null;

            if (! $department) {
                $assignmentsSkipped++;
                $missingCodes[$definition['department_code']] = true;

                continue;
            }

            if ($agent && $this->isAssigned($department, $agent)) {
                continue;
            }

            $assignmentsCreated++;

            if ($dryRun || ! $agent) {
                continue;
            }

            $department->assignAiAgent(
                $agent,
                $definition['department_role'],
                $definition['priority'],
                true,
                $definition['auto_invoke'],
            );
        }

        return new CatalogueInstallSummary(
            skillsCreated: $skillsCreated,
            agentsCreated: $agentsCreated,
            skillPivotsCreated: $pivotsCreated,
            assignmentsCreated: $assignmentsCreated,
            assignmentsSkipped: $assignmentsSkipped,
            missingDepartmentCodes: array_keys($missingCodes),
            dryRun: $dryRun,
        );
    }

    /**
     * Link the catalogue's skills to an agent, touching no pair that already exists.
     *
     * @param  list<string>  $slugs
     * @param  array<string, int>  $skillIds
     * @return int the number of links created
     */
    private function attachMissingSkills(?AiAgent $agent, array $slugs, array $skillIds, bool $dryRun): int
    {
        $held = $agent
            ? $this->heldSkillIds($agent)
            : [];

        $absent = [];

        foreach ($slugs as $slug) {
            $id = $skillIds[$slug] ?? null;

            if ($id === null) {
                if ($dryRun) {
                    // The skill itself is one this run would create, so the link is new too.
                    $absent[] = $slug;

                    continue;
                }

                // The catalogues disagree with each other. AiAgentCatalogueIntegrityTest
                // makes this a red build; this is the backstop if one ever ships anyway.
                throw new InvalidArgumentException("The skill catalogue has no skill [{$slug}].");
            }

            if (! in_array($id, $held, true)) {
                $absent[$id] = $id;
            }
        }

        if ($absent === []) {
            return 0;
        }

        if ($dryRun || ! $agent) {
            return count($absent);
        }

        // Only the absent pairs reach syncSkills(), because it loops attachSkill(), which
        // updateOrCreates — passing the whole list would reset an administrator's tuning.
        $agent->syncSkills(array_map(
            fn (): array => [
                'proficiency' => self::SEEDED_PROFICIENCY,
                'weight' => null,
                'is_required' => false,
            ],
            $absent,
        ));

        return count($absent);
    }

    /**
     * @return array<string, int> slug => id
     */
    private function existingSkillIds(Tenant $tenant): array
    {
        return Skill::withoutGlobalScopes()->where('tenant_id', $tenant->id)
            ->pluck('id', 'slug')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return array<string, AiAgent>
     */
    private function existingAgentsBySlug(Tenant $tenant): array
    {
        return AiAgent::withoutGlobalScopes()->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('slug')
            ->all();
    }

    /**
     * @return array<string, Department>
     */
    private function departmentsByCode(Tenant $tenant): array
    {
        return Department::withoutGlobalScopes()->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('code')
            ->all();
    }

    /**
     * @return list<int>
     */
    private function heldSkillIds(AiAgent $agent): array
    {
        return $agent->skills()
            ->withoutGlobalScopes()->where('skills.tenant_id', $agent->tenant_id)
            ->pluck('skills.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function isAssigned(Department $department, AiAgent $agent): bool
    {
        // The existence check is the substance of "never update": assignAiAgent() would
        // happily reset a re-prioritised or deactivated assignment.
        return DepartmentAiAgent::query()
            ->where('department_id', $department->getKey())
            ->where('ai_agent_id', $agent->getKey())
            ->exists();
    }

    /**
     * The audit trail for an install: one entry, at the granularity of the action.
     */
    private function recordInstall(Tenant $tenant, CatalogueInstallSummary $summary): void
    {
        // ActivityLogger resolves the workspace from $subject->tenant_id or the current
        // scope, and a Tenant has neither in a console run — so the clone carries the id
        // for the log call only. A clone because setting it on $tenant itself would leave
        // a stray attribute on a model the caller still holds.
        $subject = (clone $tenant)->setAttribute('tenant_id', $tenant->id);

        ActivityLogger::log(
            $subject,
            'ai_catalogue_installed',
            'Default AI agent catalogue installed — '.$summary->describe(),
            [
                'skills_created' => $summary->skillsCreated,
                'agents_created' => $summary->agentsCreated,
                'skill_links_created' => $summary->skillPivotsCreated,
                'department_assignments_created' => $summary->assignmentsCreated,
                'missing_department_codes' => $summary->missingDepartmentCodes,
            ],
        );
    }
}
