<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Exceptions\AiAgentInUseException;
use App\Exceptions\SkillInUseException;
use App\Models\ActivityLog;
use App\Models\AiAgent;
use App\Models\AiAgentSkill;
use App\Models\AiProfile;
use App\Models\Department;
use App\Models\DepartmentAiAgent;
use App\Models\License;
use App\Models\Plan;
use App\Models\Skill;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * P1: the four tables, their relationships and the guards that live on the models.
 *
 * No controller and no service exist yet, so everything here goes through the
 * models — which is the point: the rules are enforced where the write happens, so
 * they hold for the P3 admin screen, P2's seeder and tinker alike.
 */
class AiAgentSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function createEnterpriseTenant(): Tenant
    {
        // firstOrCreate, not create: the isolation tests build two tenants and
        // plans.slug is unique.
        $plan = Plan::query()->firstOrCreate(
            ['slug' => 'enterprise'],
            Plan::factory()->make(['slug' => 'enterprise', 'features' => PlanFeature::forPlan('enterprise')])->getAttributes(),
        );
        $license = License::factory()->active()->forPlan($plan)->create();

        return Tenant::factory()->create(['license_id' => $license->id]);
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

    public function test_an_agent_can_hold_many_skills_with_proficiency(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $agent = AiAgent::factory()->create(['tenant_id' => $tenant->id]);
        $mysql = Skill::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'mysql']);
        $docs = Skill::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'documentation']);

        $agent->attachSkill($mysql, 'expert', 90, true);
        $agent->attachSkill($docs, 'basic');

        $agent->load('skills');
        $this->assertCount(2, $agent->skills);

        $pivot = $agent->skills->firstWhere('slug', 'mysql')->pivot;
        $this->assertSame('expert', $pivot->proficiency);
        $this->assertSame(90, $pivot->weight);
        $this->assertTrue($pivot->is_required);
        $this->assertSame(4, $pivot->proficiencyRank());

        $basic = $agent->skills->firstWhere('slug', 'documentation')->pivot;
        $this->assertSame('basic', $basic->proficiency);
        $this->assertNull($basic->weight);
        $this->assertFalse($basic->is_required);
    }

    public function test_an_unknown_proficiency_is_refused(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $agent = AiAgent::factory()->create(['tenant_id' => $tenant->id]);
        $skill = Skill::factory()->create(['tenant_id' => $tenant->id]);

        $this->expectException(InvalidArgumentException::class);
        $agent->attachSkill($skill, 'wizard');
    }

    public function test_a_skill_can_belong_to_many_agents(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $skill = Skill::factory()->create(['tenant_id' => $tenant->id]);
        $first = AiAgent::factory()->create(['tenant_id' => $tenant->id]);
        $second = AiAgent::factory()->create(['tenant_id' => $tenant->id]);

        $first->attachSkill($skill, 'expert');
        $second->attachSkill($skill, 'basic');

        $this->assertCount(2, $skill->fresh()->aiAgents);

        // Editing one agent's claim must not touch the other's.
        $first->attachSkill($skill, 'advanced');
        $this->assertSame('advanced', $first->skills()->first()->pivot->proficiency);
        $this->assertSame('basic', $second->skills()->first()->pivot->proficiency);
    }

    public function test_a_department_can_hold_many_agents_and_an_agent_many_departments(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $software = Department::factory()->create(['tenant_id' => $tenant->id, 'code' => 'SOFTWARE']);
        $hardware = Department::factory()->create(['tenant_id' => $tenant->id, 'code' => 'HARDWARE']);
        $triage = AiAgent::factory()->role('triage')->create(['tenant_id' => $tenant->id]);
        $analyst = AiAgent::factory()->role('analyst')->create(['tenant_id' => $tenant->id]);

        $software->assignAiAgent($triage, 'primary', 100, true, true);
        $software->assignAiAgent($analyst, 'support', 10);
        $hardware->assignAiAgent($triage, 'fallback', 5);

        $this->assertCount(2, $software->fresh()->aiAgents);
        $this->assertCount(2, $triage->fresh()->departments);

        // The same agent, two departments, different roles and priorities — and
        // editing one assignment leaves the other alone.
        $software->assignAiAgent($triage, 'specialist', 50);
        $this->assertSame('specialist', $software->aiAgents()->where('ai_agents.id', $triage->id)->first()->pivot->role);
        $this->assertSame('fallback', $hardware->aiAgents()->where('ai_agents.id', $triage->id)->first()->pivot->role);
    }

    public function test_an_unknown_department_agent_role_is_refused(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $agent = AiAgent::factory()->create(['tenant_id' => $tenant->id]);

        $this->expectException(InvalidArgumentException::class);
        $department->assignAiAgent($agent, 'overlord');
    }

    public function test_duplicate_pivot_rows_are_rejected(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $agent = AiAgent::factory()->create(['tenant_id' => $tenant->id]);
        $skill = Skill::factory()->create(['tenant_id' => $tenant->id]);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);

        $agent->attachSkill($skill, 'basic');
        $agent->attachSkill($skill, 'expert');
        $this->assertSame(1, DB::table('ai_agent_skill')->count());

        $department->assignAiAgent($agent, 'primary');
        $department->assignAiAgent($agent, 'support');
        $this->assertSame(1, DB::table('department_ai_agent')->count());

        // The index, not just the helper: a raw insert of the same pair must fail.
        $this->expectException(QueryException::class);
        DB::table('ai_agent_skill')->insert([
            'tenant_id' => $tenant->id,
            'ai_agent_id' => $agent->id,
            'skill_id' => $skill->id,
            'proficiency' => 'basic',
        ]);
    }

    public function test_pivot_rows_carry_the_tenant_id_of_their_parents(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $agent = AiAgent::factory()->create(['tenant_id' => $tenant->id]);
        $skill = Skill::factory()->create(['tenant_id' => $tenant->id]);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);

        $agent->attachSkill($skill, 'advanced');
        $department->assignAiAgent($agent, 'primary');

        // Read through the query builder, with no model and no global scope: the
        // direct-query path is the whole reason the column exists.
        $this->assertSame($tenant->id, (int) DB::table('ai_agent_skill')->value('tenant_id'));
        $this->assertSame($tenant->id, (int) DB::table('department_ai_agent')->value('tenant_id'));

        // And the one-statement integrity assertion the column buys: no pivot row
        // whose tenant disagrees with either parent's.
        $this->assertSame(0, DB::table('ai_agent_skill AS p')
            ->join('ai_agents AS a', 'a.id', '=', 'p.ai_agent_id')
            ->join('skills AS s', 's.id', '=', 'p.skill_id')
            ->where(fn ($q) => $q->whereColumn('p.tenant_id', '!=', 'a.tenant_id')
                ->orWhereColumn('p.tenant_id', '!=', 's.tenant_id'))
            ->count());
    }

    public function test_attaching_a_skill_from_another_tenant_throws(): void
    {
        $tenantA = $this->createEnterpriseTenant();
        $tenantB = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenantA);

        $agent = AiAgent::factory()->create(['tenant_id' => $tenantA->id]);
        $foreignSkill = Skill::factory()->create(['tenant_id' => $tenantB->id]);

        try {
            $agent->attachSkill($foreignSkill, 'expert');
            $this->fail('A cross-tenant skill attachment was accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('another workspace', $e->getMessage());
        }

        $this->assertSame(0, DB::table('ai_agent_skill')->count());
    }

    public function test_assigning_an_agent_from_another_tenant_to_a_department_throws(): void
    {
        $tenantA = $this->createEnterpriseTenant();
        $tenantB = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenantA);

        $department = Department::factory()->create(['tenant_id' => $tenantA->id]);
        $foreignAgent = AiAgent::factory()->create(['tenant_id' => $tenantB->id]);

        try {
            $department->assignAiAgent($foreignAgent, 'primary');
            $this->fail('A cross-tenant department assignment was accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('another workspace', $e->getMessage());
        }

        $this->assertSame(0, DB::table('department_ai_agent')->count());
    }

    public function test_another_tenants_skills_and_agents_are_invisible(): void
    {
        $tenantA = $this->createEnterpriseTenant();
        $tenantB = $this->createEnterpriseTenant();

        $foreignSkill = Skill::factory()->create(['tenant_id' => $tenantB->id]);
        $foreignAgent = AiAgent::factory()->create(['tenant_id' => $tenantB->id]);
        $foreignDepartment = Department::factory()->create(['tenant_id' => $tenantB->id]);
        $foreignDepartment->assignAiAgent($foreignAgent, 'primary');

        $this->setupTenantContext($tenantA);

        $ownSkill = Skill::factory()->create(['tenant_id' => $tenantA->id]);
        $ownAgent = AiAgent::factory()->create(['tenant_id' => $tenantA->id]);
        $ownDepartment = Department::factory()->create(['tenant_id' => $tenantA->id]);
        $ownDepartment->assignAiAgent($ownAgent, 'primary');

        $this->assertSame([$ownSkill->id], Skill::all()->pluck('id')->all());
        $this->assertSame([$ownAgent->id], AiAgent::all()->pluck('id')->all());
        $this->assertSame([$ownAgent->id], $ownDepartment->aiAgents->pluck('id')->all());
        $this->assertNull(Department::find($foreignDepartment->id));
        $this->assertNull(AiAgent::find($foreignAgent->id));
        $this->assertNull(Skill::find($foreignSkill->id));
    }

    public function test_a_skill_held_by_an_agent_cannot_be_deleted_and_names_the_agent(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        // Inactive on purpose: deleting a skill an inactive agent holds would still
        // silently edit that agent's configuration, so it blocks too.
        $agent = AiAgent::factory()->inactive()->create(['tenant_id' => $tenant->id, 'name' => 'Database Engineer Agent']);
        $skill = Skill::factory()->create(['tenant_id' => $tenant->id]);
        $agent->attachSkill($skill, 'expert');

        $this->assertSame(['Database Engineer Agent'], $skill->deletionBlockers());

        try {
            $skill->delete();
            $this->fail('A skill still held by an agent was deleted.');
        } catch (SkillInUseException $e) {
            $this->assertStringContainsString('Database Engineer Agent', $e->getMessage());
        }

        $this->assertNotNull(Skill::find($skill->id));

        // Detaching clears the blocker — the hard delete leaves no ghost row.
        $agent->detachSkill($skill);
        $this->assertSame([], $skill->fresh()->deletionBlockers());
        $skill->delete();
        $this->assertNull(Skill::find($skill->id));
    }

    public function test_an_agent_assigned_to_a_department_cannot_be_deleted(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $department = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Technical Software']);
        $agent = AiAgent::factory()->create(['tenant_id' => $tenant->id]);
        $department->assignAiAgent($agent, 'primary');

        $this->assertSame(['Technical Software'], $agent->deletionBlockers());

        try {
            $agent->delete();
            $this->fail('An agent still assigned to a department was deleted.');
        } catch (AiAgentInUseException $e) {
            $this->assertStringContainsString('Technical Software', $e->getMessage());
        }

        $this->assertNotNull(AiAgent::find($agent->id));

        $department->removeAiAgent($agent);
        $this->assertSame([], $agent->fresh()->deletionBlockers());
    }

    public function test_an_unassigned_agent_deletes_and_takes_its_skill_pivots_with_it(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $agent = AiAgent::factory()->create(['tenant_id' => $tenant->id]);
        $skill = Skill::factory()->create(['tenant_id' => $tenant->id]);
        $agent->attachSkill($skill, 'expert');

        // Its own skill pivots are private configuration, not a blocker.
        $this->assertSame([], $agent->deletionBlockers());

        $agent->delete();

        $this->assertNull(AiAgent::find($agent->id));
        $this->assertSame(0, DB::table('ai_agent_skill')->count());
        $this->assertNotNull(Skill::find($skill->id));
    }

    public function test_deleting_a_department_removes_its_agent_assignments_but_not_the_agents(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $agent = AiAgent::factory()->create(['tenant_id' => $tenant->id]);
        $skill = Skill::factory()->create(['tenant_id' => $tenant->id]);
        $agent->attachSkill($skill, 'advanced');
        $department->assignAiAgent($agent, 'primary');

        $department->delete();

        $this->assertSame(0, DB::table('department_ai_agent')->count());
        $this->assertNotNull(AiAgent::find($agent->id));
        $this->assertSame(1, DB::table('ai_agent_skill')->count());
    }

    public function test_activity_is_logged_for_agent_create_update_and_skill_attachment(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $agent = AiAgent::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Database Engineer Agent']);
        $skill = Skill::factory()->create(['tenant_id' => $tenant->id, 'name' => 'MySQL']);

        $this->assertDatabaseHas('activity_logs', [
            'tenant_id' => $tenant->id,
            'subject_type' => $agent->getMorphClass(),
            'subject_id' => $agent->id,
            'action' => 'created',
        ]);

        $agent->update(['system_prompt' => 'Answer only about billing.', 'priority' => 40]);

        $updated = ActivityLog::query()
            ->where('subject_type', $agent->getMorphClass())
            ->where('subject_id', $agent->id)
            ->where('action', 'updated')
            ->firstOrFail();

        // The prompt is logged in full, as an ordinary diff (the Ticket::description
        // precedent); the column is NULL unless an admin overrides it, so the volume
        // is bounded.
        $this->assertSame([null, 'Answer only about billing.'], [
            $updated->changes['system_prompt']['old'],
            $updated->changes['system_prompt']['new'],
        ]);
        $this->assertSame(40, (int) $updated->changes['priority']['new']);

        // Attaching and detaching are configuration changes in their own right, which
        // is why the pivots are real models with ids.
        // And the row has to say what happened, not merely exist: subject_label is what
        // the audit-log screen renders, and for a detach the pivot id resolves to nothing
        // afterwards, so an unreadable label can never be recovered.
        $pivot = $agent->attachSkill($skill, 'expert');
        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => $pivot->getMorphClass(),
            'subject_id' => $pivot->id,
            'action' => 'created',
            'subject_label' => 'Database Engineer Agent → MySQL',
        ]);

        $agent->detachSkill($skill);
        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => $pivot->getMorphClass(),
            'subject_id' => $pivot->id,
            'action' => 'deleted',
            'subject_label' => 'Database Engineer Agent → MySQL',
        ]);

        $department = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Technical Software']);
        $assignment = $department->assignAiAgent($agent, 'primary');
        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => $assignment->getMorphClass(),
            'subject_id' => $assignment->id,
            'action' => 'created',
            'subject_label' => 'Technical Software → Database Engineer Agent',
        ]);

        $department->removeAiAgent($agent);
        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => $assignment->getMorphClass(),
            'subject_id' => $assignment->id,
            'action' => 'deleted',
            'subject_label' => 'Technical Software → Database Engineer Agent',
        ]);
    }

    public function test_role_type_and_proficiency_constants_are_the_only_enumeration(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $skill = Skill::factory()->create(['tenant_id' => $tenant->id]);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);

        // Every enumerated value is accepted by a varchar column with no migration.
        foreach (AiAgent::ROLE_TYPES as $roleType) {
            $agent = AiAgent::factory()->role($roleType)->create(['tenant_id' => $tenant->id]);
            $this->assertSame($roleType, $agent->fresh()->role_type);
        }

        foreach (AiAgentSkill::PROFICIENCIES as $proficiency) {
            $agent = AiAgent::factory()->create(['tenant_id' => $tenant->id]);
            $this->assertSame($proficiency, $agent->attachSkill($skill, $proficiency)->proficiency);
            $this->assertArrayHasKey($proficiency, AiAgentSkill::PROFICIENCY_RANK);
        }

        foreach (DepartmentAiAgent::ROLES as $role) {
            $agent = AiAgent::factory()->create(['tenant_id' => $tenant->id]);
            $this->assertSame($role, $department->assignAiAgent($agent, $role)->role);
        }
    }

    public function test_ordered_scope_breaks_ties_deterministically(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        // The requirement's own numbers: Triage 1, Debugger 20, QA 90, triage first — so
        // ascending. Unranked (0) sorts last, or nobody's default-priority agent would
        // outrank the triage agent somebody deliberately put at 1.
        AiAgent::factory()->create(['tenant_id' => $tenant->id, 'name' => 'QA Engineer Agent', 'priority' => 90]);
        AiAgent::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Beta Agent', 'priority' => 0]);
        AiAgent::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Alpha Agent', 'priority' => 0]);
        AiAgent::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Debugger Agent', 'priority' => 20]);
        AiAgent::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Software Triage Agent', 'priority' => 1]);

        $this->assertSame(
            ['Software Triage Agent', 'Debugger Agent', 'QA Engineer Agent', 'Alpha Agent', 'Beta Agent'],
            AiAgent::query()->ordered()->pluck('name')->all(),
        );
    }

    public function test_department_agents_rank_on_the_assignment_priority_first(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $department = Department::factory()->create(['tenant_id' => $tenant->id]);

        // The agent's own priority is deliberately the reverse of the assignment's, so a
        // sort that read the agent column first would produce the opposite order.
        $triage = AiAgent::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Software Triage Agent', 'priority' => 90]);
        $debugger = AiAgent::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Debugger Agent', 'priority' => 20]);
        $unranked = AiAgent::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Documentation Agent', 'priority' => 1]);

        $department->assignAiAgent($triage, 'primary', 1, true, true);
        $department->assignAiAgent($debugger, 'specialist', 20);
        $department->assignAiAgent($unranked, 'support', 0);

        $this->assertSame(
            ['Software Triage Agent', 'Debugger Agent', 'Documentation Agent'],
            $department->aiAgentsRanked()->pluck('ai_agents.name')->all(),
        );
    }

    public function test_effective_system_prompt_falls_back_to_the_default_and_an_override_is_isolated(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $plain = AiAgent::factory()->create(['tenant_id' => $tenant->id]);
        $overridden = AiAgent::factory()->create([
            'tenant_id' => $tenant->id,
            'system_prompt' => 'Only summarise; never recommend.',
        ]);

        $this->assertNull($plain->system_prompt);
        $this->assertSame(AiAgent::DEFAULT_SYSTEM_PROMPT, $plain->effectiveSystemPrompt());
        $this->assertSame('Only summarise; never recommend.', $overridden->effectiveSystemPrompt());
        $this->assertSame(AiAgent::DEFAULT_SYSTEM_PROMPT, $plain->fresh()->effectiveSystemPrompt());
    }

    public function test_ai_profile_is_optional_and_nulls_out_when_the_profile_is_deleted(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $unprofiled = AiAgent::factory()->create(['tenant_id' => $tenant->id]);
        $this->assertNull($unprofiled->aiProfile);

        $profile = AiProfile::create(['name' => 'Balanced', 'slug' => 'balanced', 'model' => 'gpt-5']);
        $agent = AiAgent::factory()->create(['tenant_id' => $tenant->id, 'ai_profile_id' => $profile->id]);
        $this->assertSame('balanced', $agent->aiProfile->slug);

        $profile->delete();
        $this->assertNull($agent->fresh()->ai_profile_id);
        $this->assertNotNull(AiAgent::find($agent->id));
    }

    public function test_enterprise_plan_includes_the_ai_agents_feature(): void
    {
        $this->assertContains('ai_agents', PlanFeature::forPlan('enterprise'));
        $this->assertNotContains('ai_agents', PlanFeature::forPlan('business'));
        // 'start', not 'starter' — forPlan('starter') hits the default arm and
        // silently returns [], which would make this assertion meaningless.
        $this->assertSame([], PlanFeature::forPlan('start'));
        $this->assertSame('enterprise', PlanFeature::AiAgents->minimumPlan());
    }

    public function test_no_outbound_http_request_is_made(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        // A delta, not an absolute count: another suite's leftovers would otherwise
        // fail this for a reason that has nothing to do with the feature.
        $costedCallsBefore = DB::table('ai_usage_logs')->count();

        $agent = AiAgent::factory()->create(['tenant_id' => $tenant->id]);
        $skill = Skill::factory()->create(['tenant_id' => $tenant->id]);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);

        $agent->attachSkill($skill, 'expert', 80, true);
        $agent->syncSkills([$skill->id => ['proficiency' => 'advanced']]);
        $department->assignAiAgent($agent, 'primary', 100, true, true);
        $agent->update(['system_prompt' => 'Be terse.']);
        $agent->detachSkill($skill);

        // Configuration only: no agent runs, so nothing may reach OpenAI and nothing
        // may be costed.
        Http::assertNothingSent();
        $this->assertSame($costedCallsBefore, DB::table('ai_usage_logs')->count());
    }

    public function test_sync_skills_leaves_unmentioned_skills_in_place_and_refuses_a_foreign_id(): void
    {
        $tenantA = $this->createEnterpriseTenant();
        $tenantB = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenantA);

        $agent = AiAgent::factory()->create(['tenant_id' => $tenantA->id]);
        $kept = Skill::factory()->create(['tenant_id' => $tenantA->id]);
        $added = Skill::factory()->create(['tenant_id' => $tenantA->id]);
        $foreign = Skill::factory()->create(['tenant_id' => $tenantB->id]);

        $agent->attachSkill($kept, 'expert');
        // syncWithoutDetaching semantics: an admin's own additions survive a re-seed.
        $agent->syncSkills([$added->id => 'basic']);

        $this->assertEqualsCanonicalizing(
            [$kept->id, $added->id],
            $agent->fresh()->skills->pluck('id')->all(),
        );
        $this->assertSame('expert', $agent->skills()->where('skills.id', $kept->id)->first()->pivot->proficiency);

        $this->expectException(InvalidArgumentException::class);
        $agent->syncSkills([$foreign->id => 'basic']);
    }
}
