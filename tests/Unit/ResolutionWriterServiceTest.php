<?php

namespace Tests\Unit;

use App\Enums\PlanFeature;
use App\Models\AiAgent;
use App\Models\AiUsageLog;
use App\Models\Client;
use App\Models\Department;
use App\Models\License;
use App\Models\Plan;
use App\Models\Skill;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AI\AnalyzeTicketService;
use App\Services\AI\ResolutionWriterService;
use App\Services\AiUsageTrackerService;
use App\Support\Ai\AgentRecommendation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * A2-1 at the service boundary — the assertions a request test CANNOT make.
 *
 * **Follow-up 28 governs this file.** An end-to-end test on a session-bearing surface
 * cannot prove an explicit tenant predicate, because the model's own `TenantScope` masks
 * it: the decoy row is dropped whether or not the query states `tenant_id`. The two
 * predicates this package adds as a new caller are therefore pinned HERE, by calling the
 * service directly against hostile, raw-inserted decoy rows. That cost a package's time
 * to learn once already.
 */
class ResolutionWriterServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $other;

    private User $user;

    private Ticket $ticket;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.openai.api_key', 'sk-test-key-12345');
        Config::set('services.openai.model', 'gpt-4o-mini');

        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => fn () => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [['message' => ['role' => 'assistant', 'content' => json_encode([
                    'client_resolution' => 'ZZCLIENTDRAFTZZ the cable was replaced.',
                    'internal_note' => "Cause: cable.\nResolution: replaced.\nVerification: tested.\nFollow-up: none",
                ])]]],
                'usage' => ['prompt_tokens' => 900, 'completion_tokens' => 200],
            ]),
        ]);

        $this->tenant = $this->makeTenant();
        $this->other = $this->makeTenant();

        $this->user = User::factory()->create();
        $this->tenant->addUser($this->user, 'member');

        $this->department = Department::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'SOFTWARE']);
        $this->ticket = Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'client_id' => Client::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'priority' => 'low',
            'status' => 'in_progress',
        ]);
    }

    private function makeTenant(): Tenant
    {
        $plan = Plan::query()->where('slug', 'enterprise')->first()
            ?? Plan::factory()->create(['slug' => 'enterprise', 'features' => PlanFeature::forPlan('enterprise')]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);

        SlaPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'priority' => null,
            'client_tier' => null,
            'is_active' => true,
        ]);

        return $tenant;
    }

    private function service(): ResolutionWriterService
    {
        return app(ResolutionWriterService::class);
    }

    private const NOTES = 'Swapped the patch cable at the wall port, reinstalled the printer driver, printed a test page.';

    /** The recorded chat-completions request body, as a string. */
    private function chatBody(): string
    {
        foreach (Http::recorded() as [$request]) {
            if (str_contains($request->url(), 'chat/completions')) {
                return $request->body();
            }
        }

        return '';
    }

    // ---------------------------------------------------------------------------------
    // The two direct predicate pins (follow-up 28).
    // ---------------------------------------------------------------------------------

    public function test_a_foreign_tenant_comment_row_on_this_ticket_is_never_read_into_the_prompt(): void
    {
        // Hostile: THIS ticket's id carrying ANOTHER workspace's tenant_id. The
        // application cannot create such a row, which is exactly the state the explicit
        // predicate in sanitisedTicketText() exists to survive — and the state a
        // session-less caller would otherwise read, since TenantScope applies no
        // predicate at all without an authenticated user.
        DB::table('ticket_comments')->insert([
            'tenant_id' => $this->other->id,
            'ticket_id' => $this->ticket->id,
            'user_id' => null,
            'content' => 'ZZFOREIGNCOMMENTZZ another workspace wrote this',
            'type' => 'public',
            'is_public' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Asserted on the SERVICE's own return value, not through a request: a request
        // would pass with the predicate deleted.
        $text = app(AnalyzeTicketService::class)->sanitisedTicketText($this->ticket);
        $this->assertStringNotContainsString(
            'ZZFOREIGNCOMMENTZZ',
            $text,
            'A comment row carrying another workspace\'s tenant_id must never be read into the prompt.',
        );

        $this->service()->write($this->ticket, $this->user, self::NOTES);
        $this->assertStringNotContainsString('ZZFOREIGNCOMMENTZZ', $this->chatBody());
    }

    public function test_a_foreign_tenant_task_row_on_this_ticket_is_never_read_into_the_prompt(): void
    {
        DB::table('ticket_tasks')->insert([
            'tenant_id' => $this->other->id,
            'ticket_id' => $this->ticket->id,
            'task_number' => 'T-FOREIGN-1',
            'description' => 'ZZFOREIGNTASKZZ another workspace recorded this as done',
            'status' => 'completed',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ticket_tasks')->insert([
            'tenant_id' => $this->tenant->id,
            'ticket_id' => $this->ticket->id,
            'task_number' => 'T-OWN-1',
            'description' => 'ZZOWNTASKZZ replaced the patch cable',
            'status' => 'completed',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->service()->write($this->ticket, $this->user, self::NOTES);

        $body = $this->chatBody();
        $this->assertStringNotContainsString(
            'ZZFOREIGNTASKZZ',
            $body,
            'A task row carrying another workspace\'s tenant_id must never be read into the prompt.',
        );
        // The decoy is only a proof if the same read genuinely returns this tenant's row.
        $this->assertStringContainsString('ZZOWNTASKZZ', $body);
    }

    public function test_an_internal_comment_of_this_tenant_is_never_read_from_the_database(): void
    {
        DB::table('ticket_comments')->insert([
            'tenant_id' => $this->tenant->id,
            'ticket_id' => $this->ticket->id,
            'user_id' => $this->user->id,
            'content' => 'ZZPRIVATENOTEZZ the customer is threatening to leave',
            'type' => 'internal',
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->service()->write($this->ticket, $this->user, self::NOTES);

        // The privacy boundary, re-asserted through this new caller: the server never
        // reads a non-public comment. Pulling notes in is the engineer's own act, done
        // client-side, on material already on their screen.
        $this->assertStringNotContainsString('ZZPRIVATENOTEZZ', $this->chatBody());
    }

    // ---------------------------------------------------------------------------------
    // Global runtime state, and the DTO boundary.
    // ---------------------------------------------------------------------------------

    public function test_usage_context_is_restored_after_an_exception(): void
    {
        $tracker = app(AiUsageTrackerService::class);

        try {
            $tracker->withContext(['tenant_id' => $this->tenant->id, 'feature' => 'copilot'], function () {
                throw new RuntimeException('boom');
            });
            $this->fail('The exception should have propagated.');
        } catch (RuntimeException) {
            // expected
        }

        // withContext() restores in a finally — the TenantMailService::usingTenant()
        // discipline. A worker handling several tenants must not inherit the last one's
        // attribution.
        $this->service()->write($this->ticket, $this->user, self::NOTES);

        $log = AiUsageLog::query()->latest('id')->firstOrFail();
        $this->assertSame($this->tenant->id, $log->tenant_id);
        $this->assertSame($this->user->id, $log->user_id);
        $this->assertSame('copilot', $log->feature);
    }

    public function test_the_shaping_agents_prefer_documentation_and_validator_roles_and_fall_back_when_absent(): void
    {
        $triage = new AgentRecommendation(1, 'Triage Bot', 'triage', 'p', 0.0, []);
        $docs = new AgentRecommendation(2, 'Record Keeper', 'documentation', 'p', 0.0, []);
        $validator = new AgentRecommendation(3, 'Checker', 'validator', 'p', 0.0, []);

        $service = $this->service();

        $this->assertSame(
            ['Record Keeper', 'Checker'],
            array_map(fn (AgentRecommendation $a) => $a->name, $service->shapingAgents([$triage, $docs, $validator])),
        );

        // No documentation or validator agent ⇒ the list is returned UNCHANGED: a
        // department with only domain agents should still have its domain shape the note.
        $this->assertSame(
            ['Triage Bot'],
            array_map(fn (AgentRecommendation $a) => $a->name, $service->shapingAgents([$triage])),
        );
        $this->assertSame([], $service->shapingAgents([]));
    }

    public function test_no_ai_agent_model_leaves_the_resolution_service(): void
    {
        $agent = AiAgent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Record Keeper',
            'role_type' => 'documentation',
            'system_prompt' => 'Name the firmware version.',
            'is_active' => true,
        ]);
        $this->department->assignAiAgent($agent, 'support', 0, true, true);

        $draft = $this->service()->write($this->ticket, $this->user, self::NOTES);

        $this->assertNotEmpty($draft->agentRecommendations);
        foreach ($draft->agentRecommendations as $recommendation) {
            // AiAgent has LogsActivity and carries P5's two pseudo-attributes, so handing
            // one to anything that saves throws and writes a spurious diff.
            $this->assertInstanceOf(AgentRecommendation::class, $recommendation);
        }
        $this->assertStringNotContainsString('AiAgent', json_encode($draft->toArray()));
    }

    public function test_a_foreign_department_on_the_ticket_yields_no_agent_blocks(): void
    {
        $foreignDepartment = Department::factory()->create(['tenant_id' => $this->other->id, 'code' => 'FOREIGN']);
        $foreignAgent = AiAgent::factory()->create([
            'tenant_id' => $this->other->id,
            'name' => 'ZZFOREIGNAGENTZZ',
            'role_type' => 'documentation',
            'system_prompt' => 'ZZFOREIGNPROMPTZZ',
            'is_active' => true,
        ]);
        $foreignDepartment->assignAiAgent($foreignAgent, 'support', 0, true, true);

        // Raw, because nothing in the application can point a ticket at another
        // workspace's department.
        DB::table('tickets')->where('id', $this->ticket->id)->update(['department_id' => $foreignDepartment->id]);

        $draft = $this->service()->write($this->ticket->fresh(), $this->user, self::NOTES);

        // Fails closed to generic instructions, never to another workspace's agents.
        $this->assertSame([], $draft->agentRecommendations);
        $body = $this->chatBody();
        $this->assertStringNotContainsString('ZZFOREIGNAGENTZZ', $body);
        $this->assertStringNotContainsString('ZZFOREIGNPROMPTZZ', $body);
        $this->assertTrue($draft->succeeded());
    }

    public function test_a_ticket_with_no_client_gets_the_anonymous_recipient_instruction(): void
    {
        // `tickets.client_id` is NOT NULL in the schema (every write path, including the
        // portal's, firstOrCreates a client), so the design's anonymous branch cannot be
        // reached through a request or a factory. It is still the right defensive shape —
        // and it is testable directly, by handing the service a ticket whose client is
        // absent in memory. Recorded in the build notes as a design/schema mismatch.
        $ticket = $this->ticket;
        $ticket->client_id = null;

        $this->service()->write($ticket, $this->user, self::NOTES);

        $body = $this->chatBody();
        $this->assertStringContainsString('UNIDENTIFIED', $body);
        $this->assertStringContainsString('never invent a recipient', $body);
        $this->assertStringContainsString('no salutation', $body);
    }

    public function test_the_shaping_agents_are_ranked_against_the_engineer_notes(): void
    {
        // No auto-invoke agent, so recommendationsFor() falls through to the lexical
        // ranker — whose haystack must be the NOTES, not the ticket description.
        $skill = Skill::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Structured cabling',
            'slug' => 'structured-cabling',
            'is_active' => true,
        ]);
        $agent = AiAgent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cabling Specialist',
            'role_type' => 'documentation',
            'system_prompt' => 'Record the port number.',
            'is_active' => true,
        ]);
        $agent->skills()->attach($skill->id, ['tenant_id' => $this->tenant->id, 'proficiency' => 'expert', 'is_required' => true]);
        $this->department->assignAiAgent($agent, 'support', 0, true, false);

        $draft = $this->service()->write(
            $this->ticket,
            $this->user,
            'Re-terminated the structured cabling run at the wall port and printed a test page afterwards.',
        );

        $this->assertSame(['structured-cabling'], $draft->agentRecommendations[0]->matchedSkills);
        $this->assertGreaterThan(0.0, $draft->agentRecommendations[0]->score);

        // The contrast that makes it a proof: the TICKET's own text never mentions
        // cabling, so notes that do not either must match nothing. Ranking against the
        // description instead of the notes would still match above and fail here.
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => fn () => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [['message' => ['role' => 'assistant', 'content' => json_encode([
                    'client_resolution' => 'ZZCLIENTDRAFTZZ done.',
                    'internal_note' => 'Cause: x Resolution: y Verification: z Follow-up: none',
                ])]]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
            ]),
        ]);

        $second = $this->service()->write(
            $this->ticket,
            $this->user,
            'Reinstalled the printer driver and printed a test page with the client watching.',
        );

        $this->assertSame([], $second->agentRecommendations[0]->matchedSkills);
    }
}
