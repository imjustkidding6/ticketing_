<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\AppSetting;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AiAssistantService;
use App\Services\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A generated checklist must reach a FIX, not stop at diagnosis.
 *
 * The reported defect: a draft came back "Investigate the system logs for errors / Check
 * the server status and connectivity / Verify user permissions and access rights /
 * Reproduce the issue on a test account" — four investigations and no resolution. The
 * cause was not the model. All three task-generating prompts asked for steps "to
 * investigate and fix this issue" and then gave ONE example, a diagnostic one, so the
 * example quietly overrode the instruction.
 *
 * These tests assert the requirement reaches the OUTBOUND REQUEST BODY on each of the
 * three surfaces, rather than asserting the constant contains its own text — which would
 * pass with the prompt never composed at all.
 */
class TaskChecklistPromptTest extends TestCase
{
    use RefreshDatabase;

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
                    'content' => json_encode([
                        'subject' => 'Printer offline after Windows update',
                        'description' => 'The office printer stopped responding.',
                        'tasks' => ['Reproduce the error on a test account'],
                    ]),
                ]]],
                'usage' => ['prompt_tokens' => 400, 'completion_tokens' => 120],
            ]),
        ]);
    }

    private function createTenant(): Tenant
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

        app(PlanService::class)->clearCache($tenant);

        return $tenant;
    }

    private function actAs(Tenant $tenant): User
    {
        $user = User::factory()->create();
        $tenant->addUser($user, 'member');
        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);
        session(['current_tenant_id' => $tenant->id]);
        AppSetting::set('ai_enabled', true, 'boolean', 'ai');

        return $user;
    }

    /** Every message of the chat call, joined and decoded. */
    private function prompt(): string
    {
        foreach (Http::recorded() as [$request]) {
            if (! str_contains($request->url(), 'chat/completions')) {
                continue;
            }

            return implode("\n\n", array_map(
                fn (array $message) => (string) ($message['content'] ?? ''),
                json_decode($request->body(), true)['messages'] ?? [],
            ));
        }

        return '';
    }

    public function test_the_draft_request_asks_for_a_checklist_that_reaches_a_fix_and_a_verification(): void
    {
        $tenant = $this->createTenant();
        $this->actAs($tenant);

        app(AiAssistantService::class)->structureTicketDraft($tenant, 'system wont open', 'it will not open this morning');

        $prompt = $this->prompt();

        $this->assertStringContainsString('THROUGH TO RESOLUTION', $prompt);
        $this->assertStringContainsString('at least one step must be the corrective action itself', $prompt);
        $this->assertStringContainsString('one step must verify the fix held', $prompt);

        // The fix step is phrased so it cannot fabricate the diagnosis — the half that
        // keeps this from fighting "Do NOT invent facts, names, numbers, or causes".
        $this->assertStringContainsString('ONCE THE CAUSE IS KNOWN', $prompt);
        $this->assertStringContainsString('Do NOT invent facts, names, numbers, or causes', $prompt);

        // One diagnostic, one corrective, one verifying. Before this, only the first
        // existed, and the model answered the examples rather than the instruction.
        $this->assertStringContainsString('Reproduce the error on a test account', $prompt);
        $this->assertStringContainsString('Apply the corrective change once the cause is confirmed', $prompt);
        $this->assertStringContainsString('Confirm with the reporter that the issue no longer occurs', $prompt);
    }

    public function test_the_checklist_restructure_request_carries_the_same_resolution_rule(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => fn () => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [['message' => ['role' => 'assistant', 'content' => json_encode(['tasks' => ['Reproduce the error on a test account']])]]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
            ]),
        ]);

        app(AiAssistantService::class)->polishTaskList($ticket, ['check the logs'], []);

        $prompt = $this->prompt();

        // ONE rule, applied in each place: the three prompts had the same diagnosis-only
        // bias and must not be allowed to drift apart again.
        $this->assertStringContainsString(AiAssistantService::TASK_RESOLUTION_RULE, $prompt);
        $this->assertStringContainsString(AiAssistantService::TASK_FIX_PHRASING, $prompt);
        $this->assertStringContainsString(AiAssistantService::TASK_EXAMPLES, $prompt);
    }

    public function test_the_single_task_request_keeps_a_corrective_note_corrective(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => fn () => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'Reinstall the printer driver']]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
            ]),
        ]);

        app(AiAssistantService::class)->polishTask($ticket, 'put the driver back on');

        $prompt = $this->prompt();

        $this->assertStringContainsString('do not soften a fix into one more check', strtolower($prompt));
        $this->assertStringContainsString(AiAssistantService::TASK_FIX_PHRASING, $prompt);
        $this->assertStringContainsString(AiAssistantService::TASK_EXAMPLES, $prompt);
    }
}
