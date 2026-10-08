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
use App\Services\PlanService;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A2-1: the Accept / Edit / Reject capture for the two resolution drafts.
 *
 * The capture pipeline is REUSED whole — `ai_ticket_analyses` + `ai_suggestion_verdicts`,
 * the hash comparison, `edit_ratio`, `minutes_saved`, the one-row-per-suggestion unique
 * index and `AiSuggestionVerdict::record()` as the single writer — with one new `trigger`
 * value and two new suggestion types. Both vocabularies are varchar-backed, so there is
 * no migration.
 *
 * Read follow-up 23 before averaging the four populations: `resolution` verdicts are
 * written when the engineer presses a copy control, i.e. they count applications
 * ATTEMPTED, like `manual` and `auto` and unlike `draft`.
 */
class ResolutionCaptureTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_DRAFT = 'ZZCLIENTDRAFTZZ We replaced the faulty patch cable and your printer is back online.';

    private const INTERNAL_DRAFT = "Cause: ZZINTERNALNOTEZZ failed patch cable.\nResolution: Replaced it.\nVerification: Test page printed.\nFollow-up: none";

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
                    'client_resolution' => self::CLIENT_DRAFT,
                    'internal_note' => self::INTERNAL_DRAFT,
                ])]]],
                'usage' => ['prompt_tokens' => 1100, 'completion_tokens' => 320],
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
        AppSetting::set('ai_agents_enabled', true, 'boolean', 'ai');

        return $user;
    }

    private function ticketFor(Tenant $tenant): Ticket
    {
        $department = Department::factory()->create(['tenant_id' => $tenant->id, 'code' => 'SOFTWARE']);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        return Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
            'client_id' => $client->id,
            'priority' => 'low',
            'status' => 'in_progress',
        ]);
    }

    private const NOTES = 'Swapped the patch cable at the wall port, reinstalled the printer driver, printed a test page.';

    private function draft(Ticket $ticket)
    {
        return $this->postJson($this->tenantUrl("tickets/{$ticket->id}/ai/resolution"), ['notes' => self::NOTES]);
    }

    private function verdict(int $analysisId, string $type, string $verdict, ?string $applied = null)
    {
        return $this->postJson($this->tenantUrl("tickets-ai/analyses/{$analysisId}/verdict"), array_filter([
            'suggestion_type' => $type,
            'verdict' => $verdict,
            'applied_value' => $applied,
        ], fn ($v) => $v !== null));
    }

    public function test_a_press_writes_one_resolution_analysis_row_with_its_cost_and_usage_log_ids(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        $ticket = $this->ticketFor($tenant);

        $analysisId = $this->draft($ticket)->assertOk()->json('analysis_id');

        $row = AiTicketAnalysis::query()->findOrFail($analysisId);
        $this->assertSame($tenant->id, $row->tenant_id);
        $this->assertSame($ticket->id, $row->ticket_id);
        $this->assertSame($user->id, $row->requested_by);
        $this->assertSame(AiTicketAnalysis::TRIGGER_RESOLUTION, $row->trigger);
        // Many resolution rows per ticket are legal: the unique index on this column is
        // the one-automatic-analysis guarantee and this trigger does not borrow it.
        $this->assertNull($row->auto_for_ticket_id);
        $this->assertSame(1100, $row->prompt_tokens);
        $this->assertSame(320, $row->completion_tokens);
        $this->assertGreaterThan(0, (float) $row->estimated_cost);
        $this->assertNotEmpty($row->usage_log_ids);
    }

    public function test_an_unedited_copy_records_accepted_with_minutes_saved_from_config(): void
    {
        $tenant = $this->createTenant();
        $this->actAs($tenant);
        $ticket = $this->ticketFor($tenant);

        $analysisId = $this->draft($ticket)->assertOk()->json('analysis_id');

        $this->verdict($analysisId, AiSuggestionVerdict::TYPE_CLIENT_RESOLUTION, 'accepted', self::CLIENT_DRAFT)
            ->assertOk()
            ->assertJsonPath('verdict', 'accepted')
            ->assertJsonPath('minutes_saved', (int) config('ai.minutes_saved.client_resolution'));

        $this->verdict($analysisId, AiSuggestionVerdict::TYPE_INTERNAL_NOTE, 'accepted', self::INTERNAL_DRAFT)
            ->assertOk()
            ->assertJsonPath('verdict', 'accepted')
            ->assertJsonPath('minutes_saved', (int) config('ai.minutes_saved.internal_note'));

        // Without a config key `(int) config(…, 0)` silently banks zero.
        $this->assertSame(6, (int) config('ai.minutes_saved.client_resolution'));
        $this->assertSame(4, (int) config('ai.minutes_saved.internal_note'));
    }

    public function test_an_edited_copy_records_edited_with_an_edit_ratio(): void
    {
        $tenant = $this->createTenant();
        $this->actAs($tenant);
        $ticket = $this->ticketFor($tenant);

        $analysisId = $this->draft($ticket)->assertOk()->json('analysis_id');

        $this->verdict(
            $analysisId,
            AiSuggestionVerdict::TYPE_CLIENT_RESOLUTION,
            'accepted',
            'We swapped a broken cable in your comms cabinet and the printer is working again.',
        )->assertOk()->assertJsonPath('verdict', 'edited');

        $row = AiSuggestionVerdict::query()
            ->where('ai_ticket_analysis_id', $analysisId)
            ->where('suggestion_type', AiSuggestionVerdict::TYPE_CLIENT_RESOLUTION)
            ->sole();
        $this->assertGreaterThan(0.0, (float) $row->edit_ratio);
        // The verdict WORD is the server's, decided by hashing, never the browser's.
        $this->assertSame(AiSuggestionVerdict::VERDICT_EDITED, $row->verdict);
    }

    public function test_the_engineers_final_text_is_not_stored(): void
    {
        $tenant = $this->createTenant();
        $this->actAs($tenant);
        $ticket = $this->ticketFor($tenant);

        $analysisId = $this->draft($ticket)->assertOk()->json('analysis_id');
        $applied = 'ZZENGINEERFINALTEXTZZ the comms cabinet cable was the fault.';

        $this->verdict($analysisId, AiSuggestionVerdict::TYPE_CLIENT_RESOLUTION, 'accepted', $applied)->assertOk();

        // Hashes plus edit_ratio answer the question; a second copy of ticket content in
        // an analytics table is another thing to scope and delete.
        $this->assertStringNotContainsString(
            'ZZENGINEERFINALTEXTZZ',
            json_encode(DB::table('ai_suggestion_verdicts')->get()).json_encode(DB::table('ai_ticket_analyses')->get()),
        );
    }

    public function test_a_dismiss_records_rejected_with_zero_minutes_saved(): void
    {
        $tenant = $this->createTenant();
        $this->actAs($tenant);
        $ticket = $this->ticketFor($tenant);

        $analysisId = $this->draft($ticket)->assertOk()->json('analysis_id');

        $this->verdict($analysisId, AiSuggestionVerdict::TYPE_INTERNAL_NOTE, 'rejected')
            ->assertOk()
            ->assertJsonPath('verdict', 'rejected')
            ->assertJsonPath('minutes_saved', 0)
            ->assertJsonPath('edit_ratio', null);
    }

    public function test_a_draft_nobody_used_records_no_verdict_at_all(): void
    {
        $tenant = $this->createTenant();
        $this->actAs($tenant);
        $ticket = $this->ticketFor($tenant);

        $analysisId = $this->draft($ticket)->assertOk()->json('analysis_id');

        // A closed tab is not a rejection. So issue volume comes from
        // ai_ticket_analyses and verdict volume from ai_suggestion_verdicts, and the
        // difference is "read but not used" — not "rejected". Pinned so it is a known
        // shape rather than a surprise in the pilot read-out.
        $this->assertSame(0, AiSuggestionVerdict::query()->where('ai_ticket_analysis_id', $analysisId)->count());
        $this->assertSame(1, AiTicketAnalysis::query()->where('ticket_id', $ticket->id)->count());
    }

    public function test_re_running_action_2_writes_a_second_analysis_and_does_not_overwrite_the_first_verdict(): void
    {
        $tenant = $this->createTenant();
        $this->actAs($tenant);
        $ticket = $this->ticketFor($tenant);

        $first = $this->draft($ticket)->assertOk()->json('analysis_id');
        $this->verdict($first, AiSuggestionVerdict::TYPE_CLIENT_RESOLUTION, 'rejected')->assertOk();

        // An engineer legitimately re-runs after doing more work.
        $second = $this->draft($ticket)->assertOk()->json('analysis_id');
        $this->assertNotSame($first, $second);
        $this->assertSame(2, AiTicketAnalysis::query()->where('ticket_id', $ticket->id)->count());

        $this->verdict($second, AiSuggestionVerdict::TYPE_CLIENT_RESOLUTION, 'accepted', self::CLIENT_DRAFT)
            ->assertOk()
            ->assertJsonPath('verdict', 'accepted');

        // Each press is its own analysis row, so verdicts attach to the run whose text
        // they judge. The unique index is on (analysis_id, suggestion_type).
        $this->assertSame('rejected', AiSuggestionVerdict::query()
            ->where('ai_ticket_analysis_id', $first)
            ->where('suggestion_type', AiSuggestionVerdict::TYPE_CLIENT_RESOLUTION)
            ->sole()->verdict);
    }

    public function test_a_verdict_on_a_suggestion_this_analysis_did_not_offer_is_refused(): void
    {
        $tenant = $this->createTenant();
        $this->actAs($tenant);
        $ticket = $this->ticketFor($tenant);

        $analysisId = $this->draft($ticket)->assertOk()->json('analysis_id');

        // A resolution run offers neither a category nor an engineer; accepting a verdict
        // on one would let the browser invent accept-rate.
        $this->verdict($analysisId, AiSuggestionVerdict::TYPE_CATEGORY, 'accepted', 'Printer Faults')
            ->assertStatus(422)
            ->assertJsonPath('error', 'This analysis did not offer a category suggestion.');

        $this->assertSame(0, AiSuggestionVerdict::query()->where('ai_ticket_analysis_id', $analysisId)->count());
    }

    public function test_a_resolution_capture_is_its_own_population(): void
    {
        $tenant = $this->createTenant();
        $this->actAs($tenant);
        $ticket = $this->ticketFor($tenant);

        $this->draft($ticket)->assertOk();

        // The other three triggers alongside it, so the GROUP BY the pilot reads is
        // exercised rather than reasoned about.
        foreach ([AiTicketAnalysis::TRIGGER_MANUAL, AiTicketAnalysis::TRIGGER_AUTO, AiTicketAnalysis::TRIGGER_DRAFT] as $trigger) {
            AiTicketAnalysis::create([
                'tenant_id' => $tenant->id,
                'ticket_id' => $ticket->id,
                'trigger' => $trigger,
                'auto_for_ticket_id' => $trigger === AiTicketAnalysis::TRIGGER_AUTO ? $ticket->id : null,
                'status' => AiTicketAnalysis::STATUS_SUCCEEDED,
                'suggestions' => ['summary' => 'A summary.'],
            ]);
        }

        $counts = AiTicketAnalysis::query()
            ->where('tenant_id', $tenant->id)
            ->selectRaw('`trigger` as t, count(*) as c')
            ->groupBy('t')
            ->pluck('c', 't')
            ->all();

        $this->assertSame(['auto' => 1, 'draft' => 1, 'manual' => 1, 'resolution' => 1], collect($counts)->sortKeys()->all());
        $this->assertSame(1, AiTicketAnalysis::query()->resolution()->where('tenant_id', $tenant->id)->count());
        $this->assertContains(AiTicketAnalysis::TRIGGER_RESOLUTION, AiTicketAnalysis::TRIGGERS);
    }

    public function test_another_workspaces_resolution_analysis_is_not_found(): void
    {
        $other = $this->createTenant();
        $otherTicket = $this->ticketFor($other);
        $foreign = AiTicketAnalysis::create([
            'tenant_id' => $other->id,
            'ticket_id' => $otherTicket->id,
            'trigger' => AiTicketAnalysis::TRIGGER_RESOLUTION,
            'status' => AiTicketAnalysis::STATUS_SUCCEEDED,
            'suggestions' => ['client_resolution' => self::CLIENT_DRAFT],
        ]);

        $tenant = $this->createTenant();
        $this->actAs($tenant);

        $this->verdict($foreign->id, AiSuggestionVerdict::TYPE_CLIENT_RESOLUTION, 'accepted', self::CLIENT_DRAFT)
            ->assertStatus(404);
    }
}
