<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\ActivityLog;
use App\Models\AiAgent;
use App\Models\AiAgentSkill;
use App\Models\AppSetting;
use App\Models\Department;
use App\Models\License;
use App\Models\Plan;
use App\Models\Skill;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PlanService;
use App\Services\TenantRoleService;
use Database\Seeders\Catalogue\AgentCatalogue;
use Database\Seeders\Catalogue\SkillCatalogue;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * P3: the skills and agents admin screens.
 *
 * The test this class exists for is the removal one. `AiAgent::syncSkills()` has
 * syncWithoutDetaching semantics on purpose, so an editor that only calls it saves
 * successfully, reloads, and still shows the skill the administrator removed — and a test
 * asserting only "the save succeeded" is green throughout.
 */
class AiAgentAdminUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Configuration screens only. Nothing here may reach an AI provider.
        Http::fake();
    }

    private function createEnterpriseTenant(): Tenant
    {
        // first()-or-create rather than firstOrCreate(): passing a factory's getAttributes()
        // as the second argument double-encodes the JSON `features` cast, and the plan then
        // has no features at all — which surfaces as a 403 from the feature middleware.
        $plan = Plan::query()->where('slug', 'enterprise')->first()
            ?? Plan::factory()->create(['slug' => 'enterprise', 'features' => PlanFeature::forPlan('enterprise')]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);

        // Enterprise is Business+, so the SLA-policy guard applies to any ticket priority;
        // a catch-all keeps TicketService from throwing if one is ever created.
        SlaPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'priority' => null,
            'client_tier' => null,
            'is_active' => true,
        ]);

        // PlanService caches features for 300s under the tenant id, and tenant ids repeat
        // across tests in one process — a stale entry from another plan reads as a 403.
        app(PlanService::class)->clearCache($tenant);

        return $tenant;
    }

    /**
     * An admin of $tenant, acting, with the opt-in switched on.
     */
    private function setupTenantContext(Tenant $tenant, string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $tenant->addUser($user, 'member');

        $roleService = app(TenantRoleService::class);
        $roleService->setupDefaultRoles($tenant);
        $roleService->syncRole($user, $role, $tenant);

        $this->actingAs($user)
            ->withTenant($tenant)
            ->withSession(['current_tenant_id' => $tenant->id]);

        // AppSetting::set() returns silently with no tenant in session, so the session has
        // to be established first — session(), not withSession(), because the flag is read
        // in the same process as the request that follows.
        session(['current_tenant_id' => $tenant->id]);
        AppSetting::set('ai_agents_enabled', true, 'boolean', 'ai');

        return $user;
    }

    /**
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

    // ---------------------------------------------------------------- skills

    public function test_the_skills_index_lists_name_category_agent_count_and_status(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $skill = Skill::factory()->create(['tenant_id' => $tenant->id, 'name' => 'MySQL', 'skill_category' => 'Technical']);
        $unheld = Skill::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Sentiment Analysis']);

        foreach (['Alpha Agent', 'Beta Agent'] as $name) {
            AiAgent::factory()->create(['tenant_id' => $tenant->id, 'name' => $name])->attachSkill($skill);
        }

        $response = $this->get($this->tenantUrl('/skills'));

        $response->assertOk()
            ->assertSee('MySQL')
            ->assertSee('Technical')
            ->assertSee('2 agents')
            // The 12 catalogue skills no agent holds are not a fault: 0 agents, still listed.
            ->assertSee('Sentiment Analysis')
            ->assertSee('0 agents')
            ->assertSee('Active');

        // The count comes from withCount, not a per-row relation read.
        $listed = $response->viewData('skills');
        $this->assertSame(2, $listed->firstWhere('id', $skill->id)->ai_agents_count);
        $this->assertSame(0, $listed->firstWhere('id', $unheld->id)->ai_agents_count);
        $this->assertFalse($listed->first()->relationLoaded('aiAgents'));
    }

    public function test_the_skills_index_has_no_description_column(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        Skill::factory()->create(['tenant_id' => $tenant->id, 'description' => 'A description nobody asked to see here.']);

        $this->get($this->tenantUrl('/skills'))
            ->assertOk()
            ->assertDontSee('A description nobody asked to see here.');
    }

    public function test_a_skill_can_be_created_and_appears_in_the_list(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $this->post($this->tenantUrl('/skills'), [
            'name' => 'Kubernetes',
            'skill_category' => 'Technical',
            'is_active' => '1',
        ])->assertRedirect($this->tenantUrl('/skills'));

        $skill = Skill::query()->where('name', 'Kubernetes')->firstOrFail();
        $this->assertSame('kubernetes', $skill->slug);
        $this->assertSame($tenant->id, $skill->tenant_id);

        $this->get($this->tenantUrl('/skills'))->assertSee('Kubernetes');
    }

    public function test_a_duplicate_skill_name_in_this_workspace_is_rejected_but_the_same_name_in_another_workspace_is_not(): void
    {
        $other = $this->createEnterpriseTenant();
        Skill::factory()->create(['tenant_id' => $other->id, 'name' => 'MySQL', 'slug' => 'mysql']);

        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        // Another workspace already has it; this one may still create it.
        $this->post($this->tenantUrl('/skills'), ['name' => 'MySQL'])->assertSessionHasNoErrors();
        $this->assertSame(2, DB::table('skills')->where('slug', 'mysql')->count());

        // The same workspace may not.
        $this->post($this->tenantUrl('/skills'), ['name' => 'MySQL'])->assertSessionHasErrors('name');
        $this->assertSame(1, DB::table('skills')->where('tenant_id', $tenant->id)->count());
    }

    public function test_the_skills_index_search_and_category_and_status_filters_narrow_the_list(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        Skill::factory()->create(['tenant_id' => $tenant->id, 'name' => 'MySQL Tuning', 'skill_category' => 'Technical']);
        // A category this workspace invented, which is not in the seeded vocabulary.
        Skill::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Warehouse Layout', 'skill_category' => 'Logistics Floorwork']);
        Skill::factory()->inactive()->create(['tenant_id' => $tenant->id, 'name' => 'Retired Skill', 'skill_category' => 'Technical']);

        $this->get($this->tenantUrl('/skills?search=MySQL'))
            ->assertSee('MySQL Tuning')->assertDontSee('Warehouse Layout');

        $this->assertNotContains('Logistics Floorwork', SkillCatalogue::CATEGORIES);
        $this->get($this->tenantUrl('/skills?category=Logistics+Floorwork'))
            ->assertSee('Warehouse Layout')->assertDontSee('MySQL Tuning');

        $this->get($this->tenantUrl('/skills?status=inactive'))
            ->assertSee('Retired Skill')->assertDontSee('MySQL Tuning');
    }

    public function test_deactivating_a_skill_keeps_its_agent_pivots_and_removes_it_from_the_agent_picker(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $skill = Skill::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Legacy VPN']);
        $held = Skill::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Held Skill']);
        $agent = AiAgent::factory()->create(['tenant_id' => $tenant->id]);
        $agent->attachSkill($held);

        $this->post($this->tenantUrl("/skills/{$skill->id}/toggle"))->assertRedirect();
        $this->assertFalse($skill->fresh()->is_active);

        // Still attached elsewhere? The pivot is untouched either way.
        $this->assertSame(1, DB::table('ai_agent_skill')->count());

        $available = $this->get($this->tenantUrl("/ai-agents/{$agent->id}/edit"))
            ->assertOk()
            ->viewData('availableSkills');

        $this->assertNotContains('Legacy VPN', $available->pluck('name')->all());

        // And the diff is audited.
        $this->assertTrue(ActivityLog::query()
            ->where('subject_type', Skill::class)
            ->where('subject_id', $skill->id)
            ->where('action', 'updated')
            ->exists());
    }

    public function test_a_skill_held_by_an_agent_cannot_be_deleted_and_the_error_names_the_agents(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $skill = Skill::factory()->create(['tenant_id' => $tenant->id, 'name' => 'MySQL']);
        AiAgent::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Database Engineer Agent'])->attachSkill($skill);

        // A redirect with a readable error, never a 500 from the model's guard.
        $response = $this->from($this->tenantUrl('/skills'))->delete($this->tenantUrl("/skills/{$skill->id}"));

        $response->assertRedirect($this->tenantUrl('/skills'));
        $this->assertStringContainsString('Database Engineer Agent', session('errors')->first('delete'));
        $this->assertDatabaseHas('skills', ['id' => $skill->id]);
    }

    public function test_an_unheld_skill_deletes(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $skill = Skill::factory()->create(['tenant_id' => $tenant->id]);

        $this->delete($this->tenantUrl("/skills/{$skill->id}"))
            ->assertRedirect($this->tenantUrl('/skills'));

        $this->assertDatabaseMissing('skills', ['id' => $skill->id]);
    }

    // ---------------------------------------------------------------- agents

    public function test_an_agent_can_be_created_with_a_role_type_priority_and_prompt_and_an_unlisted_role_type_is_rejected(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $this->post($this->tenantUrl('/ai-agents'), [
            'name' => 'Software Engineer Agent',
            'role_type' => 'specialist',
            'priority' => 20,
            'system_prompt' => 'Focus on code defects only.',
        ])->assertSessionHasNoErrors();

        $agent = AiAgent::query()->where('slug', 'software-engineer-agent')->firstOrFail();
        $this->assertSame('specialist', $agent->role_type);
        $this->assertSame(20, $agent->priority);
        $this->assertSame('Focus on code defects only.', $agent->effectiveSystemPrompt());

        $this->post($this->tenantUrl('/ai-agents'), ['name' => 'Nonsense Agent', 'role_type' => 'wizard'])
            ->assertSessionHasErrors('role_type');
        $this->assertDatabaseMissing('ai_agents', ['slug' => 'nonsense-agent']);
    }

    public function test_two_agents_in_one_workspace_cannot_share_a_slug_but_two_workspaces_can(): void
    {
        $other = $this->createEnterpriseTenant();
        AiAgent::factory()->create(['tenant_id' => $other->id, 'slug' => 'triage-agent', 'name' => 'Triage Agent']);

        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $this->post($this->tenantUrl('/ai-agents'), ['name' => 'Triage Agent', 'role_type' => 'triage'])
            ->assertSessionHasNoErrors();

        $this->post($this->tenantUrl('/ai-agents'), ['name' => 'Triage Agent', 'role_type' => 'triage'])
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, DB::table('ai_agents')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(2, DB::table('ai_agents')->where('slug', 'triage-agent')->count());
    }

    public function test_an_empty_system_prompt_stores_null_and_an_override_is_isolated_to_that_agent(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $edited = AiAgent::factory()->create(['tenant_id' => $tenant->id]);
        $untouched = AiAgent::factory()->create(['tenant_id' => $tenant->id]);

        $this->put($this->tenantUrl("/ai-agents/{$edited->id}"), [
            'name' => $edited->name,
            'role_type' => $edited->role_type,
            'system_prompt' => 'Only discuss billing.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Only discuss billing.', $edited->fresh()->effectiveSystemPrompt());
        $this->assertSame(AiAgent::DEFAULT_SYSTEM_PROMPT, $untouched->fresh()->effectiveSystemPrompt());

        // Clearing the textarea stores NULL, not '': "unedited" stays one state, so a
        // wording change to DEFAULT_SYSTEM_PROMPT still reaches the row.
        $this->put($this->tenantUrl("/ai-agents/{$edited->id}"), [
            'name' => $edited->name,
            'role_type' => $edited->role_type,
            'system_prompt' => '',
        ])->assertSessionHasNoErrors();

        $this->assertNull($edited->fresh()->system_prompt);
        $this->assertNotNull(DB::table('ai_agents')->where('id', $edited->id)->value('id'));
        $this->get($this->tenantUrl("/ai-agents/{$edited->id}/edit"))
            ->assertOk()
            ->assertSee('Leave empty to use the standard instructions:');
    }

    public function test_toggling_active_writes_an_activity_log_diff_for_both_resources(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $agent = AiAgent::factory()->create(['tenant_id' => $tenant->id]);
        $skill = Skill::factory()->create(['tenant_id' => $tenant->id]);

        $this->post($this->tenantUrl("/ai-agents/{$agent->id}/toggle"))->assertRedirect();
        $this->post($this->tenantUrl("/skills/{$skill->id}/toggle"))->assertRedirect();

        $this->assertFalse($agent->fresh()->is_active);
        $this->assertFalse($skill->fresh()->is_active);

        foreach ([[AiAgent::class, $agent->id], [Skill::class, $skill->id]] as [$type, $id]) {
            $log = ActivityLog::query()
                ->where('subject_type', $type)->where('subject_id', $id)->where('action', 'updated')
                ->firstOrFail();
            $this->assertArrayHasKey('is_active', (array) $log->changes);
        }
    }

    public function test_deleting_an_assigned_agent_is_refused_with_a_validation_error_and_the_row_survives(): void
    {
        $tenant = $this->createEnterpriseTenant();
        DepartmentSeeder::seedForTenant($tenant);
        $this->setupTenantContext($tenant);

        $agent = AiAgent::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Software Triage Agent']);
        $department = Department::query()->firstOrFail();
        $department->assignAiAgent($agent, 'primary', 1, true, true);

        $response = $this->from($this->tenantUrl('/ai-agents'))->delete($this->tenantUrl("/ai-agents/{$agent->id}"));

        // A redirect with an error the administrator can read — not a 500 out of the
        // model's deleting guard, which is what a builder delete would have hidden.
        $response->assertRedirect($this->tenantUrl('/ai-agents'));
        $this->assertStringContainsString($department->name, session('errors')->first('delete'));
        $this->assertDatabaseHas('ai_agents', ['id' => $agent->id]);
    }

    public function test_an_unassigned_agent_deletes(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $agent = AiAgent::factory()->create(['tenant_id' => $tenant->id]);

        $this->delete($this->tenantUrl("/ai-agents/{$agent->id}"))
            ->assertRedirect($this->tenantUrl('/ai-agents'));

        $this->assertDatabaseMissing('ai_agents', ['id' => $agent->id]);
    }

    // ---------------------------------------------------------------- the skills editor

    public function test_the_editor_attaches_multiple_skills_with_proficiency_and_rejects_the_same_skill_twice(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $agent = AiAgent::factory()->create(['tenant_id' => $tenant->id]);
        $a = Skill::factory()->create(['tenant_id' => $tenant->id]);
        $b = Skill::factory()->create(['tenant_id' => $tenant->id]);

        $this->put($this->tenantUrl("/ai-agents/{$agent->id}/skills"), [
            'skills' => [
                ['skill_id' => $a->id, 'proficiency' => 'expert', 'weight' => 80, 'is_required' => '1'],
                ['skill_id' => $b->id, 'proficiency' => 'basic'],
            ],
        ])->assertSessionHasNoErrors();

        $rows = DB::table('ai_agent_skill')->where('ai_agent_id', $agent->id)->get()->keyBy('skill_id');
        $this->assertCount(2, $rows);
        $this->assertSame('expert', $rows[$a->id]->proficiency);
        $this->assertSame(80, (int) $rows[$a->id]->weight);
        $this->assertSame(1, (int) $rows[$a->id]->is_required);
        // Pivot rows carry the workspace, read through the query builder so no scope can
        // make this pass by hiding a row.
        $this->assertSame([$tenant->id], $rows->pluck('tenant_id')->unique()->map(fn ($id) => (int) $id)->all());

        // The same skill twice is a message, not a silent collapse into updateOrCreate.
        $this->put($this->tenantUrl("/ai-agents/{$agent->id}/skills"), [
            'skills' => [
                ['skill_id' => $a->id, 'proficiency' => 'basic'],
                ['skill_id' => $a->id, 'proficiency' => 'expert'],
            ],
        ])->assertSessionHasErrors('skills.1.skill_id');
    }

    /**
     * The trap this package exists to avoid: syncSkills() does not detach.
     */
    public function test_removing_a_skill_from_the_editor_detaches_it_and_writes_an_audit_row(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $agent = AiAgent::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Debugger Agent']);
        $kept = Skill::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Log Analysis']);
        $removed = Skill::factory()->create(['tenant_id' => $tenant->id, 'name' => 'MySQL']);
        $agent->syncSkills([$kept->id => 'advanced', $removed->id => 'advanced']);

        // Saving the table with MySQL omitted must remove it. A version calling only
        // syncSkills() leaves the row in place and this assertion fails.
        $this->put($this->tenantUrl("/ai-agents/{$agent->id}/skills"), [
            'skills' => [['skill_id' => $kept->id, 'proficiency' => 'advanced']],
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            [$kept->id],
            DB::table('ai_agent_skill')->where('ai_agent_id', $agent->id)->pluck('skill_id')->map(fn ($id) => (int) $id)->all(),
        );

        // Not assertDontSee: once detached, MySQL correctly reappears in the "add a skill"
        // picker. What must be gone is the attached row.
        $this->assertSame(
            ['Log Analysis'],
            $this->get($this->tenantUrl("/ai-agents/{$agent->id}/edit"))->assertOk()
                ->viewData('agent')->skills->pluck('name')->all(),
        );

        // Through detachSkill(), so the pivot's deleted event wrote a readable label — a
        // raw detach() fires no events and writes nothing at all.
        $log = ActivityLog::query()->where('action', 'deleted')
            ->where('subject_type', AiAgentSkill::class)
            ->latest('id')->firstOrFail();
        // The readable label lives on subject_label: the row is hard-deleted, so an id-only
        // entry would be permanently unreadable.
        $this->assertSame('Debugger Agent → MySQL', $log->subject_label);
    }

    public function test_the_per_row_remove_button_detaches_one_skill_and_leaves_the_rest(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $agent = AiAgent::factory()->create(['tenant_id' => $tenant->id]);
        $kept = Skill::factory()->create(['tenant_id' => $tenant->id]);
        $removed = Skill::factory()->create(['tenant_id' => $tenant->id]);
        $agent->syncSkills([$kept->id => 'advanced', $removed->id => 'advanced']);

        $this->delete($this->tenantUrl("/ai-agents/{$agent->id}/skills/{$removed->id}"))->assertRedirect();

        $this->assertSame(
            [$kept->id],
            DB::table('ai_agent_skill')->where('ai_agent_id', $agent->id)->pluck('skill_id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    public function test_saving_the_editor_unchanged_changes_no_pivot_row_and_a_skill_held_by_two_agents_survives_editing_one(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $shared = Skill::factory()->create(['tenant_id' => $tenant->id]);
        $edited = AiAgent::factory()->create(['tenant_id' => $tenant->id]);
        $other = AiAgent::factory()->create(['tenant_id' => $tenant->id]);
        $edited->attachSkill($shared, 'expert', 40, true);
        $other->attachSkill($shared, 'basic', 10, false);

        $before = DB::table('ai_agent_skill')->orderBy('id')->get()->toArray();

        $this->put($this->tenantUrl("/ai-agents/{$edited->id}/skills"), [
            'skills' => [['skill_id' => $shared->id, 'proficiency' => 'expert', 'weight' => 40, 'is_required' => '1']],
        ])->assertSessionHasNoErrors();

        $after = DB::table('ai_agent_skill')->orderBy('id')->get()->toArray();
        $this->assertCount(2, $after);
        foreach ([0, 1] as $i) {
            $this->assertSame($before[$i]->proficiency, $after[$i]->proficiency);
            $this->assertSame($before[$i]->weight, $after[$i]->weight);
            $this->assertSame($before[$i]->is_required, $after[$i]->is_required);
        }
    }

    public function test_a_foreign_workspaces_skill_id_posted_to_the_editor_fails_validation(): void
    {
        $other = $this->createEnterpriseTenant();
        $foreign = Skill::factory()->create(['tenant_id' => $other->id]);

        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);
        $agent = AiAgent::factory()->create(['tenant_id' => $tenant->id]);

        $this->put($this->tenantUrl("/ai-agents/{$agent->id}/skills"), [
            'skills' => [['skill_id' => $foreign->id, 'proficiency' => 'expert']],
        ])->assertSessionHasErrors('skills.0.skill_id');

        // A validation message, not a 500 out of attachSkill()'s cross-tenant assertion.
        $this->assertSame(0, DB::table('ai_agent_skill')->count());
    }

    // ---------------------------------------------------------------- isolation, empty state, install

    public function test_another_workspaces_skills_and_agents_are_absent_from_the_indexes_and_their_edit_urls_404(): void
    {
        $other = $this->createEnterpriseTenant();
        $foreignSkill = Skill::factory()->create(['tenant_id' => $other->id, 'name' => 'Foreign Skill']);
        $foreignAgent = AiAgent::factory()->create(['tenant_id' => $other->id, 'name' => 'Foreign Agent']);

        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $this->get($this->tenantUrl('/skills'))->assertOk()->assertDontSee('Foreign Skill');
        $this->get($this->tenantUrl('/ai-agents'))->assertOk()->assertDontSee('Foreign Agent');

        $this->get($this->tenantUrl("/skills/{$foreignSkill->id}/edit"))->assertNotFound();
        $this->get($this->tenantUrl("/ai-agents/{$foreignAgent->id}/edit"))->assertNotFound();
        $this->delete($this->tenantUrl("/skills/{$foreignSkill->id}"))->assertNotFound();
        $this->delete($this->tenantUrl("/ai-agents/{$foreignAgent->id}"))->assertNotFound();

        $this->assertDatabaseHas('skills', ['id' => $foreignSkill->id]);
        $this->assertDatabaseHas('ai_agents', ['id' => $foreignAgent->id]);
    }

    public function test_the_indexes_render_an_empty_state_for_a_workspace_with_nothing_installed(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        $this->get($this->tenantUrl('/skills'))->assertOk()
            ->assertSee('No skills yet — install the default catalogue or create one.');
        $this->get($this->tenantUrl('/ai-agents'))->assertOk()
            ->assertSee('No agents configured yet — install the default catalogue or create one.');
    }

    public function test_the_catalogue_preview_writes_nothing_and_the_install_then_creates_the_catalogue(): void
    {
        $tenant = $this->createEnterpriseTenant();
        DepartmentSeeder::seedForTenant($tenant);
        $this->setupTenantContext($tenant);

        $before = $this->counts();

        $this->get($this->tenantUrl('/ai-agents/catalogue'))->assertOk()->assertSee('This install would create');
        $this->assertSame($before, $this->counts(), 'The preview must be a dry run.');

        $this->post($this->tenantUrl('/ai-agents/catalogue'))
            ->assertRedirect($this->tenantUrl('/ai-agents/setup'));

        $after = $this->counts();
        $this->assertSame(count(SkillCatalogue::SKILLS), $after['skills']);
        $this->assertSame(count(AgentCatalogue::AGENTS), $after['ai_agents']);
        $this->assertGreaterThan(0, $after['department_ai_agent']);

        // The roster paginates at 20, so look the first catalogue agent up rather than
        // assuming which page it lands on.
        $this->get($this->tenantUrl('/ai-agents?search='.urlencode(AgentCatalogue::AGENTS[0]['name'])))
            ->assertOk()->assertSee(AgentCatalogue::AGENTS[0]['name']);
    }

    public function test_a_second_install_creates_nothing_and_says_so(): void
    {
        $tenant = $this->createEnterpriseTenant();
        DepartmentSeeder::seedForTenant($tenant);
        $this->setupTenantContext($tenant);

        $this->post($this->tenantUrl('/ai-agents/catalogue'));
        $afterFirst = $this->counts();

        $this->post($this->tenantUrl('/ai-agents/catalogue'))
            ->assertSessionHas('success', 'Everything in the default catalogue is already installed — nothing was changed.');

        $this->assertSame($afterFirst, $this->counts());
    }

    public function test_an_install_on_a_workspace_without_the_new_departments_reports_the_missing_codes(): void
    {
        $tenant = $this->createEnterpriseTenant();
        DepartmentSeeder::seedForTenant($tenant);
        // An older workspace, predating the two departments the command adds.
        Department::query()->whereIn('code', ['OPS', 'PRESALES'])->delete();
        $this->setupTenantContext($tenant);

        $this->get($this->tenantUrl('/ai-agents/catalogue'))->assertOk()
            ->assertSee('OPS')->assertSee('PRESALES')
            ->assertSee('departments:add-missing-defaults');

        $this->post($this->tenantUrl('/ai-agents/catalogue'))
            ->assertRedirect($this->tenantUrl('/ai-agents/setup'));

        $this->get($this->tenantUrl('/ai-agents/setup'))->assertOk()
            ->assertSee('departments:add-missing-defaults');
    }

    /**
     * The hub is exempt from the opt-in so the switch stays reachable — which means it must
     * not offer controls that are themselves gated. A green 403 test and a live button on the
     * same screen is exactly how a dead control ships.
     */
    public function test_the_hub_offers_no_gated_links_while_the_opt_in_is_off(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);

        AppSetting::set('ai_agents_enabled', false, 'boolean', 'ai');

        $this->get($this->tenantUrl('/ai-agents/setup'))->assertOk()
            // Paths, not route(): the tenant slug comes from the request's URL defaults, which
            // are not set in the test process. /ai-agents is deliberately not asserted — it is a
            // prefix of the hub's own URL, which is present and must be.
            ->assertDontSee($this->tenantUrl('/ai-agents/catalogue'))
            ->assertDontSee($this->tenantUrl('/skills'))
            ->assertSee('Enable AI agents above to install the catalogue.');

        AppSetting::set('ai_agents_enabled', true, 'boolean', 'ai');

        $this->get($this->tenantUrl('/ai-agents/setup'))->assertOk()
            ->assertSee($this->tenantUrl('/ai-agents/catalogue'))
            ->assertSee($this->tenantUrl('/skills'));
    }

    public function test_the_missing_department_notice_names_a_runnable_command(): void
    {
        $tenant = $this->createEnterpriseTenant();
        DepartmentSeeder::seedForTenant($tenant);
        Department::query()->whereIn('code', ['OPS', 'PRESALES'])->delete();
        $this->setupTenantContext($tenant);

        // AddMissingDefaultDepartments refuses to run without --tenant or --all, so the id
        // has to be in the notice or a copy-paste errors out.
        $this->get($this->tenantUrl('/ai-agents/catalogue'))->assertOk()
            ->assertSee("departments:add-missing-defaults --tenant={$tenant->id}");

        $this->post($this->tenantUrl('/ai-agents/catalogue'));

        $this->get($this->tenantUrl('/ai-agents/setup'))->assertOk()
            ->assertSee("departments:add-missing-defaults --tenant={$tenant->id}");
    }

    public function test_the_hub_shows_the_counts_and_the_toggle_persists(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->setupTenantContext($tenant);
        Skill::factory()->count(3)->create(['tenant_id' => $tenant->id]);
        AiAgent::factory()->count(2)->create(['tenant_id' => $tenant->id]);

        $response = $this->get($this->tenantUrl('/ai-agents/setup'))->assertOk();
        $this->assertSame(3, $response->viewData('skillCount'));
        $this->assertSame(2, $response->viewData('agentCount'));

        $this->post($this->tenantUrl('/ai-agents/setup'), [])->assertRedirect();
        $this->assertDatabaseHas('app_settings', [
            'tenant_id' => $tenant->id,
            'key' => 'ai_agents_enabled',
            'value' => '0',
        ]);
    }

    public function test_no_outbound_http_request_is_made(): void
    {
        $tenant = $this->createEnterpriseTenant();
        DepartmentSeeder::seedForTenant($tenant);
        $this->setupTenantContext($tenant);

        $this->post($this->tenantUrl('/skills'), ['name' => 'Kubernetes']);
        $this->post($this->tenantUrl('/ai-agents'), ['name' => 'Ops Agent', 'role_type' => 'specialist']);
        $agent = AiAgent::query()->where('slug', 'ops-agent')->firstOrFail();
        $skill = Skill::query()->where('slug', 'kubernetes')->firstOrFail();
        $this->put($this->tenantUrl("/ai-agents/{$agent->id}/skills"), [
            'skills' => [['skill_id' => $skill->id, 'proficiency' => 'expert']],
        ]);
        $this->post($this->tenantUrl('/ai-agents/catalogue'));
        $this->get($this->tenantUrl("/ai-agents/{$agent->id}/edit"));

        Http::assertNothingSent();
    }
}
