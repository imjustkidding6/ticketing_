<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Jobs\AnalyzeInternalTicketJob;
use App\Models\AiAgent;
use App\Models\AiTicketAnalysis;
use App\Models\AiUsageLog;
use App\Models\AppSetting;
use App\Models\Client;
use App\Models\Department;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\AI\AnalyzeTicketService;
use App\Services\AI\DraftSuggestionService;
use App\Services\PlanService;
use App\Support\Ai\AgentRecommendation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * The queued INTAKE analysis: the one an internally created ticket gets without anybody
 * pressing anything.
 *
 * Every test here calls `handle()` DIRECTLY. Follow-up 28 is the reason and it is not
 * negotiable: on a session-bearing request the models' own `TenantScope` masks an explicit
 * tenant predicate, so an end-to-end test proves nothing about one. The decoys are
 * raw-inserted for the same reason — a factory or a model event can quietly correct a
 * `tenant_id` and leave the hostile state untested.
 */
class AnalyzeInternalTicketJobTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed>|string */
    private array|string $modelReply = [
        'summary' => 'SENTINEL-SUMMARY printer offline',
        'polished_subject' => 'SENTINEL-POLISHED-SUBJECT',
        'polished_description' => 'SENTINEL-POLISHED-DESC structured body',
        'category_ref' => 'K1',
        'priority' => 'high',
        'checks' => ['SENTINEL-CHECK the spooler'],
        'engineer_ref' => null,
        'engineer_reason' => null,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.openai.api_key', 'sk-test-key-12345');
        Config::set('services.openai.model', 'gpt-4o-mini');

        // OPENAI_API_KEY is live here: an unmatched endpoint must fail the test rather
        // than bill the account.
        Http::preventStrayRequests();

        Http::fake([
            'https://api.openai.com/v1/embeddings' => fn () => Http::response([
                'data' => [['embedding' => array_fill(0, 8, 0.1)]],
                'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 0],
            ]),
            'https://api.openai.com/v1/chat/completions' => fn () => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [['message' => [
                    'role' => 'assistant',
                    'content' => is_string($this->modelReply) ? $this->modelReply : json_encode($this->modelReply),
                ]]],
                'usage' => ['prompt_tokens' => 900, 'completion_tokens' => 180],
            ]),
        ]);
    }

    private function createTenant(string $planSlug = 'enterprise'): Tenant
    {
        $plan = Plan::query()->where('slug', $planSlug)->first()
            ?? Plan::factory()->create(['slug' => $planSlug, 'features' => PlanFeature::forPlan($planSlug)]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);

        SlaPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'priority' => null,
            'client_tier' => null,
            'is_active' => true,
        ]);

        app(PlanService::class)->clearCache($tenant);

        return $tenant;
    }

    /**
     * The workspace switches, written while a session exists and then DELIBERATELY
     * forgotten — the job must read them against the tenant, not the session.
     */
    private function switches(Tenant $tenant, bool $aiEnabled = true, bool $agentsEnabled = true, string $timezone = 'UTC'): void
    {
        session(['current_tenant_id' => $tenant->id]);
        AppSetting::set('ai_enabled', $aiEnabled, 'boolean', 'ai');
        AppSetting::set('ai_agents_enabled', $agentsEnabled, 'boolean', 'ai');
        AppSetting::set('timezone', $timezone, 'string', 'general');
        session()->forget('current_tenant_id');
        Cache::flush();
    }

    private function engineerFor(Tenant $tenant): User
    {
        $user = User::factory()->create(['name' => 'Rosalind Ferrier']);
        $tenant->addUser($user, 'member');

        return $user;
    }

    private function ticketFor(Tenant $tenant, array $overrides = []): Ticket
    {
        $department = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Technical Software']);
        TicketCategory::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
            'name' => 'Printer Faults',
        ]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        return Ticket::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
            'category_id' => null,
            'client_id' => $client->id,
            'priority' => null,
            'status' => 'open',
            'subject' => 'Printer offline after update',
            'description' => 'The office printer stopped responding after the Windows update.',
        ], $overrides));
    }

    private function runJob(Tenant $tenant, int $ticketId, int $userId): void
    {
        (new AnalyzeInternalTicketJob($tenant->id, $ticketId, $userId))
            ->handle(app(AnalyzeTicketService::class), app(PlanService::class));
    }

    private function analyses(Tenant $tenant)
    {
        return AiTicketAnalysis::withoutGlobalScopes()->where('tenant_id', $tenant->id);
    }

    /**
     * Q1 — the creating engineer is resolved through the tenant pivot and nothing else.
     *
     * `User` carries no global scope, so this predicate is the ONLY thing separating
     * workspaces for that lookup. The decoy is raw-inserted into workspace B's pivot only.
     */
    public function test_the_creating_engineer_is_resolved_only_through_this_workspaces_pivot(): void
    {
        $tenant = $this->createTenant();
        $this->switches($tenant);
        $other = $this->createTenant();

        // A real user who is a member of B and NOT of A.
        $foreigner = User::factory()->create(['name' => 'LEAK-B-ENGINEER']);
        DB::table('tenant_user')->insert([
            'tenant_id' => $other->id,
            'user_id' => $foreigner->id,
            'role' => 'member',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ticket = $this->ticketFor($tenant, ['created_by' => $foreigner->id]);

        Log::spy();

        $this->runJob($tenant, (int) $ticket->getKey(), (int) $foreigner->getKey());

        $this->assertSame(0, $this->analyses($tenant)->count());
        Http::assertNothingSent();
        Log::shouldHaveReceived('warning')->once();

        // The converse, so the predicate is proven to be the thing doing the work rather
        // than some other refusal upstream of it: attach the same user to A and the same
        // call now writes one row attributed to them.
        DB::table('tenant_user')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $foreigner->id,
            'role' => 'member',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runJob($tenant, (int) $ticket->getKey(), (int) $foreigner->getKey());

        $this->assertSame((int) $foreigner->getKey(), (int) $this->analyses($tenant)->sole()->requested_by);
    }

    /**
     * §7 — the cost requirement: every new call lands a usage row with a non-null
     * tenant_id AND a non-null user_id. The public job passes a null user because a
     * stranger submitted the ticket; an internal ticket has a real requester.
     */
    public function test_an_attached_engineer_is_recorded_as_requested_by_and_attributed_in_usage(): void
    {
        $tenant = $this->createTenant();
        $this->switches($tenant);
        $engineer = $this->engineerFor($tenant);
        $ticket = $this->ticketFor($tenant, ['created_by' => $engineer->id]);

        $this->runJob($tenant, (int) $ticket->getKey(), (int) $engineer->getKey());

        $analysis = $this->analyses($tenant)->sole();
        $this->assertSame((int) $engineer->getKey(), (int) $analysis->requested_by);

        $logs = AiUsageLog::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get();
        $this->assertNotEmpty($logs, 'The intake analysis logged no usage row, so its spend is unattributable.');

        foreach ($logs as $log) {
            $this->assertSame($tenant->id, (int) $log->tenant_id);
            $this->assertSame((int) $engineer->getKey(), (int) $log->user_id);
        }
    }

    /** Q2 — a ticket id belonging to another workspace resolves to nothing. */
    public function test_a_ticket_id_from_another_workspace_is_never_analysed(): void
    {
        $tenant = $this->createTenant();
        $this->switches($tenant);
        $engineer = $this->engineerFor($tenant);

        $other = $this->createTenant();
        $foreignTicket = $this->ticketFor($other);

        $this->runJob($tenant, (int) $foreignTicket->getKey(), (int) $engineer->getKey());

        Http::assertNothingSent();
        $this->assertSame(0, $this->analyses($tenant)->count());
        $this->assertSame(0, $this->analyses($other)->count());
    }

    /** Q2 — `whereNull('deleted_at')`, because withoutGlobalScopes() drops SoftDeletingScope too. */
    public function test_a_ticket_soft_deleted_before_the_worker_ran_is_never_analysed(): void
    {
        $tenant = $this->createTenant();
        $this->switches($tenant);
        $engineer = $this->engineerFor($tenant);
        $ticket = $this->ticketFor($tenant);

        // Raw, so no model event can reinterpret the state the worker will actually find.
        DB::table('tickets')->where('id', $ticket->getKey())->update(['deleted_at' => now()]);

        $this->runJob($tenant, (int) $ticket->getKey(), (int) $engineer->getKey());

        Http::assertNothingSent();
        $this->assertSame(0, $this->analyses($tenant)->count());
    }

    /**
     * Q3 — the opt-in reads are pinned to this workspace.
     *
     * Both flags are on for B and absent for A. A missing predicate would read B's and
     * spend A's money against a switch A never touched.
     */
    public function test_another_workspaces_ai_agents_enabled_flag_does_not_enable_this_one(): void
    {
        $tenant = $this->createTenant();
        $other = $this->createTenant();
        $this->switches($other);

        $engineer = $this->engineerFor($tenant);
        $ticket = $this->ticketFor($tenant);

        $this->runJob($tenant, (int) $ticket->getKey(), (int) $engineer->getKey());

        Http::assertNothingSent();
        $this->assertSame(0, $this->analyses($tenant)->count());
    }

    /** Q4 — another workspace's analyses do not consume this workspace's daily cap. */
    public function test_another_workspaces_analyses_do_not_consume_this_workspaces_daily_cap(): void
    {
        Config::set('ai.analyze.daily_cap', 2);

        $tenant = $this->createTenant();
        $this->switches($tenant);
        $engineer = $this->engineerFor($tenant);
        $ticket = $this->ticketFor($tenant);

        $other = $this->createTenant();
        $this->seedAnalyses($other, 3, AiTicketAnalysis::TRIGGER_AUTO);

        $this->runJob($tenant, (int) $ticket->getKey(), (int) $engineer->getKey());

        $this->assertSame(1, $this->analyses($tenant)->count());
    }

    /** Q4 converse + §8.6 — the two automatic triggers share one budget. */
    public function test_the_intake_and_auto_triggers_share_one_daily_cap(): void
    {
        Config::set('ai.analyze.daily_cap', 2);

        $tenant = $this->createTenant();
        $this->switches($tenant);
        $engineer = $this->engineerFor($tenant);
        $ticket = $this->ticketFor($tenant);

        // One of each, so the cap is only reached if BOTH count.
        $this->seedAnalyses($tenant, 1, AiTicketAnalysis::TRIGGER_AUTO);
        $this->seedAnalyses($tenant, 1, AiTicketAnalysis::TRIGGER_INTAKE);

        Log::spy();

        $this->runJob($tenant, (int) $ticket->getKey(), (int) $engineer->getKey());

        Http::assertNothingSent();
        $this->assertSame(2, $this->analyses($tenant)->count());
        Log::shouldHaveReceived('info')->once();
    }

    /**
     * Raw-inserted analysis rows for the cap arithmetic, so no factory default or model
     * event can quietly set a different `trigger` or `tenant_id` than the one under test.
     */
    private function seedAnalyses(Tenant $tenant, int $count, string $trigger): void
    {
        // One filler ticket, reused: `ticket_id` is NOT NULL on this table, and
        // `auto_for_ticket_id` stays null so the one-automatic-run unique index is not the
        // thing under test here.
        $filler = Ticket::factory()->create(['tenant_id' => $tenant->id, 'priority' => null]);

        for ($i = 0; $i < $count; $i++) {
            DB::table('ai_ticket_analyses')->insert([
                'tenant_id' => $tenant->id,
                'ticket_id' => $filler->getKey(),
                'requested_by' => null,
                'trigger' => $trigger,
                'auto_for_ticket_id' => null,
                'status' => AiTicketAnalysis::STATUS_SUCCEEDED,
                'model' => 'gpt-4o-mini',
                'prompt_tokens' => 10,
                'completion_tokens' => 10,
                'estimated_cost' => 0,
                'latency_ms' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** §3.2 rows 13–14 — all four run-time gates, one assertion each. */
    public function test_a_withdrawn_plan_feature_or_opt_in_between_dispatch_and_run_spends_nothing(): void
    {
        // ai_enabled off.
        $a = $this->createTenant();
        $this->switches($a, aiEnabled: false);
        $this->runJob($a, (int) $this->ticketFor($a)->getKey(), (int) $this->engineerFor($a)->getKey());
        $this->assertSame(0, $this->analyses($a)->count());

        // ai_agents_enabled off.
        $b = $this->createTenant();
        $this->switches($b, agentsEnabled: false);
        $this->runJob($b, (int) $this->ticketFor($b)->getKey(), (int) $this->engineerFor($b)->getKey());
        $this->assertSame(0, $this->analyses($b)->count());

        // Business carries neither ai_chatbot nor ai_agents.
        $c = $this->createTenant('business');
        $this->switches($c);
        $this->runJob($c, (int) $this->ticketFor($c)->getKey(), (int) $this->engineerFor($c)->getKey());
        $this->assertSame(0, $this->analyses($c)->count());

        // Suspended workspace.
        $d = $this->createTenant();
        $this->switches($d);
        $d->update(['suspended_at' => now()]);
        $this->runJob($d, (int) $this->ticketFor($d)->getKey(), (int) $this->engineerFor($d)->getKey());
        $this->assertSame(0, $this->analyses($d)->count());

        Http::assertNothingSent();
    }

    /** §8.6 — the intake run inherits the one-automatic-analysis-per-ticket guarantee. */
    public function test_an_intake_analysis_writes_auto_for_ticket_id_and_a_second_run_is_idempotent(): void
    {
        $tenant = $this->createTenant();
        $this->switches($tenant);
        $engineer = $this->engineerFor($tenant);
        $ticket = $this->ticketFor($tenant);

        $this->runJob($tenant, (int) $ticket->getKey(), (int) $engineer->getKey());
        $this->runJob($tenant, (int) $ticket->getKey(), (int) $engineer->getKey());

        $analysis = $this->analyses($tenant)->sole();
        $this->assertSame((int) $ticket->getKey(), (int) $analysis->auto_for_ticket_id);

        // The second run must not have paid for a second completion either.
        Http::assertSentCount(2); // one embedding + one chat, from the FIRST run only
    }

    /** §0.1's payoff: the panel on tickets.show is hydrated with no click. */
    public function test_the_intake_run_writes_the_intake_trigger_and_hydrates_the_panel(): void
    {
        $tenant = $this->createTenant();
        $this->switches($tenant);
        $engineer = $this->engineerFor($tenant);
        $ticket = $this->ticketFor($tenant);

        $this->runJob($tenant, (int) $ticket->getKey(), (int) $engineer->getKey());

        $analysis = $this->analyses($tenant)->sole();
        $this->assertSame(AiTicketAnalysis::TRIGGER_INTAKE, $analysis->trigger);
        $this->assertSame(AiTicketAnalysis::STATUS_SUCCEEDED, $analysis->status);

        $panel = $analysis->toPanelArray();
        $this->assertNotNull($panel);
        $this->assertSame('SENTINEL-SUMMARY printer offline', $panel['summary']);
    }

    /** §3.2 row 11 ① — refused before any spend. */
    public function test_a_department_belonging_to_another_workspace_refuses_the_run_before_any_spend(): void
    {
        $tenant = $this->createTenant();
        $this->switches($tenant);
        $engineer = $this->engineerFor($tenant);

        $other = $this->createTenant();
        $foreignDepartment = Department::factory()->create(['tenant_id' => $other->id, 'name' => 'LEAK-B-DEPARTMENT']);

        $ticket = $this->ticketFor($tenant);
        // Raw, because StoreTicketRequest's tenantExists() makes this state unreachable
        // through the form — which is the point of a second defence.
        DB::table('tickets')->where('id', $ticket->getKey())->update(['department_id' => $foreignDepartment->id]);

        Log::spy();

        $this->runJob($tenant, (int) $ticket->getKey(), (int) $engineer->getKey());

        Http::assertNothingSent();
        $this->assertSame(0, $this->analyses($tenant)->count());
        Log::shouldHaveReceived('warning')->once();
    }

    /**
     * "One prompt, not two" and "one rubric, not two", proven where it matters: the two
     * OUTBOUND bodies. The unit test pins the builders' text; this pins that both surfaces
     * actually compose it, which is the claim the brief cares about.
     */
    public function test_the_agent_block_and_the_rubric_are_identical_on_the_analysis_and_the_draft_surfaces(): void
    {
        $tenant = $this->createTenant();
        $this->switches($tenant);
        $engineer = $this->engineerFor($tenant);
        $ticket = $this->ticketFor($tenant, ['created_by' => $engineer->id]);

        $department = Department::find($ticket->department_id);
        $agent = AiAgent::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Software Triage Agent',
            'role_type' => 'triage',
            'system_prompt' => 'SENTINEL-SHARED-AGENT weigh printer faults by site impact',
            'is_active' => true,
        ]);
        $department->assignAiAgent($agent, 'primary', 1, true, true);

        $expectedBlock = implode("\n\n", AnalyzeTicketService::agentInstructionLines([
            AgentRecommendation::fromModel($agent),
        ]));
        $expectedRubric = AnalyzeTicketService::priorityRubric(AnalyzeTicketService::PRIORITIES);

        // Surface 1: the queued intake analysis.
        $this->runJob($tenant, (int) $ticket->getKey(), (int) $engineer->getKey());
        $analysisPrompt = $this->lastChatPrompt();

        // Surface 2: the create-form draft, called directly.
        session(['current_tenant_id' => $tenant->id]);
        app(DraftSuggestionService::class)->suggest(
            $tenant,
            $engineer,
            'printer dead',
            'printer stopped working after the windows update',
            null,
            (int) $department->getKey(),
            DraftSuggestionService::CONTEXT_CREATE,
            true,
        );
        $draftPrompt = $this->lastChatPrompt();
        session()->forget('current_tenant_id');

        $this->assertNotSame($analysisPrompt, $draftPrompt, 'The two surfaces should still differ overall.');

        foreach ([$analysisPrompt, $draftPrompt] as $prompt) {
            $this->assertStringContainsString($expectedBlock, $prompt);
            $this->assertStringContainsString($expectedRubric, $prompt);
        }
    }

    /**
     * Every message of the MOST RECENT chat call, joined and decoded.
     *
     * Both messages, deliberately: the agent block rides the system prompt on both
     * surfaces, but the analysis path carries its rubric in the USER prompt beside the
     * category and engineer options. "What the model received" is the whole request.
     */
    private function lastChatPrompt(): string
    {
        $prompt = '';

        foreach (Http::recorded() as [$request]) {
            if (! str_contains($request->url(), 'chat/completions')) {
                continue;
            }

            $prompt = implode("\n\n", array_map(
                fn (array $message) => (string) ($message['content'] ?? ''),
                json_decode($request->body(), true)['messages'] ?? [],
            ));
        }

        return $prompt;
    }
}
