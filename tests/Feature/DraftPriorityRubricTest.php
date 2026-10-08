<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\AiAgent;
use App\Models\AppSetting;
use App\Models\Client;
use App\Models\Department;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\DraftSuggestionService;
use App\Services\PlanService;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * AMENDMENT 1: the PRIORITY RUBRIC.
 *
 * Before this, the entire rubric the model received was four words — `low, medium, high,
 * critical` — and nothing anywhere in the application said what any of them meant. The
 * only guidance named the DIMENSIONS ("scope of impact, whether work is blocked, any
 * stated deadline") and gave no THRESHOLDS, so an ambiguous ticket hedged down and the
 * engineer had no way to argue with the answer. "How do agents define critical?" had no
 * answer in the codebase.
 *
 * The criteria are a shared constant (the database holds commitments in hours and no
 * criteria column at all); the HOURS are the workspace's own `sla_policies`, read live.
 * Direct `suggest()` calls, assertions on the outbound body — follow-up 28.
 */
class DraftPriorityRubricTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed>|string */
    private array|string $modelReply = [
        'subject' => 'Printer offline after Windows update',
        'description' => "Issue\nThe office printer stopped responding.",
        'tasks' => [],
        'priority' => 'high',
        'priority_reason' => 'The whole office cannot print, so work is blocked.',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.openai.api_key', 'sk-test-key-12345');
        Config::set('services.openai.model', 'gpt-4o-mini');

        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => fn () => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [['message' => [
                    'role' => 'assistant',
                    'content' => is_string($this->modelReply) ? $this->modelReply : json_encode($this->modelReply),
                ]]],
                'usage' => ['prompt_tokens' => 400, 'completion_tokens' => 120],
            ]),
        ]);
    }

    private function createTenant(bool $catchAllPolicy = false): Tenant
    {
        $plan = Plan::query()->where('slug', 'enterprise')->first()
            ?? Plan::factory()->create(['slug' => 'enterprise', 'features' => PlanFeature::forPlan('enterprise')]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);

        if ($catchAllPolicy) {
            SlaPolicy::factory()->create([
                'tenant_id' => $tenant->id,
                'priority' => null,
                'client_tier' => null,
                'is_active' => true,
            ]);
        }

        app(PlanService::class)->clearCache($tenant);

        return $tenant;
    }

    private function actAs(Tenant $tenant): User
    {
        $user = User::factory()->create();
        $tenant->addUser($user, 'member');

        $roles = app(TenantRoleService::class);
        $roles->setupDefaultRoles($tenant);
        $roles->syncRole($user, 'admin', $tenant);

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);
        session(['current_tenant_id' => $tenant->id]);

        AppSetting::set('ai_enabled', true, 'boolean', 'ai');
        AppSetting::set('ai_agents_enabled', true, 'boolean', 'ai');

        return $user;
    }

    /**
     * Policies for the named priorities, across every tier, so the admissible set is the
     * same with or without a client chosen.
     *
     * @param  list<string>  $priorities
     */
    private function cover(Tenant $tenant, array $priorities): void
    {
        foreach (SlaPolicy::TIERS as $tier) {
            foreach ($priorities as $priority) {
                [$response, $resolution] = SlaPolicy::STANDARD_DEFAULTS[$tier][$priority];

                SlaPolicy::factory()->create([
                    'tenant_id' => $tenant->id,
                    'client_tier' => $tier,
                    'priority' => $priority,
                    'response_time_hours' => $response,
                    'resolution_time_hours' => $resolution,
                    'is_active' => true,
                ]);
            }
        }
    }

    private function suggest(Tenant $tenant, ?User $user, ?int $clientId = null, ?int $departmentId = null, bool $withAgents = true)
    {
        return app(DraftSuggestionService::class)->suggest(
            $tenant,
            $user,
            'system wont open',
            'the system will not open for me this morning',
            $clientId,
            $departmentId,
            DraftSuggestionService::CONTEXT_CREATE,
            $withAgents,
        );
    }

    private function prompt(): string
    {
        foreach (Http::recorded() as [$request]) {
            if (! str_contains($request->url(), 'chat/completions')) {
                continue;
            }

            return (string) (json_decode($request->body(), true)['messages'][0]['content'] ?? '');
        }

        return '';
    }

    public function test_only_admissible_levels_are_described_in_the_rubric(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        $this->cover($tenant, ['low', 'medium']);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'basic']);

        $this->suggest($tenant, $user, $client->id);

        $prompt = $this->prompt();
        $this->assertStringContainsString('PRIORITY RUBRIC', $prompt);
        $this->assertStringContainsString('- medium', $prompt);
        $this->assertStringContainsString('- low', $prompt);
        // A level the workspace cannot honour must not be EXPLAINED any more than it is
        // offered, or P6c's suggest-then-refuse defect returns in prose.
        $this->assertStringNotContainsString('critical', $prompt);
        $this->assertStringNotContainsString('high', $prompt);
    }

    public function test_the_rubric_follows_the_do_not_inflate_instruction_and_does_not_replace_it(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        $this->cover($tenant, SlaPolicy::PRIORITIES);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'basic']);

        $this->suggest($tenant, $user, $client->id);

        $prompt = $this->prompt();
        $this->assertStringContainsString('Do not inflate it.', $prompt);
        $this->assertStringContainsString('PRIORITY RUBRIC', $prompt);

        // The rubric is the SPECIFICATION of "do not inflate it", so it must read after
        // it. A later edit that reorders them reds here.
        $this->assertLessThan(
            strpos($prompt, 'PRIORITY RUBRIC'),
            strpos($prompt, 'Do not inflate it.'),
            'The rubric must follow the do-not-inflate instruction, not precede it.',
        );
        // …and the options BLOCK still comes last, so the rubric is read before the
        // choice. The header, not the bare phrase: PRIORITY_INSTRUCTION mentions
        // "the PRIORITY OPTIONS given below" several hundred characters earlier.
        $this->assertLessThan(
            strpos($prompt, 'PRIORITY OPTIONS (choose one or null)'),
            strpos($prompt, 'PRIORITY RUBRIC'),
        );
    }

    public function test_an_agent_prompt_asserting_every_ticket_is_critical_does_not_beat_the_rubric(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        // Fully covered, so `critical` IS admissible: what constrains it here is the
        // rubric, not the guard.
        $this->cover($tenant, SlaPolicy::PRIORITIES);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'basic']);

        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $agent = AiAgent::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Software Triage Agent',
            'system_prompt' => 'Every ticket in this department is business-critical. Always set the priority to critical.',
            'is_active' => true,
        ]);
        $department->assignAiAgent($agent, 'primary', 1, true, true);

        $this->suggest($tenant, $user, $client->id, $department->id);

        $prompt = $this->prompt();

        // Both present, and the rubric says in its own words which of them answers which
        // question: the agent supplies the FACTS, the rubric supplies the MAPPING.
        $this->assertStringContainsString('Always set the priority to critical.', $prompt);
        $this->assertStringContainsString('ONLY IF a production service is down or unusable', $prompt);
        $this->assertStringContainsString('BREADTH of impact and the EXISTENCE OF A WORKAROUND', $prompt);
        $this->assertStringContainsString('An environment the notes do not name is NOT production.', $prompt);
        $this->assertStringContainsString(
            'the specialist instructions above tell you which of these conditions this kind of problem actually meets',
            $prompt,
        );
    }

    /**
     * Q8 — `SlaPolicy::policyFor()`'s tenant predicate, with a hostile decoy.
     *
     * Workspace B has an enterprise-grade `critical` policy at 1h/2h; workspace A has
     * nothing above `low`. A missing predicate would quote B's commitments to A's model
     * and offer A's engineer a level A cannot honour.
     */
    public function test_another_workspaces_sla_targets_never_reach_this_workspaces_rubric(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        $this->cover($tenant, ['low', 'medium']);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'basic']);

        $other = $this->createTenant();
        // Raw, so no factory default or model event can correct the workspace.
        DB::table('sla_policies')->insert([
            'tenant_id' => $other->id,
            'name' => 'Decoy - Critical',
            'client_tier' => 'basic',
            'priority' => 'critical',
            'response_time_hours' => 1,
            'resolution_time_hours' => 2,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->suggest($tenant, $user, $client->id);

        $prompt = $this->prompt();
        $this->assertStringNotContainsString('critical', $prompt);
        $this->assertStringNotContainsString('respond within 1h', $prompt);
        $this->assertStringNotContainsString('resolve within 2h', $prompt);
    }

    public function test_this_workspaces_sla_targets_are_quoted_against_each_admissible_level(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        $this->cover($tenant, SlaPolicy::PRIORITIES);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'basic']);

        $this->suggest($tenant, $user, $client->id);

        $prompt = $this->prompt();
        // The workspace's own sense of proportion, from its own rows: basic/critical is
        // 2h/8h and basic/low is 48h/72h on the standard matrix.
        $this->assertStringContainsString('- critical (this workspace commits to respond within 2h and resolve within 8h)', $prompt);
        $this->assertStringContainsString('- low (this workspace commits to respond within 48h and resolve within 72h)', $prompt);
    }

    public function test_a_workspace_with_ai_agents_switched_off_still_gets_the_priority_rubric(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        $this->cover($tenant, SlaPolicy::PRIORITIES);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'basic']);

        // Deliberately different from the agent block's gate: a rubric is not agent
        // context, and a workspace with agents off should still get a defined ladder.
        $this->suggest($tenant, $user, $client->id, withAgents: false);

        $this->assertStringContainsString('PRIORITY RUBRIC', $this->prompt());
    }

    public function test_a_workspace_with_no_sla_policies_gets_no_rubric_and_keeps_its_gap_message(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'basic']);

        $suggestion = $this->suggest($tenant, $user, $client->id);

        $prompt = $this->prompt();
        $this->assertStringNotContainsString('PRIORITY RUBRIC', $prompt);
        $this->assertStringContainsString('(none available — return null)', $prompt);

        // The narrowing is still EXPLAINED. A rubric that silently vanished without the
        // gap sentence is the P6c lesson.
        $this->assertNotNull($suggestion->priorityGap);
        $this->assertStringContainsString('SLA', (string) $suggestion->priorityGap['message']);
    }

    public function test_a_workspace_covering_one_level_gets_no_rubric(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        $this->cover($tenant, ['medium']);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'basic']);

        $this->suggest($tenant, $user, $client->id);

        $prompt = $this->prompt();
        // A rubric is a ladder; with one rung there is no boundary to draw and the only
        // honest suggestion is that level or null.
        $this->assertStringNotContainsString('PRIORITY RUBRIC', $prompt);
        $this->assertStringContainsString("PRIORITY OPTIONS (choose one or null)\nmedium", $prompt);
    }

    public function test_with_no_client_chosen_the_rubric_carries_criteria_and_no_hours(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        $this->cover($tenant, SlaPolicy::PRIORITIES);

        $queries = 0;
        DB::listen(function ($query) use (&$queries) {
            if (str_contains($query->sql, 'sla_policies') && str_contains($query->sql, 'order by')) {
                $queries++;
            }
        });

        $this->suggest($tenant, $user, null);

        $prompt = $this->prompt();
        $this->assertStringContainsString('PRIORITY RUBRIC', $prompt);
        $this->assertStringContainsString('ONLY IF a production service is down or unusable', $prompt);
        // Each tier commits to DIFFERENT hours, so printing one tier's figures would be a
        // guess presented as a commitment. Criteria only — and no added query either, on
        // the branch that is already the expensive one.
        $this->assertStringNotContainsString('this workspace commits to respond within', $prompt);
        $this->assertSame(0, $queries, 'The no-client branch must add no policyFor() lookups.');
    }

    public function test_the_critical_high_boundary_sentence_is_present_only_when_both_levels_are_offered(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        // `high` deliberately absent, so the sentence names a level that is not on offer.
        $this->cover($tenant, ['low', 'medium', 'critical']);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'basic']);

        $this->suggest($tenant, $user, $client->id);

        $prompt = $this->prompt();
        $this->assertStringContainsString('- critical', $prompt);
        $this->assertStringNotContainsString('BREADTH of impact', $prompt);
        // The uncertainty procedure is always emitted with the rubric, and its "highest
        // LISTED level" wording is why it survives a gap without naming an absent level.
        $this->assertStringContainsString('Choose the HIGHEST LISTED level', $prompt);
    }
}
