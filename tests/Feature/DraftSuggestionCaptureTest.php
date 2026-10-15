<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\AiSuggestionVerdict;
use App\Models\AiTicketAnalysis;
use App\Models\AppSetting;
use App\Models\Client;
use App\Models\Department;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\Ticket;
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
 * P6c-1: the Accept / Edit / Reject capture for a polished draft.
 *
 * The analysis row is written AFTER the ticket is created, with `trigger = 'draft'`, so
 * both `ticket_id` columns stay NOT NULL and the whole P6a pipeline applies unchanged.
 *
 * The verdicts here are DERIVED FROM WHAT LANDED on the ticket, not from a button — so
 * this population counts applications that landed, while `manual`/`auto` count
 * applications attempted. Those are different metrics and the pilot must be told so.
 */
class DraftSuggestionCaptureTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $modelReply = [
        'subject' => 'Printer offline after Windows update',
        'description' => "Issue\nThe office printer stopped responding after the update.",
        'tasks' => ['Reinstall the printer driver'],
        'priority' => 'high',
        'priority_reason' => 'Nobody in the office can print.',
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
                'choices' => [['message' => ['role' => 'assistant', 'content' => json_encode($this->modelReply)]]],
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

        $roles = app(TenantRoleService::class);
        $roles->setupDefaultRoles($tenant);
        $roles->syncRole($user, 'admin', $tenant);

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);
        session(['current_tenant_id' => $tenant->id]);

        AppSetting::set('ai_enabled', true, 'boolean', 'ai');
        AppSetting::set('ai_agent_copilot_enabled', true, 'boolean', 'ai');

        return $user;
    }

    /** Polish a draft and return the issued token. */
    private function polishFor(Client $client): string
    {
        $token = $this->postJson($this->tenantUrl('tickets-ai/structure'), [
            'subject' => 'printer dead',
            'description' => 'printer stopped working after the windows update',
            'client_id' => $client->id,
            'context' => 'create',
        ])->assertOk()->json('draft_token');

        $this->assertIsString($token);

        return $token;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function submit(Client $client, array $overrides = [])
    {
        return $this->post($this->tenantUrl('tickets'), array_merge([
            'client_id' => $client->id,
            'subject' => 'Printer offline after Windows update',
            'description' => "Issue\nThe office printer stopped responding after the update.",
            'priority' => 'high',
            // The universal half of the declared detail set is required on the internal
            // create form in every department.
            'impact_scope' => 'one_person',
            'workaround' => 'none',
        ], $overrides));
    }

    public function test_submitting_a_polished_draft_writes_one_draft_analysis_against_the_new_ticket(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'premium']);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);

        $token = $this->polishFor($client);

        $this->submit($client, ['ai_draft_token' => $token, 'department_id' => $department->id])
            ->assertRedirect();

        $ticket = Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();

        $analysis = AiTicketAnalysis::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();
        $this->assertSame(AiTicketAnalysis::TRIGGER_DRAFT, $analysis->trigger);
        $this->assertSame($ticket->id, $analysis->ticket_id);
        $this->assertSame($user->id, $analysis->requested_by);
        $this->assertSame($department->id, $analysis->department_id);
        $this->assertNull($analysis->auto_for_ticket_id);
        $this->assertSame(AiTicketAnalysis::STATUS_SUCCEEDED, $analysis->status);
        // Cost joins to the ticket through the usage log ids, not through timestamps.
        $this->assertNotEmpty($analysis->usage_log_ids);
        $this->assertSame('gpt-4o-mini', $analysis->model);
        $this->assertGreaterThan(0, $analysis->prompt_tokens);
    }

    public function test_an_accepted_priority_records_accepted_with_minutes_saved_from_config(): void
    {
        $tenant = $this->createTenant();
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'premium']);
        $this->actAs($tenant);

        $token = $this->polishFor($client);
        $this->submit($client, ['ai_draft_token' => $token])->assertRedirect();

        $verdict = AiSuggestionVerdict::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('suggestion_type', AiSuggestionVerdict::TYPE_PRIORITY)
            ->sole();

        $this->assertSame(AiSuggestionVerdict::VERDICT_ACCEPTED, $verdict->verdict);
        $this->assertSame((int) config('ai.minutes_saved.priority'), $verdict->minutes_saved);
        $this->assertSame(0.0, $verdict->edit_ratio);
    }

    public function test_a_changed_priority_records_edited_with_an_edit_ratio(): void
    {
        $tenant = $this->createTenant();
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'premium']);
        $this->actAs($tenant);

        $token = $this->polishFor($client);

        // The engineer thought it over and lowered it before submitting.
        $this->submit($client, ['ai_draft_token' => $token, 'priority' => 'low'])->assertRedirect();

        $verdict = AiSuggestionVerdict::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('suggestion_type', AiSuggestionVerdict::TYPE_PRIORITY)
            ->sole();

        $this->assertSame(AiSuggestionVerdict::VERDICT_EDITED, $verdict->verdict);
        $this->assertGreaterThan(0, $verdict->edit_ratio);
        // The verdict derives from the LANDED ticket, not from anything the browser said.
        $this->assertSame('low', Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole()->priority);
    }

    public function test_an_edited_subject_and_description_are_captured_from_what_landed(): void
    {
        $tenant = $this->createTenant();
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'premium']);
        $this->actAs($tenant);

        $token = $this->polishFor($client);

        $this->submit($client, [
            'ai_draft_token' => $token,
            'subject' => 'Printer offline — escalated by the site manager',
        ])->assertRedirect();

        $byType = AiSuggestionVerdict::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('suggestion_type');

        $this->assertSame(AiSuggestionVerdict::VERDICT_EDITED, $byType[AiSuggestionVerdict::TYPE_SUBJECT]->verdict);
        $this->assertSame(AiSuggestionVerdict::VERDICT_ACCEPTED, $byType[AiSuggestionVerdict::TYPE_DESCRIPTION]->verdict);
    }

    public function test_an_abandoned_draft_writes_nothing(): void
    {
        // The measurement cost of writing the row after creation, pinned so it is a known
        // shape rather than a surprise when the pilot reads issue volume.
        $tenant = $this->createTenant();
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'premium']);
        $this->actAs($tenant);

        $this->polishFor($client);

        $this->assertSame(0, AiTicketAnalysis::withoutGlobalScopes()->count());
        $this->assertSame(0, AiSuggestionVerdict::withoutGlobalScopes()->count());
    }

    public function test_a_draft_with_no_token_submits_exactly_as_it_does_today(): void
    {
        $tenant = $this->createTenant();
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'premium']);
        $this->actAs($tenant);

        $this->submit($client)->assertRedirect();

        $this->assertSame(1, Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, AiTicketAnalysis::withoutGlobalScopes()->count());
    }

    public function test_a_draft_token_cannot_be_redeemed_by_another_user_or_workspace(): void
    {
        $tenant = $this->createTenant();
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'premium']);
        $issuedTo = $this->actAs($tenant);

        $token = $this->polishFor($client);

        // Another member of the SAME workspace: the token is keyed on the user too.
        $colleague = User::factory()->create();
        $tenant->addUser($colleague, 'member');

        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'priority' => 'high']);

        $drafts = app(DraftSuggestionService::class);
        $this->assertNull($drafts->recordOutcome($ticket, $colleague, $token));

        // Another workspace entirely.
        $otherTenant = $this->createTenant();
        $otherUser = User::factory()->create();
        $otherTenant->addUser($otherUser, 'member');
        $foreignTicket = Ticket::factory()->create(['tenant_id' => $otherTenant->id, 'priority' => 'high']);

        $this->assertNull($drafts->recordOutcome($foreignTicket, $otherUser, $token));
        $this->assertNull($drafts->recordOutcome($foreignTicket, $issuedTo, $token));

        $this->assertSame(0, AiTicketAnalysis::withoutGlobalScopes()->count());

        // ...and the engineer it was issued to can still redeem it exactly once.
        $this->assertNotNull($drafts->recordOutcome($ticket, $issuedTo, $token));
        $this->assertNull($drafts->recordOutcome($ticket, $issuedTo, $token));
    }

    public function test_a_capture_failure_never_fails_the_ticket_creation(): void
    {
        $tenant = $this->createTenant();
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'premium']);
        $this->actAs($tenant);

        $token = $this->polishFor($client);

        $this->mock(DraftSuggestionService::class, function ($mock) {
            $mock->shouldReceive('recordOutcome')->once()->andThrow(new \RuntimeException('capture exploded'));
        });

        $this->submit($client, ['ai_draft_token' => $token])
            ->assertRedirect()
            ->assertSessionHas('success');

        // The engineer's work is the product; the pilot's metric is not.
        $this->assertSame(1, Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_a_draft_capture_is_its_own_population(): void
    {
        $tenant = $this->createTenant();
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'premium']);
        $user = $this->actAs($tenant);

        $token = $this->polishFor($client);
        $this->submit($client, ['ai_draft_token' => $token])->assertRedirect();

        $ticket = Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();

        foreach ([AiTicketAnalysis::TRIGGER_MANUAL, AiTicketAnalysis::TRIGGER_AUTO] as $trigger) {
            AiTicketAnalysis::create([
                'tenant_id' => $tenant->id,
                'ticket_id' => $ticket->id,
                'requested_by' => $user->id,
                'trigger' => $trigger,
                'auto_for_ticket_id' => $trigger === AiTicketAnalysis::TRIGGER_AUTO ? $ticket->id : null,
                'status' => AiTicketAnalysis::STATUS_SUCCEEDED,
                'suggestions' => ['priority' => 'high'],
            ]);
        }

        $populations = DB::table('ai_ticket_analyses')
            ->where('tenant_id', $tenant->id)
            // `trigger` is a MySQL reserved word.
            ->selectRaw('`trigger` as t, COUNT(*) as c')
            ->groupBy('trigger')
            ->pluck('c', 't')
            ->all();

        $this->assertSame(['auto' => 1, 'draft' => 1, 'manual' => 1], collect($populations)->map(fn ($c) => (int) $c)->sortKeys()->all());
        $this->assertSame(1, AiTicketAnalysis::withoutGlobalScopes()->draft()->count());
    }
}
