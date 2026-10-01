<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Installs the default AI agent and skill catalogue into every existing workspace.
 *
 * A data migration, deliberately, because production only ever runs `migrate` — the
 * deploy workflow has no seeder step, so `AiAgentCatalogueSeeder` (demo workspace only)
 * and `ai-agents:install-catalogue` (opt-in, per workspace) can never reach a live
 * environment on their own.
 *
 * Safe to run against live data for three reasons worth stating:
 *
 *  1. The installer is **create-if-absent and never update** — a workspace that already
 *     holds part of the catalogue keeps its own edits, so re-running changes nothing.
 *     Proven in the P2 tests and against tenant 7 end to end.
 *  2. Departments are backfilled first. An agent whose department code is missing is
 *     created but left unassigned, so adding Operations and Pre-Sales before the install
 *     is what makes the assignments land. Both commands are firstOrCreate on
 *     (tenant_id, code) and will not rewrite a renamed or recoloured department.
 *  3. The rows are **inert until switched on**. The feature is Enterprise-gated
 *     (PlanFeature::AiAgents) and opt-in per workspace (`ai_agents_enabled`, default
 *     off), so this changes no behaviour anywhere — it only means the catalogue is
 *     already there when a workspace enables it.
 *
 * On a fresh database there are no tenants yet, so the loop is empty: this costs
 * nothing in CI, where RefreshDatabase re-migrates per test class.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Tenant::query()->doesntExist()) {
            return;
        }

        // Through the commands rather than a copy of their logic: both already handle
        // the no-session case with explicit tenant_id predicates, and duplicating the
        // backfill here is how the two would drift.
        Artisan::call('departments:add-missing-defaults', ['--all' => true]);
        Artisan::call('ai-agents:install-catalogue', ['--all' => true]);
    }

    /**
     * Not reversed on purpose.
     *
     * Rolling this back would mean deleting agents, skills and assignments an
     * administrator may have edited or added to since — and AiAgent::deleting() refuses
     * to drop an agent still assigned to a department, so a blanket delete would throw
     * part way through and leave the catalogue half removed. Removing a workspace's
     * agents is a deliberate act for the settings screen, not a schema rollback.
     */
    public function down(): void
    {
        //
    }
};
