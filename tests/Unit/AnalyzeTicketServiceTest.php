<?php

namespace Tests\Unit;

use App\Models\AiAgent;
use App\Models\AiUsageLog;
use App\Models\Client;
use App\Models\Department;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\AI\AnalyzeTicketService;
use App\Services\AiUsageTrackerService;
use App\Support\Ai\AgentRecommendation;
use App\Support\Ai\TicketAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * P6a-1 unit-level boundaries: the DTO boundary and the usage-context restore.
 *
 * Both exist because of a specific past failure. P5 writes `recommendation_score` and
 * `matched_skills` onto AiAgent models as pseudo-attributes — no such columns — and
 * AiAgent has LogsActivity, so an AiAgent escaping the service is a write that throws.
 * And withContext() is global runtime state on a singleton, which is the
 * TenantMailService::configure() failure mode in a second place.
 */
class AnalyzeTicketServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.openai.api_key', 'sk-test-key-12345');
        Config::set('services.openai.model', 'gpt-4o-mini');

        Http::preventStrayRequests();

        Http::fake([
            'https://api.openai.com/v1/embeddings' => Http::response([
                'data' => [['embedding' => array_fill(0, 8, 0.1)]],
            ]),
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [['message' => ['content' => json_encode([
                    'summary' => 'A short summary.',
                    'category_ref' => 'K1',
                    'priority' => 'medium',
                ])]]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20],
            ]),
        ]);
    }

    public function test_recommendations_are_returned_as_readonly_dtos_not_models(): void
    {
        $tenant = Tenant::factory()->withLicense()->create();
        $user = User::factory()->create();
        $tenant->addUser($user, 'member');

        $department = Department::factory()->create(['tenant_id' => $tenant->id, 'code' => 'SOFTWARE']);
        TicketCategory::factory()->create(['tenant_id' => $tenant->id, 'department_id' => $department->id, 'name' => 'Printer Faults']);

        $agent = AiAgent::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Printer Triage Specialist',
            'role_type' => 'triage',
            'is_active' => true,
        ]);
        $department->assignAiAgent($agent, 'support', 0, true, true);

        $ticket = Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
            'client_id' => Client::factory()->create(['tenant_id' => $tenant->id])->id,
        ]);

        $analysis = app(AnalyzeTicketService::class)->analyze($ticket, $user);

        $this->assertNotEmpty($analysis->agentRecommendations);

        foreach ($analysis->agentRecommendations as $recommendation) {
            $this->assertInstanceOf(AgentRecommendation::class, $recommendation);
            $this->assertNotInstanceOf(AiAgent::class, $recommendation);
        }

        // Readonly, so a consumer cannot mutate a recommendation into disagreeing with
        // the row persisted beside it.
        $this->assertTrue((new \ReflectionClass(AgentRecommendation::class))->isReadOnly());
        $this->assertTrue((new \ReflectionClass(TicketAnalysis::class))->isReadOnly());
    }

    public function test_usage_context_is_restored_after_an_exception(): void
    {
        $tenant = Tenant::factory()->withLicense()->create();
        $user = User::factory()->create();

        /** @var AiUsageTrackerService $tracker */
        $tracker = app(AiUsageTrackerService::class);

        try {
            $tracker->withContext(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'feature' => 'copilot'], function () {
                throw new RuntimeException('the provider blew up mid-call');
            });
            $this->fail('The exception should have propagated.');
        } catch (RuntimeException) {
            // expected
        }

        // Without the finally, this log would be billed to the wrong workspace — the
        // TenantMailService::configure() failure, one tenant's cost on another's row.
        $log = $tracker->log(['model' => 'gpt-4o-mini', 'prompt_tokens' => 1]);

        $this->assertNull($log->tenant_id);
        $this->assertNull($log->user_id);
        $this->assertSame('chat', $log->feature);
        $this->assertSame(1, AiUsageLog::query()->count());
    }

    public function test_the_tracker_is_a_singleton_so_the_context_reaches_the_writer(): void
    {
        // OpenAiService::post() resolves the tracker out of the container itself; a fresh
        // instance per resolution would see an empty context and log null attribution.
        $this->assertSame(app(AiUsageTrackerService::class), app(AiUsageTrackerService::class));
    }

    public function test_an_explicit_key_beats_the_context_but_a_generic_chat_feature_does_not(): void
    {
        $tenant = Tenant::factory()->withLicense()->create();
        $ambient = User::factory()->create();
        $explicitUser = User::factory()->create();

        /** @var AiUsageTrackerService $tracker */
        $tracker = app(AiUsageTrackerService::class);

        $tracker->withContext(['tenant_id' => $tenant->id, 'user_id' => $ambient->id, 'feature' => 'copilot'], function () use ($tracker, $tenant, $explicitUser) {
            // `embed` is a real endpoint-derived answer and must survive the context.
            $embed = $tracker->log(['model' => 'text-embedding-3-small', 'feature' => 'embed']);
            $this->assertSame('embed', $embed->feature);

            // `chat` is the fallback for "a chat completion of some kind", so the context's
            // product surface replaces it — otherwise every surface built on
            // chat/completions is indistinguishable in the cost reports.
            $chat = $tracker->log(['model' => 'gpt-4o-mini', 'feature' => 'chat']);
            $this->assertSame('copilot', $chat->feature);

            // An explicit user_id still wins over the ambient one.
            $explicit = $tracker->log(['model' => 'gpt-4o-mini', 'user_id' => $explicitUser->id]);
            $this->assertSame($explicitUser->id, $explicit->user_id);
            $this->assertSame($tenant->id, $explicit->tenant_id);
        });

        $this->assertCount(3, $tracker->lastLoggedIds());
    }
}
