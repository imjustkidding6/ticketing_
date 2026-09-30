<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\ActivityLog;
use App\Models\AiAgent;
use App\Models\AiAgentSkill;
use App\Models\Department;
use App\Models\DepartmentAiAgent;
use App\Models\License;
use App\Models\Plan;
use App\Models\Skill;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiAgentCatalogueInstaller;
use Database\Seeders\Catalogue\AgentCatalogue;
use Database\Seeders\Catalogue\SkillCatalogue;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * P2: the catalogue, the installer and the two new departments.
 *
 * The tests that matter most here are the four "survives a reinstall" ones. Both of P1's
 * pivot helpers are updateOrCreate — correctly, for their own callers — so the obvious
 * implementation of a re-runnable seeder silently reverts an administrator's tuning, and
 * nothing else in the suite would notice.
 */
class AiAgentCatalogueSeedTest extends TestCase
{
    use RefreshDatabase;

    private function createEnterpriseTenant(): Tenant
    {
        // firstOrCreate, not create: the isolation test builds two tenants and
        // plans.slug is unique.
        $plan = Plan::query()->firstOrCreate(
            ['slug' => 'enterprise'],
            Plan::factory()->make(['slug' => 'enterprise', 'features' => PlanFeature::forPlan('enterprise')])->getAttributes(),
        );
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);

