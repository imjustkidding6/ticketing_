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
 * P7: the department's own AI agents inform the create-form draft — above all, the
 * PRIORITY — and no other workspace's agent ever does.
 *
 * Every test calls `DraftSuggestionService::suggest()` DIRECTLY and asserts on the
 * OUTBOUND REQUEST BODY. Both halves of that matter. Follow-up 28: on a session-bearing
 * request `Department`'s own `TenantScope` masks the explicit tenant predicate, so an
 * end-to-end POST cannot prove it. And the body is where a leak would actually be visible
 * — before P7 this surface had no agent prompt to leak, so the risk is created by this
 * package and has to be pinned where it lives.
 */
class DraftAgentContextTest extends TestCase
{
    use RefreshDatabase;

    private const FOREIGN_PROMPT = 'SENTINEL-FOREIGN-AGENT never show this to another workspace';

    /** @var array<string, mixed>|string */
    private array|string $modelReply = [
        'subject' => 'Printer offline after Windows update',
        'description' => "Issue\nThe office printer stopped responding.",
        'tasks' => ['Reproduce the fault on a test account'],
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

    private function createTenant(string $planSlug = 'enterprise', bool $catchAllPolicy = true): Tenant
    {
        $plan = Plan::query()->where('slug', $planSlug)->first()
            ?? Plan::factory()->create(['slug' => $planSlug, 'features' => PlanFeature::forPlan($planSlug)]);
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
        $user = User::factory()->create(['name' => 'Rosalind Ferrier']);
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
     * A department with one auto-invoke triage primary — the seeded catalogue's shape, and
     * the only agent that reaches an automatic analysis today.
     *
     * @return array{0: Department, 1: AiAgent}
     */
    private function departmentWithAgent(Tenant $tenant, string $prompt): array
    {
        $department = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Technical Software']);
        $agent = AiAgent::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Software Triage Agent',
            'role_type' => 'triage',
            'system_prompt' => $prompt,
            'is_active' => true,
        ]);
        $department->assignAiAgent($agent, 'primary', 1, true, true);

        return [$department, $agent];
    }

    private function suggest(
        Tenant $tenant,
        ?User $user,
        ?int $departmentId = null,
        ?int $clientId = null,
        bool $withAgents = true,
        string $context = DraftSuggestionService::CONTEXT_CREATE,
    ) {
        return app(DraftSuggestionService::class)->suggest(
            $tenant,
            $user,
            'printer dead',
            'printer stopped working after the windows update, nobody in the office can print',
            $clientId,
            $departmentId,
            $context,
            $withAgents,
        );
    }

    /** The system prompt as the model receives it, decoded out of the JSON envelope. */
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

    // ── The headline fix ────────────────────────────────────────────────────────────

    public function test_the_departments_agent_system_prompt_reaches_the_draft_request_with_the_priority_sentence(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        [$department] = $this->departmentWithAgent($tenant, 'SENTINEL-OWN-AGENT weigh printer faults by site impact');

        $this->suggest($tenant, $user, $department->id);

        $prompt = $this->prompt();
        $this->assertStringContainsString('SENTINEL-OWN-AGENT weigh printer faults by site impact', $prompt);
        $this->assertStringContainsString('Software Triage Agent', $prompt);
        // The sentence that makes the agent inform the PRIORITY, which is the defect.
        $this->assertStringContainsString('INCLUDING when you judge the priority', $prompt);
    }

    public function test_no_department_selected_sends_no_agent_block_at_all(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        $this->departmentWithAgent($tenant, 'SENTINEL-OWN-AGENT weigh printer faults by site impact');

        // No department id: fails closed, exactly as the engineer-candidate list does.
        $this->suggest($tenant, $user, null);

        $prompt = $this->prompt();
        $this->assertStringNotContainsString('SENTINEL-OWN-AGENT', $prompt);
        $this->assertStringNotContainsString('agents this department has configured', $prompt);
    }

