<?php

namespace Tests\Unit;

use App\Models\AiAgent;
use App\Models\Department;
use App\Models\Skill;
use App\Models\Tenant;
use App\Services\AI\AgentSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * P5: the read-only selection layer.
 *
 * No plan or license is set up anywhere below, deliberately: the service holds no plan
 * gate and no opt-in check (those are the caller's, see its docblock), so a test that
 * built an Enterprise tenant would be asserting a dependency that does not exist. Nor is
 * anyone logged in — the service has to work for a command and a queue worker, and
 * `TenantScope` is a no-op without a session, which is the whole reason it carries an
 * explicit tenant predicate.
 */
class AgentSelectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private AgentSelectionService $service;

    private Tenant $tenant;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        // §4: this package spends no tokens. Any outbound request is a failure, not a
        // slow test.
        Http::fake();

        $this->service = new AgentSelectionService;
        $this->tenant = Tenant::factory()->create();
        $this->department = Department::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function agent(string $name, int $priority = 0, array $attributes = []): AiAgent
    {
        return AiAgent::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'priority' => $priority,
        ], $attributes));
    }

    private function skill(string $name, array $attributes = []): Skill
    {
        return Skill::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            // Pinned: the factory randomises the category, and getAvailableSkills() orders
            // on category before name.
            'skill_category' => 'Technical',
        ], $attributes));
    }

    /**
     * §39's worked example, which is the only evidence of the intended direction.
     */
    private function seedWorkedExample(): void
    {
        $this->department->assignAiAgent($this->agent('Software Triage Agent'), 'primary', 1, true, true);
        $this->department->assignAiAgent($this->agent('Debugger Agent'), 'specialist', 20);
        $this->department->assignAiAgent($this->agent('QA Engineer Agent'), 'validator', 90);
    }

    public function test_department_agents_come_back_in_the_assignment_ranking(): void
    {
        $this->seedWorkedExample();

        $this->assertSame(
            ['Software Triage Agent', 'Debugger Agent', 'QA Engineer Agent'],
            $this->service->getDepartmentAgents($this->department)->pluck('name')->all(),
        );

        Http::assertNothingSent();
    }

    public function test_an_unranked_assignment_sorts_behind_a_ranked_one(): void
    {
        $this->seedWorkedExample();
        // priority 0 is "nobody ranked this", not "most important" — OrdersByPriority.
        $this->department->assignAiAgent($this->agent('Aardvark Agent'), 'support', 0);

        $this->assertSame(
            ['Software Triage Agent', 'Debugger Agent', 'QA Engineer Agent', 'Aardvark Agent'],
            $this->service->getDepartmentAgents($this->department)->pluck('name')->all(),
        );
    }

    public function test_an_inactive_agent_is_excluded_even_though_its_assignment_is_active(): void
    {
        $this->department->assignAiAgent($this->agent('Debugger Agent', 20), 'specialist', 20);
        $retired = $this->agent('Retired Agent', 1, ['is_active' => false]);
        $this->department->assignAiAgent($retired, 'primary', 1, true, true);

        $this->assertSame(
            ['Debugger Agent'],
            $this->service->getDepartmentAgents($this->department)->pluck('name')->all(),
        );
        // The assignment row itself is active — only the agent's own flag excludes it.
        $this->assertDatabaseHas('department_ai_agent', [
            'department_id' => $this->department->id,
            'ai_agent_id' => $retired->id,
            'is_active' => true,
        ]);
    }

    public function test_an_inactive_assignment_is_excluded_even_though_the_agent_is_active(): void
    {
        $this->department->assignAiAgent($this->agent('Debugger Agent', 20), 'specialist', 20);
        $standDown = $this->agent('Stood Down Agent', 1);
        $this->department->assignAiAgent($standDown, 'primary', 1, false, true);

        $this->assertTrue($standDown->fresh()->is_active, 'the agent itself is still switched on');
        $this->assertSame(
            ['Debugger Agent'],
            $this->service->getDepartmentAgents($this->department)->pluck('name')->all(),
        );
        $this->assertSame([], $this->service->getAutoInvokeAgents($this->department)->pluck('name')->all());
    }

    public function test_auto_invoke_returns_only_the_agents_marked_for_it(): void
    {
        $this->seedWorkedExample();

        $this->assertSame(
            ['Software Triage Agent'],
            $this->service->getAutoInvokeAgents($this->department)->pluck('name')->all(),
        );
    }

    public function test_available_skills_are_de_duplicated_across_agents_and_exclude_inactive_ones(): void
    {
        $shared = $this->skill('MySQL');
        $alsoShared = $this->skill('Apache');
        $own = $this->skill('Query Optimization', ['skill_category' => 'Analysis']);
        $switchedOff = $this->skill('Livewire', ['is_active' => false]);

        $database = $this->agent('Database Engineer Agent', 1);
        $backend = $this->agent('Software Engineer Agent', 2);
        $docs = $this->agent('Documentation Agent', 3);

        foreach ([$database, $backend, $docs] as $agent) {
            $agent->attachSkill($shared, 'advanced');
            $agent->attachSkill($alsoShared, 'advanced');
        }
        $database->attachSkill($own, 'expert');
        $docs->attachSkill($switchedOff, 'basic');

        $this->department->assignAiAgent($database, 'primary', 1);
        $this->department->assignAiAgent($backend, 'specialist', 2);
        $this->department->assignAiAgent($docs, 'support', 3);

        // Held by three agents, returned once; the deactivated skill is gone even though
        // it is still attached (criterion 3).
        // Category first, then name — the union of several agents' skills has no inherent
        // order, so one is imposed.
        $this->assertSame(
            ['Query Optimization', 'Apache', 'MySQL'],
            $this->service->getAvailableSkills($this->department)->pluck('name')->all(),
        );
        $this->assertDatabaseHas('ai_agent_skill', [
            'ai_agent_id' => $docs->id,
            'skill_id' => $switchedOff->id,
        ]);
    }

    public function test_skills_of_an_inactive_agent_do_not_reach_the_available_list(): void
    {
        $retired = $this->agent('Retired Agent', 1, ['is_active' => false]);
        $retired->attachSkill($this->skill('Kubernetes'), 'expert');
        $this->department->assignAiAgent($retired, 'primary', 1);

        $this->assertSame([], $this->service->getAvailableSkills($this->department)->all());
    }

    public function test_a_department_with_no_agents_returns_empty_collections_rather_than_throwing(): void
    {
        $bare = Department::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertTrue($this->service->getDepartmentAgents($bare)->isEmpty());
        $this->assertTrue($this->service->getAutoInvokeAgents($bare)->isEmpty());
        $this->assertTrue($this->service->getAvailableSkills($bare)->isEmpty());
        $this->assertTrue($this->service->recommendAgents($bare, 'mysql is slow')->isEmpty());
    }

    public function test_another_workspaces_agent_is_never_returned_even_from_a_mixed_assignment_row(): void
    {
        $this->seedWorkedExample();

        $other = Tenant::factory()->create();
        $foreign = AiAgent::factory()->create(['tenant_id' => $other->id, 'name' => 'Foreign Agent']);
        $foreignSkill = Skill::factory()->create(['tenant_id' => $other->id, 'name' => 'Foreign Skill']);
        DB::table('ai_agent_skill')->insert([
            'tenant_id' => $other->id,
            'ai_agent_id' => $foreign->id,
            'skill_id' => $foreignSkill->id,
            'proficiency' => 'expert',
            'is_required' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Department::assignAiAgent() refuses a cross-tenant pair, so the only way such a
        // row can exist is a raw write — which is exactly the state the service's explicit
        // tenant predicate is there to survive.
        DB::table('department_ai_agent')->insert([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'ai_agent_id' => $foreign->id,
            'role' => 'primary',
            'priority' => 1,
            'is_active' => true,
            'auto_invoke' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNotContains('Foreign Agent', $this->service->getDepartmentAgents($this->department)->pluck('name')->all());
        $this->assertNotContains('Foreign Agent', $this->service->getAutoInvokeAgents($this->department)->pluck('name')->all());
        $this->assertNotContains('Foreign Skill', $this->service->getAvailableSkills($this->department)->pluck('name')->all());
        $this->assertNotContains('Foreign Agent', $this->service->recommendAgents($this->department, 'foreign skill')->pluck('name')->all());
    }

    public function test_it_works_with_no_session_and_no_authenticated_user(): void
    {
        $this->seedWorkedExample();
        $this->agent('Database Engineer Agent', 5)->attachSkill($this->skill('MySQL'), 'expert');
        $this->department->assignAiAgent(AiAgent::where('name', 'Database Engineer Agent')->first(), 'specialist', 5);

        $this->assertGuest();
        $this->assertNull(session('current_tenant_id'));

        $this->assertCount(4, $this->service->getDepartmentAgents($this->department));
        $this->assertSame(['MySQL'], $this->service->getAvailableSkills($this->department)->pluck('name')->all());
        $this->assertSame(
            ['Database Engineer Agent'],
            $this->service->recommendAgents($this->department, 'MySQL keeps timing out', 1)->pluck('name')->all(),
        );
    }

    public function test_recommendations_rank_the_agent_holding_the_mentioned_skills_first(): void
    {
        $mysql = $this->skill('MySQL');
        $optimization = $this->skill('Query Optimization');
        $writing = $this->skill('Technical Writing');

        // The database agent is ranked *last* by assignment priority, so a result that
        // merely echoed the department ranking would put Documentation first.
        $database = $this->agent('Database Engineer Agent');
        $database->attachSkill($mysql, 'expert');
        $database->attachSkill($optimization, 'advanced');

        $docs = $this->agent('Documentation Agent');
        $docs->attachSkill($writing, 'expert');
        $docs->attachSkill($mysql, 'basic');

        $this->department->assignAiAgent($docs, 'support', 1);
        $this->department->assignAiAgent($database, 'specialist', 90);

        $result = $this->service->recommendAgents($this->department, 'MySQL query optimization is slow on the reports page');

        $this->assertSame(
            ['Database Engineer Agent', 'Documentation Agent'],
            $result->pluck('name')->all(),
        );
        // Explainable from the pivot alone: expert(4) + advanced(3) against basic(1).
        $this->assertSame(7, $result->first()->recommendation_score);
        $this->assertSame(['mysql', 'query-optimization'], $result->first()->matched_skills);
        $this->assertSame(1, $result->last()->recommendation_score);

        Http::assertNothingSent();
    }

    public function test_weight_and_required_are_read_off_the_pivot(): void
    {
        $mysql = $this->skill('MySQL');

        $weighted = $this->agent('Weighted Agent');
        $weighted->attachSkill($mysql, 'basic', 5);

        $required = $this->agent('Required Agent');
        $required->attachSkill($mysql, 'basic', null, true);

        $plain = $this->agent('Plain Agent');
        $plain->attachSkill($mysql, 'basic');

        $this->department->assignAiAgent($weighted, 'support', 3);
        $this->department->assignAiAgent($required, 'support', 2);
        $this->department->assignAiAgent($plain, 'support', 1);

        $result = $this->service->recommendAgents($this->department, 'mysql', 3);

        // basic(1) × 5 = 5, basic(1) × required(2) = 2, basic(1) = 1 — and the weakest is
        // the one the department ranked first, so the scoring is what ordered this.
        $this->assertSame(['Weighted Agent', 'Required Agent', 'Plain Agent'], $result->pluck('name')->all());
        $this->assertSame([5, 2, 1], $result->pluck('recommendation_score')->all());
    }

    /**
     * Pins the observable guarantee: all-ties come back in the department ranking, and two
     * identical calls agree.
     *
     * It does **not** pin the rank tie-break comparator in `recommendAgents()`. The ranks
     * are derived from the same ordered collection that is being sorted, so the input is
     * already in rank order and PHP's stable `uasort` would give this answer even with the
     * comparator deleted — verified, and it stays green. The comparator is there so the
     * guarantee survives anything that reorders the collection before the sort; that is a
     * code-reading guarantee, not one a test can observe from outside. Do not delete it on
     * the strength of this test passing.
     */
    public function test_equal_scores_fall_back_to_the_department_ranking_and_never_vary(): void
    {
        $mysql = $this->skill('MySQL');

        foreach (['Zulu Agent' => 90, 'Alpha Agent' => 1, 'Mike Agent' => 20] as $name => $priority) {
            $agent = $this->agent($name);
            $agent->attachSkill($mysql, 'advanced');
            $this->department->assignAiAgent($agent, 'support', $priority);
        }

        $first = $this->service->recommendAgents($this->department, 'mysql', 3)->pluck('name')->all();
        $second = $this->service->recommendAgents($this->department, 'mysql', 3)->pluck('name')->all();

        $this->assertSame(['Alpha Agent', 'Mike Agent', 'Zulu Agent'], $first);
        $this->assertSame($first, $second);
    }

    public function test_when_nothing_matches_the_fallback_assignment_leads_the_department_ranking(): void
    {
        $this->agent('Software Triage Agent', 0)->attachSkill($this->skill('Root Cause Analysis'), 'expert');

        $triage = AiAgent::where('name', 'Software Triage Agent')->first();
        $catchAll = $this->agent('Others Agent');

        $this->department->assignAiAgent($triage, 'primary', 1, true, true);
        $this->department->assignAiAgent($catchAll, 'fallback', 50);

        // Prose with no configured skill word in it. Freshdesk's "remains unassigned" is
        // the failure this must not reproduce.
        $result = $this->service->recommendAgents($this->department, 'everything is broken and the customer is upset');

        $this->assertSame(['Others Agent', 'Software Triage Agent'], $result->pluck('name')->all());
        $this->assertSame([0, 0], $result->pluck('recommendation_score')->all());
        $this->assertSame([], $result->first()->matched_skills);
    }

    public function test_empty_text_returns_the_fallback_list_rather_than_everything_scored(): void
    {
        $this->seedWorkedExample();

        $result = $this->service->recommendAgents($this->department, '   ');

        $this->assertSame(['Software Triage Agent', 'Debugger Agent', 'QA Engineer Agent'], $result->pluck('name')->all());
        $this->assertSame([0, 0, 0], $result->pluck('recommendation_score')->all());
    }

    public function test_a_skill_name_only_matches_on_whole_words(): void
    {
        $sql = $this->skill('SQL');
        $agent = $this->agent('Database Engineer Agent');
        $agent->attachSkill($sql, 'expert');
        $this->department->assignAiAgent($agent, 'specialist', 1);

        // "mysql" contains "sql"; a substring match would score this and present a
        // coincidence as a finding.
        $result = $this->service->recommendAgents($this->department, 'our mysql box fell over');

        $this->assertSame(0, $result->first()->recommendation_score);
    }

    public function test_the_limit_caps_both_the_matched_and_the_fallback_list(): void
    {
        $mysql = $this->skill('MySQL');

        foreach ([1, 2, 3, 4] as $priority) {
            $agent = $this->agent("Agent {$priority}");
            $agent->attachSkill($mysql, 'advanced');
            $this->department->assignAiAgent($agent, 'support', $priority);
        }

        $this->assertCount(2, $this->service->recommendAgents($this->department, 'mysql', 2));
        $this->assertCount(2, $this->service->recommendAgents($this->department, 'nothing here', 2));
        // A nonsense limit must not produce an empty recommendation.
        $this->assertCount(1, $this->service->recommendAgents($this->department, 'mysql', 0));
    }

    public function test_no_literal_department_code_appears_in_the_selection_path(): void
    {
        // Criterion 26 / §40: the architecture is the tables, not a branch on a code.
        $source = file_get_contents(app_path('Services/AI/AgentSelectionService.php'));

        foreach (['SOFTWARE', 'HARDWARE', 'PRESALES', 'OTHERS', 'department_id =', "code'"] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }
    }
}
