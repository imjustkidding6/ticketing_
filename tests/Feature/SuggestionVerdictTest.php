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
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * P6a-1: Accept / Edit / Reject capture — the pilot's primary quality metric.
 *
 * The verdict endpoint spends nothing, so nothing here should reach OpenAI at all; the
 * analysis rows are built directly. What it must prove is that the metric cannot be
 * dictated by the client: accepted-vs-edited is decided server-side against the stored
 * suggestion, and the engineer's final text is never persisted.
 */
class SuggestionVerdictTest extends TestCase
{
    use RefreshDatabase;

    private const SUMMARY = 'The printer is offline after a Windows update.';

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
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

    private function actAs(Tenant $tenant, string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $tenant->addUser($user, 'member');

        $roles = app(TenantRoleService::class);
        $roles->setupDefaultRoles($tenant);
        $roles->syncRole($user, $role, $tenant);

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);
        session(['current_tenant_id' => $tenant->id]);

        AppSetting::set('ai_enabled', true, 'boolean', 'ai');
        AppSetting::set('ai_agents_enabled', true, 'boolean', 'ai');

        return $user;
    }

    /**
     * @return array{0: Ticket, 1: AiTicketAnalysis, 2: TicketCategory}
     */
    private function analysisFor(Tenant $tenant, ?User $user = null): array
    {
        $department = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Technical Software', 'code' => 'SOFTWARE']);
        $category = TicketCategory::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
            'name' => 'Printer Faults',
        ]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $ticket = Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
            'category_id' => null,
            'client_id' => $client->id,
            'priority' => 'low',
            'status' => 'open',
        ]);

        $analysis = AiTicketAnalysis::create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'requested_by' => $user?->id,
            'department_id' => $department->id,
            'status' => AiTicketAnalysis::STATUS_SUCCEEDED,
            'suggestions' => [
                'summary' => self::SUMMARY,
                'category' => ['id' => $category->id, 'name' => $category->name],
                'priority' => 'high',
            ],
        ]);

        return [$ticket, $analysis, $category];
    }

    private function postVerdict(AiTicketAnalysis $analysis, array $payload)
    {
        return $this->postJson($this->tenantUrl("tickets-ai/analyses/{$analysis->id}/verdict"), $payload);
    }

    public function test_an_unedited_apply_records_accepted(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        [$ticket, $analysis] = $this->analysisFor($tenant, $user);

        $this->postVerdict($analysis, [
            'suggestion_type' => 'summary',
            'verdict' => 'accepted',
            'applied_value' => self::SUMMARY,
        ])->assertOk()->assertJsonPath('verdict', 'accepted');

        $row = AiSuggestionVerdict::query()->sole();
        $this->assertSame($tenant->id, $row->tenant_id);
        $this->assertSame($ticket->id, $row->ticket_id);
        $this->assertSame($user->id, $row->decided_by);
        $this->assertSame($row->suggested_hash, $row->applied_hash);
        $this->assertSame(config('ai.minutes_saved.summary'), $row->minutes_saved);
        $this->assertSame(0.0, $row->edit_ratio);
        $this->assertNotNull($row->decided_at);
    }

    public function test_an_edited_apply_records_edited_with_an_edit_ratio(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        [, $analysis] = $this->analysisFor($tenant, $user);

        // The client claims 'accepted'; the server compares hashes and knows better.
        $this->postVerdict($analysis, [
            'suggestion_type' => 'summary',
            'verdict' => 'accepted',
            'applied_value' => 'The office printer went offline after the latest Windows update was installed.',
        ])->assertOk()->assertJsonPath('verdict', 'edited');

        $row = AiSuggestionVerdict::query()->sole();
        $this->assertNotSame($row->suggested_hash, $row->applied_hash);
        $this->assertGreaterThan(0, $row->edit_ratio);
        $this->assertLessThanOrEqual(1, $row->edit_ratio);
    }

    public function test_the_engineers_final_text_is_not_stored(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        [, $analysis] = $this->analysisFor($tenant, $user);

        $final = 'Zebra marmalade reconciliation phrase nobody else would write';

        $this->postVerdict($analysis, [
            'suggestion_type' => 'summary',
            'verdict' => 'accepted',
            'applied_value' => $final,
        ])->assertOk();

        // Hashes plus a ratio answer the question; a second copy of ticket content in an
        // analytics table would be another thing to scope and delete.
        foreach (['ai_suggestion_verdicts', 'ai_ticket_analyses'] as $table) {
            foreach (\DB::table($table)->get() as $stored) {
                $this->assertStringNotContainsString($final, json_encode($stored));
            }
        }
    }

    public function test_a_dismiss_records_rejected_with_zero_minutes_saved(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        [, $analysis] = $this->analysisFor($tenant, $user);

        $this->postVerdict($analysis, ['suggestion_type' => 'priority', 'verdict' => 'rejected'])
            ->assertOk()
            ->assertJsonPath('verdict', 'rejected');

        $row = AiSuggestionVerdict::query()->sole();
        $this->assertSame(0, $row->minutes_saved);
        $this->assertNull($row->applied_hash);
        $this->assertNull($row->edit_ratio);
    }

    public function test_re_deciding_the_same_suggestion_updates_the_one_row(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        [, $analysis] = $this->analysisFor($tenant, $user);

        $this->postVerdict($analysis, ['suggestion_type' => 'priority', 'verdict' => 'rejected'])->assertOk();
        $this->postVerdict($analysis, ['suggestion_type' => 'priority', 'verdict' => 'accepted', 'applied_value' => 'high'])->assertOk();

        $this->assertSame(1, AiSuggestionVerdict::query()->count());
        $this->assertSame('accepted', AiSuggestionVerdict::query()->sole()->verdict);
    }

    public function test_a_verdict_on_a_suggestion_the_analysis_did_not_offer_is_refused(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        [, $analysis] = $this->analysisFor($tenant, $user);

        // `checks` and `engineer` arrive in P6a-2; until they are produced a verdict on
        // either is meaningless, and accepting one would let a client invent accept-rate.
        $this->postVerdict($analysis, ['suggestion_type' => 'checks', 'verdict' => 'accepted', 'applied_value' => 'x'])
            ->assertStatus(422);

        $this->assertSame(0, AiSuggestionVerdict::query()->count());
    }

    public function test_the_ticket_id_is_taken_from_the_analysis_not_the_request(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        [$ticket, $analysis] = $this->analysisFor($tenant, $user);

        $otherTicket = Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => Client::factory()->create(['tenant_id' => $tenant->id])->id,
        ]);

        $this->postVerdict($analysis, [
            'suggestion_type' => 'summary',
            'verdict' => 'accepted',
            'applied_value' => self::SUMMARY,
            'ticket_id' => $otherTicket->id,
        ])->assertOk();

        $this->assertSame($ticket->id, AiSuggestionVerdict::query()->sole()->ticket_id);
    }

    public function test_another_workspaces_analysis_is_not_found(): void
    {
        $other = $this->createTenant();
        [, $foreignAnalysis] = $this->analysisFor($other);

        $tenant = $this->createTenant();
        $this->actAs($tenant);

        $this->postVerdict($foreignAnalysis, ['suggestion_type' => 'summary', 'verdict' => 'rejected'])
            ->assertNotFound();
    }

    public function test_an_agent_outside_the_department_cannot_record_a_verdict(): void
    {
        $tenant = $this->createTenant();
        $owner = User::factory()->create();
        $tenant->addUser($owner, 'member');
        [, $analysis] = $this->analysisFor($tenant, $owner);

        // Applying authorizeTicketAccess() to analyze() only is the half-applied-check
        // IDOR CLAUDE.md names; this is the other half.
        $this->actAs($tenant, 'agent');

        $this->postVerdict($analysis, ['suggestion_type' => 'summary', 'verdict' => 'rejected'])
            ->assertForbidden();
    }

    public function test_applying_a_suggested_priority_goes_through_the_normal_update_path_and_the_sla_guard(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        [$ticket, $analysis] = $this->analysisFor($tenant, $user);

        $this->postVerdict($analysis, ['suggestion_type' => 'priority', 'verdict' => 'accepted', 'applied_value' => 'high'])
            ->assertOk();

        // The AI did not write it. The engineer's Apply is the ordinary ticket update,
        // so guardSlaPolicy() runs and SlaService::assignSla() recomputes the deadlines.
        $this->assertSame('low', $ticket->fresh()->priority);

        $this->put($this->tenantUrl("tickets/{$ticket->id}"), [
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'priority' => 'high',
            'status' => $ticket->status,
            'client_id' => $ticket->client_id,
            'category_id' => $ticket->category_id,
            'department_id' => $ticket->department_id,
        ])->assertRedirect();

        $fresh = $ticket->fresh();
        $this->assertSame('high', $fresh->priority);
        // Recomputed, not inherited from the low-priority targets.
        $this->assertNotNull($fresh->resolution_due_at);
    }

    /**
     * The pair, not the happy path. The accept-vs-edit rate is only a real metric if BOTH
     * outcomes are reachable from the same suggestion with the same request shape — the UI
     * claims `accepted` either way and the server decides. Finding 1 of the P6a-1 review was
     * that only one half of this was reachable in practice.
     */
    public function test_the_same_suggestion_records_accepted_unchanged_and_edited_when_changed(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        [, $analysis] = $this->analysisFor($tenant, $user);

        // Applied exactly as suggested.
        $this->postVerdict($analysis, [
            'suggestion_type' => 'summary',
            'verdict' => 'accepted',
            'applied_value' => self::SUMMARY,
        ])->assertOk()->assertJsonPath('verdict', 'accepted');

        $this->assertSame(0.0, AiSuggestionVerdict::query()->sole()->edit_ratio);

        // Re-decided after an edit, same endpoint, same claimed verdict, same single row.
        $this->postVerdict($analysis, [
            'suggestion_type' => 'summary',
            'verdict' => 'accepted',
            'applied_value' => 'Printer offline; spooler restart did not fix it. Escalating to Hardware.',
        ])->assertOk()->assertJsonPath('verdict', 'edited');

        $row = AiSuggestionVerdict::query()->sole();
        $this->assertSame('edited', $row->verdict);
        $this->assertNotSame($row->suggested_hash, $row->applied_hash);
        $this->assertGreaterThan(0, $row->edit_ratio);
    }

    public function test_an_edited_category_is_reported_by_name_and_records_edited(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        [, $analysis] = $this->analysisFor($tenant, $user);

        // The panel reports an edited category by NAME, because that is what the stored
        // suggestion holds and the comparison is over the stored value.
        $this->postVerdict($analysis, [
            'suggestion_type' => 'category',
            'verdict' => 'accepted',
            'applied_value' => 'Network Faults',
        ])->assertOk()->assertJsonPath('verdict', 'edited');

        $this->assertSame(
            config('ai.minutes_saved.category'),
            AiSuggestionVerdict::query()->sole()->minutes_saved,
        );
    }
}