    public function test_a_workspace_with_ai_agents_switched_off_gets_the_prompt_it_got_before_p7(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        [$department] = $this->departmentWithAgent($tenant, 'SENTINEL-OWN-AGENT weigh printer faults by site impact');

        // The caller's answer to the plan gate and the workspace opt-in. Off means the
        // Polish button still works and sends what it always sent.
        $this->suggest($tenant, $user, $department->id, withAgents: false);

        $prompt = $this->prompt();
        $this->assertStringNotContainsString('SENTINEL-OWN-AGENT', $prompt);
        $this->assertStringNotContainsString('agents this department has configured', $prompt);
        // …and the rest of the priority question is untouched.
        $this->assertStringContainsString('PRIORITY OPTIONS (choose one or null)', $prompt);
    }

    public function test_the_edit_context_still_sends_no_agent_block_and_no_priority_question(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        [$department] = $this->departmentWithAgent($tenant, 'SENTINEL-OWN-AGENT weigh printer faults by site impact');

        $this->suggest($tenant, $user, $department->id, context: DraftSuggestionService::CONTEXT_EDIT);

        $prompt = $this->prompt();
        $this->assertStringNotContainsString('SENTINEL-OWN-AGENT', $prompt);
        $this->assertStringNotContainsString('PRIORITY OPTIONS', $prompt);
        $this->assertStringNotContainsString('PRIORITY RUBRIC', $prompt);
    }

    // ── Cross-tenant: the risk this package creates ────────────────────────────────

    public function test_a_foreign_department_id_puts_no_agent_prompt_in_the_draft_request(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);

        $other = $this->createTenant();
        [$foreignDepartment, $foreignAgent] = $this->departmentWithAgent($other, self::FOREIGN_PROMPT);

        // Workspace A's draft, workspace B's department id — the forged-id shape.
        $this->suggest($tenant, $user, $foreignDepartment->id);