        // Enterprise is Business+, so the SLA-policy guard applies to any ticket priority
        // this tenant later sees; a catch-all keeps TicketService from throwing.
        SlaPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'priority' => null,
            'client_tier' => null,
            'is_active' => true,
        ]);

        return $tenant;
    }

    private function setupTenantContext(Tenant $tenant): User
    {
        $user = User::factory()->create();
        $tenant->addUser($user, 'member');

        $this->actingAs($user)
            ->withTenant($tenant)
            ->withSession(['current_tenant_id' => $tenant->id]);

        return $user;
    }

    /**
     * A workspace with all nine default departments and nothing else.
     */
    private function tenantWithDepartments(): Tenant
    {
        $tenant = $this->createEnterpriseTenant();
        DepartmentSeeder::seedForTenant($tenant);

        return $tenant;
    }

    private function installer(): AiAgentCatalogueInstaller
    {
        return app(AiAgentCatalogueInstaller::class);
    }

    /**
     * Row counts in the four tables, read through the query builder so no global scope
     * can make an assertion pass by hiding rows.
     *
     * @return array<string, int>
     */
    private function counts(): array
    {
        return [
            'skills' => DB::table('skills')->count(),
            'ai_agents' => DB::table('ai_agents')->count(),
            'ai_agent_skill' => DB::table('ai_agent_skill')->count(),
            'department_ai_agent' => DB::table('department_ai_agent')->count(),
        ];
    }

    public function test_installing_the_catalogue_creates_every_skill_and_agent_for_that_tenant(): void
    {
        $tenant = $this->tenantWithDepartments();

        $summary = $this->installer()->installFor($tenant);

        // Counted from the constants: a hardcoded 214 would go stale the first time
        // somebody adds a skill.
        $expectedSkills = count(SkillCatalogue::SKILLS);
        $expectedAgents = count(AgentCatalogue::AGENTS);
        $expectedPivots = array_sum(array_map(
            fn (array $agent): int => count($agent['skills']),
            AgentCatalogue::AGENTS,
        ));

        $this->assertSame($expectedSkills, $summary->skillsCreated);
        $this->assertSame($expectedAgents, $summary->agentsCreated);
        $this->assertSame($expectedPivots, $summary->skillPivotsCreated);
        $this->assertSame($expectedAgents, $summary->assignmentsCreated);
        $this->assertSame([], $summary->missingDepartmentCodes);

        $this->assertSame([
            'skills' => $expectedSkills,
            'ai_agents' => $expectedAgents,
            'ai_agent_skill' => $expectedPivots,
            'department_ai_agent' => $expectedAgents,
        ], $this->counts());
    }

    public function test_every_seeded_agent_is_unranked_and_uses_the_default_system_prompt(): void
    {
        $tenant = $this->tenantWithDepartments();
        $this->installer()->installFor($tenant);

        // The ranking lives on the assignment only, which is also what exercises P1's
        // 0-last ordering rule rather than hiding it behind a second copy.
        $this->assertSame(0, DB::table('ai_agents')->where('priority', '!=', 0)->count());
        $this->assertSame(0, DB::table('ai_agents')->whereNotNull('system_prompt')->count());

        $agent = AiAgent::withoutGlobalScopes()->where('slug', 'software-triage-agent')->first();
        $this->assertSame(AiAgent::DEFAULT_SYSTEM_PROMPT, $agent->effectiveSystemPrompt());

        $this->assertSame(
            0,
            DB::table('ai_agent_skill')->where('proficiency', '!=', 'advanced')->count(),
        );
        $this->assertSame(0, DB::table('ai_agent_skill')->whereNotNull('weight')->count());
        $this->assertSame(0, DB::table('ai_agent_skill')->where('is_required', true)->count());
    }

    public function test_installing_twice_changes_no_row_count_in_any_of_the_four_tables(): void
    {
        $tenant = $this->tenantWithDepartments();

        $this->installer()->installFor($tenant);
        $after = $this->counts();

        $second = $this->installer()->installFor($tenant);

        $this->assertTrue($second->createdNothing());
        $this->assertSame($after, $this->counts());
    }

    public function test_db_seed_run_twice_produces_identical_counts(): void
    {
        Artisan::call('db:seed', ['--force' => true]);
        $first = $this->counts();

        Artisan::call('db:seed', ['--force' => true]);

        $this->assertSame($first, $this->counts());
        // And it really did seed something, or the assertion above is vacuous.
        $this->assertSame(count(AgentCatalogue::AGENTS), $first['ai_agents']);
    }

    public function test_an_administrator_retuned_assignment_survives_a_reinstall(): void
    {
        $tenant = $this->tenantWithDepartments();
        $this->installer()->installFor($tenant);

        $department = Department::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('code', 'SOFTWARE')->first();
        $agent = AiAgent::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('slug', 'debugger-agent')->first();

        $assignment = DepartmentAiAgent::query()
            ->where('department_id', $department->id)
            ->where('ai_agent_id', $agent->id)
            ->firstOrFail();

        // The per-assignment tuning §8 exists to provide.
        $assignment->update(['priority' => 5, 'role' => 'primary', 'auto_invoke' => true, 'is_active' => false]);

        $this->installer()->installFor($tenant);

        // assignAiAgent() is an updateOrCreate: passing it this pair would have reset all four.
        $assignment->refresh();
        $this->assertSame(5, $assignment->priority);
        $this->assertSame('primary', $assignment->role);
        $this->assertTrue($assignment->auto_invoke);
        $this->assertFalse($assignment->is_active);
        $this->assertSame(1, DepartmentAiAgent::query()
            ->where('department_id', $department->id)->where('ai_agent_id', $agent->id)->count());
    }

    public function test_an_administrator_changed_proficiency_survives_a_reinstall(): void
    {
        $tenant = $this->tenantWithDepartments();
        $this->installer()->installFor($tenant);

        $agent = AiAgent::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('slug', 'database-engineer-agent')->first();
        $skill = Skill::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('slug', 'mysql')->first();

        $pivot = AiAgentSkill::query()
            ->where('ai_agent_id', $agent->id)->where('skill_id', $skill->id)->firstOrFail();

        $pivot->update(['proficiency' => 'expert', 'weight' => 75, 'is_required' => true]);

        $this->installer()->installFor($tenant);

        // attachSkill() is an updateOrCreate, so syncSkills() over the whole list would
        // have put this row back to advanced/null/false.
        $pivot->refresh();
        $this->assertSame('expert', $pivot->proficiency);
        $this->assertSame(75, $pivot->weight);
        $this->assertTrue($pivot->is_required);
    }

    public function test_an_administrator_renamed_skill_survives_a_reinstall(): void
    {
        $tenant = $this->tenantWithDepartments();
        $this->installer()->installFor($tenant);

        $skill = Skill::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('slug', 'mysql')->first();
        $skill->update(['name' => 'MySQL 8', 'skill_category' => 'Our own category', 'is_active' => false]);

        $this->installer()->installFor($tenant);

        $skill->refresh();
        $this->assertSame('MySQL 8', $skill->name);
        $this->assertSame('Our own category', $skill->skill_category);
        $this->assertFalse($skill->is_active);
        $this->assertSame(1, Skill::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('slug', 'mysql')->count());
    }

    public function test_an_administrator_added_skill_and_edited_agent_survive_a_reinstall(): void
    {
        $tenant = $this->tenantWithDepartments();
        $this->installer()->installFor($tenant);

        $agent = AiAgent::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('slug', 'qa-engineer-agent')->first();
        $agent->update(['system_prompt' => 'Only ever check the regression suite.', 'is_active' => false, 'role_type' => 'analyst']);

        $extra = Skill::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'our-own-skill']);
        $agent->attachSkill($extra, 'expert');

        $before = AiAgentSkill::query()->where('ai_agent_id', $agent->id)->count();

        $this->installer()->installFor($tenant);

        $agent->refresh();
        $this->assertSame('Only ever check the regression suite.', $agent->system_prompt);
        $this->assertFalse($agent->is_active);
        $this->assertSame('analyst', $agent->role_type);
        $this->assertSame($before, AiAgentSkill::query()->where('ai_agent_id', $agent->id)->count());
        $this->assertTrue($agent->skills()->where('skills.slug', 'our-own-skill')->exists());
    }

    public function test_a_removed_catalogue_skill_is_restored_by_a_reinstall(): void
    {
        // The accepted consequence of create-if-absent: "absent" cannot be told from
        // "removed on purpose", and the command says so in its output. Pinned here so
        // nobody changes it silently.
        $tenant = $this->tenantWithDepartments();
        $this->installer()->installFor($tenant);

        $agent = AiAgent::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('slug', 'debugger-agent')->first();
        $skill = Skill::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('slug', 'log-analysis')->first();
        $agent->detachSkill($skill);

        $summary = $this->installer()->installFor($tenant);

        $this->assertSame(1, $summary->skillPivotsCreated);
        $this->assertTrue($agent->skills()->where('skills.slug', 'log-analysis')->exists());
    }

    public function test_technical_software_has_exactly_the_eight_agents_of_the_spec_with_triage_first(): void
    {
        $tenant = $this->tenantWithDepartments();
        $this->setupTenantContext($tenant);
        $this->installer()->installFor($tenant);

        $department = Department::where('code', 'SOFTWARE')->firstOrFail();

        // Read through the relation P1 owns the ordering of — P2 writes no orderBy.
        $agents = $department->aiAgentsRanked()->get();

        $this->assertCount(8, $agents);
        $this->assertSame('Software Triage Agent', $agents->first()->name);

        // The whole ranked order, not just the ends: assignment priority ascending
        // (1 triage, 20 specialists, 80 documentation, 90 validators) with name as the
        // tie-break, so two agents on the same priority never swap between requests.
        $this->assertSame([
            'Software Triage Agent',
            'Database Engineer Agent',
            'Debugger Agent',
            'DevOps Agent',
            'Software Engineer Agent',
            'Documentation Agent',
            'QA Engineer Agent',
            'Security Agent',
        ], $agents->pluck('name')->all());

        // §8's worked example, on the assignment rather than the agent.
        $this->assertSame(1, $agents->firstWhere('slug', 'software-triage-agent')->pivot->priority);
        $this->assertSame(20, $agents->firstWhere('slug', 'debugger-agent')->pivot->priority);
        $this->assertSame(90, $agents->firstWhere('slug', 'qa-engineer-agent')->pivot->priority);
        $this->assertSame('primary', $agents->firstWhere('slug', 'software-triage-agent')->pivot->role);
        $this->assertSame('validator', $agents->firstWhere('slug', 'qa-engineer-agent')->pivot->role);
    }

    public function test_software_engineer_agent_holds_exactly_the_skills_of_the_spec_and_is_a_specialist(): void
    {
        $tenant = $this->tenantWithDepartments();
        $this->setupTenantContext($tenant);
        $this->installer()->installFor($tenant);

        $agent = AiAgent::where('slug', 'software-engineer-agent')->firstOrFail();

        $this->assertSame('specialist', $agent->role_type);
        // §19–§27 lists thirteen skills for this agent; §39's prose says twelve. The list
        // is the data, so the list wins — see the build notes.
        $this->assertEqualsCanonicalizing([
            'software-engineering', 'laravel', 'php', 'mysql', 'livewire', 'javascript',
            'rest-api', 'api-integration', 'software-architecture', 'system-design',
            'backend-development', 'frontend-development', 'full-stack-development',
        ], $agent->skills->pluck('slug')->all());
    }

    public function test_only_triage_agents_are_auto_invoke_and_software_has_exactly_one(): void
    {
        $tenant = $this->tenantWithDepartments();
        $this->setupTenantContext($tenant);
        $this->installer()->installFor($tenant);

        $autoInvoke = DepartmentAiAgent::query()->where('auto_invoke', true)->get();

        $this->assertCount(9, $autoInvoke);
        foreach ($autoInvoke as $assignment) {
            $this->assertSame('triage', $assignment->aiAgent->role_type);
        }

        $software = Department::where('code', 'SOFTWARE')->firstOrFail();
        $this->assertSame(1, DepartmentAiAgent::query()
            ->where('department_id', $software->id)->where('auto_invoke', true)->count());
    }

    public function test_a_shared_skill_is_attached_to_every_agent_that_lists_it(): void
    {
        $tenant = $this->tenantWithDepartments();
        $this->setupTenantContext($tenant);
        $this->installer()->installFor($tenant);

        // §2/§3: one row, many agents, across departments — not one copy per department.
        $this->assertSame(1, Skill::where('slug', 'root-cause-analysis')->count());

        $skill = Skill::where('slug', 'root-cause-analysis')->firstOrFail();
        $expected = array_column(array_filter(
            AgentCatalogue::AGENTS,
            fn (array $agent): bool => in_array('root-cause-analysis', $agent['skills'], true),
        ), 'slug');

        $this->assertGreaterThan(1, count($expected));
        $this->assertEqualsCanonicalizing($expected, $skill->aiAgents->pluck('slug')->all());
        $this->assertGreaterThan(
            1,
            $skill->aiAgents->load('departments')->pluck('departments')->flatten()->pluck('code')->unique()->count(),
        );
    }

    public function test_installing_for_one_tenant_leaves_another_tenant_empty(): void
    {
        $first = $this->tenantWithDepartments();
        $second = $this->tenantWithDepartments();

        $this->installer()->installFor($first);

        foreach (['skills', 'ai_agents', 'ai_agent_skill', 'department_ai_agent'] as $table) {
            $this->assertSame(
                0,
                DB::table($table)->where('tenant_id', $second->id)->count(),
                "Tenant B has rows in {$table}.",
            );
            // withoutEvents silences BelongsToTenant's stamping hook, so every insert has
            // to carry tenant_id itself. This is the assertion that catches a missed one.
            $this->assertSame(0, DB::table($table)->whereNull('tenant_id')->count());
            $this->assertSame(
                DB::table($table)->count(),
                DB::table($table)->where('tenant_id', $first->id)->count(),
            );
        }
    }

    public function test_no_pivot_row_pairs_records_from_two_workspaces(): void
    {
        $first = $this->tenantWithDepartments();
        $second = $this->tenantWithDepartments();

        $this->installer()->installFor($first);
        $this->installer()->installFor($second);

        $crossed = DB::table('ai_agent_skill')
            ->join('ai_agents', 'ai_agents.id', '=', 'ai_agent_skill.ai_agent_id')
            ->join('skills', 'skills.id', '=', 'ai_agent_skill.skill_id')
            ->where(fn ($q) => $q
                ->whereColumn('ai_agents.tenant_id', '!=', 'skills.tenant_id')
                ->orWhereColumn('ai_agent_skill.tenant_id', '!=', 'ai_agents.tenant_id'))
            ->count();

        $this->assertSame(0, $crossed);

        $crossedAssignments = DB::table('department_ai_agent')
            ->join('ai_agents', 'ai_agents.id', '=', 'department_ai_agent.ai_agent_id')
            ->join('departments', 'departments.id', '=', 'department_ai_agent.department_id')
            ->where(fn ($q) => $q
                ->whereColumn('ai_agents.tenant_id', '!=', 'departments.tenant_id')
                ->orWhereColumn('department_ai_agent.tenant_id', '!=', 'ai_agents.tenant_id'))
            ->count();

        $this->assertSame(0, $crossedAssignments);
    }

    public function test_installing_writes_one_activity_log_entry_and_no_per_row_entries(): void
    {
        $tenant = $this->tenantWithDepartments();
        ActivityLog::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();

        $this->installer()->installFor($tenant);

        $logs = ActivityLog::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get();

        // ~700 "Skill created." rows would bury the first real edit.
        $this->assertCount(1, $logs);
        $this->assertSame('ai_catalogue_installed', $logs->first()->action);
        $this->assertStringContainsString('Default AI agent catalogue installed', $logs->first()->description);

        // A second, no-op install records nothing at all.
        $this->installer()->installFor($tenant);
        $this->assertSame(1, ActivityLog::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_a_tenant_missing_a_department_still_gets_the_agents_and_the_summary_names_the_code(): void
    {
        $tenant = $this->createEnterpriseTenant();
        // A workspace that existed before Operations and Pre-Sales became defaults.
        DepartmentSeeder::seedForTenant($tenant, DepartmentSeeder::ADDED_2026_09);

        $summary = $this->installer()->installFor($tenant);

        $this->assertSame(['OPS', 'PRESALES'], $summary->missingDepartmentCodes);
        $this->assertSame(10, $summary->assignmentsSkipped);
        $this->assertSame(count(AgentCatalogue::AGENTS), $summary->agentsCreated);
        $this->assertSame(count(AgentCatalogue::AGENTS) - 10, $summary->assignmentsCreated);

        // The ten agents exist and are simply unassigned, which the command tells the
        // operator how to fix rather than leaving them uncreated.
        $this->assertSame(
            10,
            DB::table('ai_agents')
                ->leftJoin('department_ai_agent', 'ai_agents.id', '=', 'department_ai_agent.ai_agent_id')
                ->whereNull('department_ai_agent.id')
                ->count(),
        );

        // Adding the departments and reinstalling completes the assignments.
        Artisan::call('departments:add-missing-defaults', ['--tenant' => $tenant->id]);
        $second = $this->installer()->installFor($tenant);

        $this->assertSame(10, $second->assignmentsCreated);
        $this->assertSame(0, $second->skillsCreated);
        $this->assertSame(0, $second->agentsCreated);
    }

    public function test_the_install_command_refuses_without_a_target_and_dry_run_writes_nothing(): void
    {
        $tenant = $this->tenantWithDepartments();

        $this->assertSame(1, Artisan::call('ai-agents:install-catalogue'));
        $this->assertSame([
            'skills' => 0, 'ai_agents' => 0, 'ai_agent_skill' => 0, 'department_ai_agent' => 0,
        ], $this->counts());

        $logsBefore = ActivityLog::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();

        $summary = $this->installer()->installFor($tenant, dryRun: true);

        $this->assertTrue($summary->dryRun);
        $this->assertSame(count(SkillCatalogue::SKILLS), $summary->skillsCreated);
        $this->assertSame(count(AgentCatalogue::AGENTS), $summary->agentsCreated);
        $this->assertSame(count(AgentCatalogue::AGENTS), $summary->assignmentsCreated);
        $this->assertSame(
            array_sum(array_map(fn (array $a): int => count($a['skills']), AgentCatalogue::AGENTS)),
            $summary->skillPivotsCreated,
        );

        // Reported, not written.
        $this->assertSame([
            'skills' => 0, 'ai_agents' => 0, 'ai_agent_skill' => 0, 'department_ai_agent' => 0,
        ], $this->counts());
        $this->assertSame($logsBefore, ActivityLog::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());

        // And a dry run over an installed workspace reports nothing left to do.
        $this->installer()->installFor($tenant);
        $this->assertTrue($this->installer()->installFor($tenant, dryRun: true)->createdNothing());
    }

    public function test_the_install_command_installs_for_one_tenant_only(): void
    {
        $first = $this->tenantWithDepartments();
        $second = $this->tenantWithDepartments();

        $this->assertSame(0, Artisan::call('ai-agents:install-catalogue', ['--tenant' => $first->id]));

        $this->assertSame(count(SkillCatalogue::SKILLS), DB::table('skills')->where('tenant_id', $first->id)->count());
        $this->assertSame(0, DB::table('skills')->where('tenant_id', $second->id)->count());
    }

    public function test_no_outbound_http_request_is_made(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $tenant = $this->tenantWithDepartments();
        $this->installer()->installFor($tenant);

        // The catalogue is data. Nothing here talks to OpenAI or anything else.
        Http::assertNothingSent();
    }

    public function test_department_seeder_run_does_not_add_the_new_departments_to_an_existing_tenant(): void
    {
        $tenant = $this->createEnterpriseTenant();
        DepartmentSeeder::seedForTenant($tenant, DepartmentSeeder::ADDED_2026_09);

        $before = Department::withoutGlobalScopes()->where('tenant_id', $tenant->id)
            ->pluck('sort_order', 'code')->all();
        $this->assertCount(7, $before);

        // The only thing standing between six live workspaces and two departments nobody
        // asked for: db:seed loops every tenant.
        (new DepartmentSeeder)->run();

        $after = Department::withoutGlobalScopes()->where('tenant_id', $tenant->id)
            ->pluck('sort_order', 'code')->all();

        $this->assertSame($before, $after);
    }

    public function test_add_missing_default_departments_adds_only_absent_codes_and_rewrites_nothing(): void
    {
        $tenant = $this->createEnterpriseTenant();
        DepartmentSeeder::seedForTenant($tenant, DepartmentSeeder::ADDED_2026_09);

        $others = Department::withoutGlobalScopes()->where('tenant_id', $tenant->id)
            ->where('code', 'OTHERS')->first();
        $others->update(['name' => 'Miscellaneous', 'color' => '#000000', 'sort_order' => 3]);

        $before = Department::withoutGlobalScopes()->where('tenant_id', $tenant->id)
            ->get()->map->only(['code', 'name', 'color', 'sort_order'])->keyBy('code')->all();

        $this->assertSame(0, Artisan::call('departments:add-missing-defaults', ['--tenant' => $tenant->id]));

        $departments = Department::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get()->keyBy('code');

        $this->assertCount(9, $departments);
        // seedForTenant() would have updateOrCreated the rename and the colour away.
        $this->assertSame('Miscellaneous', $departments['OTHERS']->name);
        $this->assertSame('#000000', $departments['OTHERS']->color);

        foreach ($before as $code => $attributes) {
            $this->assertSame(
                $attributes,
                $departments[$code]->only(['code', 'name', 'color', 'sort_order']),
                "Department {$code} was rewritten.",
            );
        }

        // Appended, so the existing ordering is not renumbered.
        $max = max(array_column($before, 'sort_order'));
        $this->assertSame($max + 1, $departments['OPS']->sort_order);
        $this->assertSame($max + 2, $departments['PRESALES']->sort_order);
        $this->assertTrue($departments['OPS']->is_default);
    }

    public function test_add_missing_default_departments_is_idempotent_and_dry_run_writes_nothing(): void
    {
        $tenant = $this->createEnterpriseTenant();

        Artisan::call('departments:add-missing-defaults', ['--tenant' => $tenant->id, '--dry-run' => true]);
        $this->assertSame(0, DB::table('departments')->where('tenant_id', $tenant->id)->count());

        Artisan::call('departments:add-missing-defaults', ['--tenant' => $tenant->id]);
        Artisan::call('departments:add-missing-defaults', ['--tenant' => $tenant->id]);

        $this->assertSame(
            count(DepartmentSeeder::DEFAULTS),
            DB::table('departments')->where('tenant_id', $tenant->id)->count(),
        );

        // And it refuses to guess a target.
        $this->assertSame(1, Artisan::call('departments:add-missing-defaults'));
    }

    public function test_registration_still_seeds_all_nine_departments(): void
    {
        // The one-argument call site (RegisteredUserController, GoogleSocialiteController)
        // must keep getting every default — the skip list belongs to run() alone.
        $tenant = $this->createEnterpriseTenant();
        DepartmentSeeder::seedForTenant($tenant);

        $codes = Department::withoutGlobalScopes()->where('tenant_id', $tenant->id)->pluck('code')->all();

        $this->assertEqualsCanonicalizing(array_column(DepartmentSeeder::DEFAULTS, 'code'), $codes);
        $this->assertContains('OPS', $codes);
        $this->assertContains('PRESALES', $codes);
    }
}
