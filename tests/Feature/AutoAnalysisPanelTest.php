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
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\PlanService;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The two surfaces of an automatic analysis: the public one, which must show NONE of it,
 * and the agent's panel, which must show all of it beside the customer's own words.
 *
 * Every suggestion field in the canned reply carries a DISTINCTIVE SENTINEL. Realistic
 * polished text shares words with the original, so an `assertDontSee` on it would pass
 * vacuously — the sentinels are what make the public-surface proof real.
 */
class AutoAnalysisPanelTest extends TestCase
{
    use RefreshDatabase;

    private const S_SUBJECT = 'SENTINEL-POLISHED-SUBJECT';

    private const S_DESC = 'SENTINEL-POLISHED-DESC';

    private const S_SUMMARY = 'SENTINEL-SUMMARY';

    private const S_CHECK = 'SENTINEL-CHECK';

    private const S_REASON = 'SENTINEL-ENGINEER-REASON';

    private const CATEGORY = 'SENTINEL-CATEGORY-NAME';

    /** @var array<string, mixed>|string */
    private array|string $modelReply = [];

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.openai.api_key', 'sk-test-key-12345');
        Config::set('services.openai.model', 'gpt-4o-mini');

        $this->modelReply = [
            'summary' => self::S_SUMMARY,
            'polished_subject' => self::S_SUBJECT,
            'polished_description' => self::S_DESC,
            'category_ref' => 'K1',
            'priority' => 'critical',
            'checks' => [self::S_CHECK],
            'engineer_ref' => 'C1',
            'engineer_reason' => self::S_REASON,
        ];

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

    private function switches(Tenant $tenant): void
    {
        session(['current_tenant_id' => $tenant->id]);
        AppSetting::set('ai_enabled', true, 'boolean', 'ai');
        AppSetting::set('ai_agents_enabled', true, 'boolean', 'ai');
        session()->forget('current_tenant_id');
    }