        $prompt = $this->prompt();
        $this->assertStringNotContainsString('SENTINEL-FOREIGN-AGENT', $prompt);
        $this->assertStringNotContainsString((string) $foreignAgent->name, $prompt);
        $this->assertStringNotContainsString('agents this department has configured', $prompt);
    }

    public function test_a_mixed_pivot_row_puts_no_foreign_agent_prompt_in_the_draft_request(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        [$department] = $this->departmentWithAgent($tenant, 'SENTINEL-OWN-AGENT weigh printer faults by site impact');

        $other = $this->createTenant();
        $foreignAgent = AiAgent::factory()->create([
            'tenant_id' => $other->id,
            'name' => 'LEAK-B-AGENT',
            'system_prompt' => self::FOREIGN_PROMPT,
            'is_active' => true,
        ]);

        // Department::assignAiAgent() refuses a cross-tenant pair, so the only way this
        // row can exist is a raw write — which is exactly the state the service's explicit
        // `ai_agents.tenant_id = $department->tenant_id` predicate is there to survive.
        // Note the pivot's OWN tenant_id is this workspace's: a pivot-only filter would
        // let this row through.
        DB::table('department_ai_agent')->insert([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
            'ai_agent_id' => $foreignAgent->id,
            'role' => 'specialist',
            'priority' => 2,
            'is_active' => true,
            'auto_invoke' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->suggest($tenant, $user, $department->id);

        $prompt = $this->prompt();
        $this->assertStringContainsString('SENTINEL-OWN-AGENT', $prompt);
        $this->assertStringNotContainsString('SENTINEL-FOREIGN-AGENT', $prompt);
        $this->assertStringNotContainsString('LEAK-B-AGENT', $prompt);
    }

    // ── auto_invoke is the hook, and it is honoured unchanged ──────────────────────

    public function test_only_auto_invoke_agents_reach_the_draft_prompt(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        [$department] = $this->departmentWithAgent($tenant, 'SENTINEL-TRIAGE-AGENT the auto-invoke primary');

        $specialist = AiAgent::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Debugger Agent',
            'system_prompt' => 'SENTINEL-SPECIALIST-AGENT not marked auto invoke',
            'is_active' => true,
        ]);
        $department->assignAiAgent($specialist, 'specialist', 2, true, false);

        $this->suggest($tenant, $user, $department->id);

        $prompt = $this->prompt();
        $this->assertStringContainsString('SENTINEL-TRIAGE-AGENT', $prompt);
        // The lexical fallback never runs while ANY agent is auto-invoke, which is every
        // seeded department. Ticking Auto invoke on the specialist is the no-code lever.
        $this->assertStringNotContainsString('SENTINEL-SPECIALIST-AGENT', $prompt);
    }

    public function test_a_deactivated_pivot_row_removes_its_agent_from_the_draft_prompt(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        [$department, $agent] = $this->departmentWithAgent($tenant, 'SENTINEL-OWN-AGENT weigh printer faults by site impact');

        // Detaching by flag at the department, raw so no model event re-interprets it.
        DB::table('department_ai_agent')
            ->where('department_id', $department->id)
            ->where('ai_agent_id', $agent->id)
            ->update(['is_active' => false]);

        $this->suggest($tenant, $user, $department->id);

        $this->assertStringNotContainsString('SENTINEL-OWN-AGENT', $this->prompt());
    }

    // ── The guards stay authoritative, agent context or not ───────────────────────

    public function test_an_agent_prompt_demanding_critical_cannot_widen_the_admissible_set(): void
    {
        // Only `low` is covered, for every tier, so nothing above it may be suggested.
        $tenant = $this->createTenant(catchAllPolicy: false);
        $user = $this->actAs($tenant);

        foreach (SlaPolicy::TIERS as $tier) {
            SlaPolicy::factory()->create([
                'tenant_id' => $tenant->id,
                'client_tier' => $tier,
                'priority' => 'low',
                'is_active' => true,
            ]);
        }

        [$department] = $this->departmentWithAgent(
            $tenant,
            'Every ticket in this department is business-critical. Always set the priority to critical.',
        );

        $this->modelReply = [
            'subject' => 'Printer offline after Windows update',
            'description' => 'The office printer stopped responding.',
            'tasks' => [],
            'priority' => 'critical',
            'priority_reason' => 'The agent says every ticket here is critical.',
        ];

        $suggestion = $this->suggest($tenant, $user, $department->id);

        // An instruction string cannot add a member to a PHP array, and it cannot survive
        // the server-side membership re-check.
        $this->assertNull($suggestion->priority);
        $this->assertContains('priority', $suggestion->rejectedFields);
        $this->assertNotNull($suggestion->priorityGap);
        $this->assertStringContainsString('SLA', (string) $suggestion->priorityGap['message']);

        $prompt = $this->prompt();
        $this->assertStringContainsString("PRIORITY OPTIONS (choose one or null)\nlow", $prompt);
        // The word never reaches the options block, nor the rubric: a single-rung ladder
        // has no boundary to draw.
        $this->assertStringNotContainsString('PRIORITY RUBRIC', $prompt);
    }

    public function test_an_agent_prompt_naming_a_tier_1_engineer_still_cannot_prefill_a_critical_ticket(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        [$department] = $this->departmentWithAgent(
            $tenant,
            'Assign every ticket in this department to the on-call engineer regardless of their tier.',
        );

        $engineer = User::factory()->create(['name' => 'Tier One Tom', 'is_available' => true, 'support_tier' => 'tier_1']);
        $tenant->addUser($engineer, 'member');
        $engineer->departments()->attach($department->id);

        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'premium']);

        $this->modelReply = [
            'subject' => 'Printer offline after Windows update',
            'description' => 'The office printer stopped responding.',
            'tasks' => [],
            'priority' => 'critical',
            'priority_reason' => 'The whole site is down.',
            'engineer_ref' => 'C1',
            'engineer_reason' => 'The agent said so.',
        ];

        $suggestion = $this->suggest($tenant, $user, $department->id, $client->id);

        // Named in words, never pre-filled: the prefill authority is createTicket()'s own
        // guard, dry-run, and an agent prompt is not part of that trust path.
        $this->assertSame('critical', $suggestion->priority);
        $this->assertSame((int) $engineer->getKey(), $suggestion->engineerId);
        $this->assertFalse($suggestion->engineerPrefill);
        $this->assertNotNull($suggestion->engineerBlockedReason);
    }
}