    /**
     * A department with one category and one available member, so every suggestion in the
     * canned reply resolves and every sentinel is really stored.
     *
     * @return array{0: Department, 1: TicketCategory, 2: User}
     */
    private function departmentFor(Tenant $tenant): array
    {
        $department = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Technical Software']);
        $category = TicketCategory::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
            'name' => self::CATEGORY,
        ]);

        $engineer = User::factory()->create(['name' => 'SENTINEL-ENGINEER-NAME', 'is_available' => true]);
        $tenant->addUser($engineer, 'member');
        $engineer->departments()->attach($department->id);

        return [$department, $category, $engineer];
    }

    /**
     * Submit a public ticket and run the analysis synchronously, exactly as the worker
     * would. Returns the ticket.
     */
    private function submitAndAnalyse(Tenant $tenant, Department $department, TicketCategory $category): Ticket
    {
        Config::set('queue.default', 'sync');

        $this->post("/{$tenant->slug}/submit-ticket", [
            'name' => 'Portal User',
            'email' => 'portal@example.com',
            'subject' => 'printer wont print',
            'description' => 'it stopped working this morning',
            // Deliberately NO category: the submitter picked none, so the only place
            // SENTINEL-CATEGORY-NAME could appear on the public page is the AI's
            // suggestion. A submitted category would make the assertion pass vacuously.
            // Sent as an explicit null rather than omitted — publicSubmitStore() reads
            // $validated['category_id'] unguarded and 500s on an absent key (pre-existing).
            'category_id' => null,
            'department_id' => $department->id,
        ])->assertRedirect();

        return Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();
    }

    private function actAs(Tenant $tenant, string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $tenant->addUser($user, 'member');

        $roles = app(TenantRoleService::class);
        $roles->setupDefaultRoles($tenant);
        $roles->syncRole($user, $role, $tenant);

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);
        session(['current_tenant_id' => $tenant->id]);

        return $user;
    }

    // ───────────────────────── The public surface ─────────────────────────

    public function test_no_analysis_text_reaches_the_public_tracking_page(): void
    {
        $tenant = $this->createTenant();
        $this->switches($tenant);
        [$department, $category] = $this->departmentFor($tenant);

        $ticket = $this->submitAndAnalyse($tenant, $department, $category);

        $analysis = AiTicketAnalysis::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();
        $this->assertSame(AiTicketAnalysis::STATUS_SUCCEEDED, $analysis->status);
        $this->assertSame(self::S_SUBJECT, $analysis->suggestions['polished_subject']);

        $response = $this->get("/{$tenant->slug}/track-ticket/{$ticket->tracking_token}")->assertOk();

        foreach ([self::S_SUBJECT, self::S_DESC, self::S_SUMMARY, self::S_CHECK, self::S_REASON] as $sentinel) {
            $response->assertDontSee($sentinel, false);
        }

        // The suggested category, priority and engineer are suggestions too, and the client
        // sees only what they themselves submitted.
        $response->assertDontSee(self::CATEGORY, false);
        $response->assertDontSee('SENTINEL-ENGINEER-NAME', false);
        $response->assertDontSee('critical', false);
        $response->assertDontSee('analysis', false);

        // And the customer's own words are still there, byte-exact.
        $response->assertSee('printer wont print', false);
    }

    public function test_no_analysis_text_reaches_the_public_track_form_or_the_landing_page(): void
    {
        $tenant = $this->createTenant();
        $this->switches($tenant);
        [$department, $category] = $this->departmentFor($tenant);
        $this->submitAndAnalyse($tenant, $department, $category);

        foreach (["/{$tenant->slug}/track-ticket", "/{$tenant->slug}/"] as $url) {
            $response = $this->get($url)->assertOk();

            foreach ([self::S_SUBJECT, self::S_DESC, self::S_SUMMARY, self::S_CHECK, self::S_REASON] as $sentinel) {
                $response->assertDontSee($sentinel, false);
            }
        }
    }

    public function test_the_submit_response_is_byte_identical_whether_or_not_the_job_is_dispatched(): void
    {
        $tenant = $this->createTenant();
        $this->switches($tenant);
        [$department, $category] = $this->departmentFor($tenant);

        Queue::fake();
        $faked = $this->post("/{$tenant->slug}/submit-ticket", [
            'name' => 'A', 'email' => 'a@example.com',
            'subject' => 'one', 'description' => 'first submission',
            'department_id' => $department->id, 'category_id' => $category->id,
        ]);

        $real = $this->post("/{$tenant->slug}/submit-ticket", [
            'name' => 'A', 'email' => 'a@example.com',
            'subject' => 'two', 'description' => 'second submission',
            'department_id' => $department->id, 'category_id' => $category->id,
        ]);

        // Same route, same flash shape: the analysis is invisible to the submitter either
        // way. (The token differs by construction, so the route name is what is compared.)
        $this->assertSame(302, $faked->getStatusCode());
        $this->assertSame(302, $real->getStatusCode());
        $this->assertStringContainsString('/track-ticket/', (string) $faked->headers->get('Location'));
        $this->assertStringContainsString('/track-ticket/', (string) $real->headers->get('Location'));
        $this->assertStringStartsWith(
            'Your ticket has been submitted successfully!',
            (string) session('success'),
        );
    }

    /**
     * Constraint 3, proven: the submission cannot fail because of AI.
     */
    public function test_the_public_submit_succeeds_when_openai_is_down(): void
    {
        $tenant = $this->createTenant();
        $this->switches($tenant);
        [$department, $category] = $this->departmentFor($tenant);

        // Http::fake() MERGES stubs and the first match wins, so a second fake() here
        // would be silently ignored. The breaker is the honest way to model "the provider
        // is unreachable": OpenAiService::post() raises OpenAiException BEFORE any HTTP.
        Cache::put('circuit_breaker_openai_state', 'OPEN', now()->addMinutes(5));
        Config::set('queue.default', 'sync');

        $this->post("/{$tenant->slug}/submit-ticket", [
            'name' => 'Portal User', 'email' => 'portal@example.com',
            'subject' => 'printer wont print', 'description' => 'it stopped working',
            'department_id' => $department->id, 'category_id' => $category->id,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(1, Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(
            0,
            AiTicketAnalysis::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('status', AiTicketAnalysis::STATUS_SUCCEEDED)
                ->count(),
        );
    }

    public function test_the_public_submit_succeeds_when_the_queue_connection_is_broken(): void
    {
        $tenant = $this->createTenant();
        $this->switches($tenant);
        [$department, $category] = $this->departmentFor($tenant);

        // A queue that throws on push. "The submission can never fail because of AI"
        // includes the dispatch itself, which is why it is wrapped in the controller.
        Config::set('queue.default', 'there-is-no-such-connection');

        $this->post("/{$tenant->slug}/submit-ticket", [
            'name' => 'Portal User', 'email' => 'portal@example.com',
            'subject' => 'printer wont print', 'description' => 'it stopped working',
            'department_id' => $department->id, 'category_id' => $category->id,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(1, Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    // ───────────────────────── The agent's panel ─────────────────────────

    public function test_the_panel_hydrates_from_the_stored_auto_analysis_without_pressing_analyze(): void
    {
        $tenant = $this->createTenant();
        $this->switches($tenant);
        [$department, $category] = $this->departmentFor($tenant);
        $ticket = $this->submitAndAnalyse($tenant, $department, $category);

        $this->actAs($tenant);

        $response = $this->get($this->tenantUrl("tickets/{$ticket->id}"))->assertOk();

        // The suggestions are in the page itself, not behind a fetch.
        $response->assertSee(self::S_SUBJECT, false);
        $response->assertSee(self::S_DESC, false);
        $response->assertSee(self::S_SUMMARY, false);
        $response->assertSee('Analyzed automatically on submission', false);
    }

    public function test_the_panel_labels_an_auto_analysis_as_automatic_and_a_manual_one_by_its_requester(): void
    {
        $tenant = $this->createTenant();
        $this->switches($tenant);
        [$department, $category] = $this->departmentFor($tenant);
        $ticket = $this->submitAndAnalyse($tenant, $department, $category);

        $user = $this->actAs($tenant);
        // The name is compared as Blade renders it. With escaping disabled, a Faker name
        // carrying an apostrophe ("Jany O'Kon" -> "O&#039;Kon") fails the assertSee below
        // and makes the assertDontSee above pass vacuously -- the more dangerous half.
        $byUser = 'Analyzed by '.e($user->name);

        $this->get($this->tenantUrl("tickets/{$ticket->id}"))
            ->assertSee('Analyzed automatically on submission', false)
            ->assertDontSee($byUser, false);

        AiTicketAnalysis::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'department_id' => $department->id,
            'requested_by' => $user->id,
            'trigger' => AiTicketAnalysis::TRIGGER_MANUAL,
            'status' => AiTicketAnalysis::STATUS_SUCCEEDED,
            'suggestions' => ['summary' => 'SENTINEL-MANUAL-SUMMARY'],
        ]);

        $this->get($this->tenantUrl("tickets/{$ticket->id}"))
            ->assertSee($byUser, false)
            ->assertDontSee('Analyzed automatically on submission', false);
    }

    public function test_the_panel_shows_the_customers_original_subject_and_description_beside_the_polished_ones(): void
    {
        $tenant = $this->createTenant();
        $this->switches($tenant);
        [$department, $category] = $this->departmentFor($tenant);
        $ticket = $this->submitAndAnalyse($tenant, $department, $category);

        $this->actAs($tenant);

        $response = $this->get($this->tenantUrl("tickets/{$ticket->id}"))->assertOk();

        // Both panes, always together, with the original labelled as the record.
        $response->assertSee('Customer&#039;s words', false);
        $response->assertSee('Stored exactly as submitted. This is the ticket.', false);
        $response->assertSee('AI suggestion — not applied', false);
        $response->assertSee('printer wont print', false);
        $response->assertSee('it stopped working this morning', false);
        $response->assertSee(self::S_SUBJECT, false);

        // And nothing overwrote the record.
        $ticket->refresh();
        $this->assertSame('printer wont print', $ticket->subject);
        $this->assertSame('it stopped working this morning', $ticket->description);
    }

    public function test_a_later_manual_analysis_supersedes_the_auto_one_in_the_panel_and_both_rows_survive(): void
    {
        $tenant = $this->createTenant();
        $this->switches($tenant);
        [$department, $category] = $this->departmentFor($tenant);
        $ticket = $this->submitAndAnalyse($tenant, $department, $category);
        $user = $this->actAs($tenant);

        $manual = AiTicketAnalysis::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'department_id' => $department->id,
            'requested_by' => $user->id,
            'trigger' => AiTicketAnalysis::TRIGGER_MANUAL,
            'status' => AiTicketAnalysis::STATUS_SUCCEEDED,
            'suggestions' => ['summary' => 'SENTINEL-MANUAL-SUMMARY', 'polished_subject' => 'SENTINEL-MANUAL-SUBJECT'],
        ]);

        $auto = AiTicketAnalysis::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('trigger', AiTicketAnalysis::TRIGGER_AUTO)
            ->sole();

        $this->get($this->tenantUrl("tickets/{$ticket->id}"))
            ->assertSee('SENTINEL-MANUAL-SUBJECT', false)
            ->assertDontSee(self::S_SUBJECT, false);

        // Both rows survive, so the two populations stay separable in one join.
        $this->assertSame(2, AiTicketAnalysis::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());

        // A verdict on each is a row in its own population.
        $this->postJson($this->tenantUrl("tickets-ai/analyses/{$auto->id}/verdict"), [
            'suggestion_type' => AiSuggestionVerdict::TYPE_SUBJECT,
            'verdict' => 'accepted',
            'applied_value' => self::S_SUBJECT,
        ])->assertOk();

        $this->postJson($this->tenantUrl("tickets-ai/analyses/{$manual->id}/verdict"), [
            'suggestion_type' => AiSuggestionVerdict::TYPE_SUBJECT,
            'verdict' => 'accepted',
            'applied_value' => 'SENTINEL-MANUAL-SUBJECT',
        ])->assertOk();

        $this->assertSame(1, AiSuggestionVerdict::withoutGlobalScopes()->where('ai_ticket_analysis_id', $auto->id)->count());
        $this->assertSame(1, AiSuggestionVerdict::withoutGlobalScopes()->where('ai_ticket_analysis_id', $manual->id)->count());
    }

    public function test_applying_a_polished_subject_records_an_accepted_verdict_of_type_subject(): void
    {
        $tenant = $this->createTenant();
        $this->switches($tenant);
        [$department, $category] = $this->departmentFor($tenant);
        $ticket = $this->submitAndAnalyse($tenant, $department, $category);
        $this->actAs($tenant);

        $analysis = AiTicketAnalysis::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();

        $this->postJson($this->tenantUrl("tickets-ai/analyses/{$analysis->id}/verdict"), [
            'suggestion_type' => AiSuggestionVerdict::TYPE_SUBJECT,
            'verdict' => 'accepted',
            'applied_value' => self::S_SUBJECT,
        ])->assertOk()->assertJson([
            'verdict' => AiSuggestionVerdict::VERDICT_ACCEPTED,
            'minutes_saved' => 1,
        ]);

        $this->postJson($this->tenantUrl("tickets-ai/analyses/{$analysis->id}/verdict"), [
            'suggestion_type' => AiSuggestionVerdict::TYPE_DESCRIPTION,
            'verdict' => 'accepted',
            'applied_value' => self::S_DESC,
        ])->assertOk()->assertJson([
            'verdict' => AiSuggestionVerdict::VERDICT_ACCEPTED,
            'minutes_saved' => 4,
        ]);
    }

    public function test_an_edited_subject_records_edited_with_an_edit_ratio(): void
    {
        $tenant = $this->createTenant();
        $this->switches($tenant);
        [$department, $category] = $this->departmentFor($tenant);
        $ticket = $this->submitAndAnalyse($tenant, $department, $category);
        $this->actAs($tenant);

        $analysis = AiTicketAnalysis::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();

        // The browser claims `accepted`; the server's hash comparison decides otherwise.
        $response = $this->postJson($this->tenantUrl("tickets-ai/analyses/{$analysis->id}/verdict"), [
            'suggestion_type' => AiSuggestionVerdict::TYPE_SUBJECT,
            'verdict' => 'accepted',
            'applied_value' => 'Printer offline after the October Windows update',
        ])->assertOk();

        $response->assertJson(['verdict' => AiSuggestionVerdict::VERDICT_EDITED]);
        $this->assertNotNull($response->json('edit_ratio'));
    }

    public function test_a_verdict_on_a_polished_value_the_analysis_never_offered_is_refused(): void
    {
        $tenant = $this->createTenant();
        $this->switches($tenant);
        [$department] = $this->departmentFor($tenant);
        $user = $this->actAs($tenant);
        $ticket = Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
            'client_id' => Client::factory()->create(['tenant_id' => $tenant->id])->id,
        ]);

        $analysis = AiTicketAnalysis::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'department_id' => $department->id,
            'requested_by' => $user->id,
            'trigger' => AiTicketAnalysis::TRIGGER_MANUAL,
            'status' => AiTicketAnalysis::STATUS_SUCCEEDED,
            'suggestions' => ['summary' => 'only a summary here'],
        ]);

        $this->postJson($this->tenantUrl("tickets-ai/analyses/{$analysis->id}/verdict"), [
            'suggestion_type' => AiSuggestionVerdict::TYPE_SUBJECT,
            'verdict' => 'accepted',
            'applied_value' => 'something the AI never said',
        ])->assertStatus(422);

        $this->assertSame(0, AiSuggestionVerdict::withoutGlobalScopes()->count());
    }

    public function test_an_agent_outside_the_department_does_not_see_the_panel_at_all(): void
    {
        $tenant = $this->createTenant();
        $this->switches($tenant);
        [$department, $category] = $this->departmentFor($tenant);
        $ticket = $this->submitAndAnalyse($tenant, $department, $category);

        // An agent whose departments do not include this ticket's, and who neither created
        // it nor is assigned to it: department scoping fails closed, so the show page is
        // refused and the hydration cannot become a read path around it.
        $outsider = User::factory()->create();
        $tenant->addUser($outsider, 'member');
        $otherDepartment = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Billing']);
        $outsider->departments()->attach($otherDepartment->id);

        $roles = app(TenantRoleService::class);
        $roles->setupDefaultRoles($tenant);
        $roles->syncRole($outsider, 'agent', $tenant);

        $this->actingAs($outsider)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);
        session(['current_tenant_id' => $tenant->id]);

        $this->get($this->tenantUrl("tickets/{$ticket->id}"))->assertForbidden();
    }

    public function test_a_failed_analysis_hydrates_nothing(): void
    {
        $tenant = $this->createTenant();
        $this->switches($tenant);
        [$department, $category] = $this->departmentFor($tenant);
        $this->modelReply = '{"summary": "half a rep';
        $ticket = $this->submitAndAnalyse($tenant, $department, $category);
        $this->actAs($tenant);

        $this->assertSame(
            AiTicketAnalysis::STATUS_FAILED,
            AiTicketAnalysis::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole()->status,
        );

        $this->get($this->tenantUrl("tickets/{$ticket->id}"))
            ->assertOk()
            ->assertSee('result: null', false)
            ->assertDontSee('Analyzed automatically on submission', false);
    }
}
