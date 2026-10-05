<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\AgentSchedule;
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
use App\Services\AI\AnalyzeTicketService;
use App\Services\AI\DraftSuggestionService;
use App\Services\PlanService;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * P6c-2: the suggested ENGINEER on the create-ticket draft, and the tier-aware
 * admissible-priority set that makes a suggestion possible before a client is chosen.
 *
 * Two things are under test that no other suite can see. First, what reaches OpenAI: a
 * ref, a tier, a count and an availability flag, and never a name, an email or an id —
 * asserted on the recorded outbound body, not on the service. Second, that the candidate
 * set is a strict subset of the selected department's members in THIS workspace: a
 * foreign member and a same-tenant member outside the department are each disproven with
 * a hostile fixture (the cross-tenant decoy is raw-inserted into this tenant's own
 * department pivot, because a sanitised decoy proves nothing).
 *
 * Every test fakes the HTTP layer and sets Http::preventStrayRequests(): OPENAI_API_KEY
 * is live in this environment, so an unmatched endpoint must fail the test rather than
 * bill the account.
 */
class DraftEngineerSuggestionTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $modelReply = [
        'subject' => 'Printer offline after Windows update',
        'description' => "Issue\nThe office printer stopped responding.",
        'tasks' => ['Reinstall the printer driver'],
        'priority' => 'medium',
        'priority_reason' => 'One office is affected and a workaround exists.',
        'engineer_ref' => 'C1',
        'engineer_reason' => 'Fewest open tickets and on shift now.',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.openai.api_key', 'sk-test-key-12345');
        // A non-reasoning model, so max_tokens/temperature/response_format survive into
        // the payload and the assertions describe the real request.
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function createTenant(bool $catchAllPolicy = true): Tenant
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
        $user = User::factory()->create(['name' => 'Rosalind Ferrier']);
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

    /**
     * A department member of this workspace — i.e. a legitimate candidate.
     */
    private function candidate(Tenant $tenant, ?Department $department, string $name, ?string $tier = null): User
    {
        $member = User::factory()->create([
            'name' => $name,
            'email' => str(strtolower($name))->slug().'@candidates.test',
            'is_available' => true,
            'support_tier' => $tier,
        ]);
        $tenant->addUser($member, 'member');

        if ($department !== null) {
            $member->departments()->attach($department->getKey());
        }

        return $member;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function polish(array $payload = [])
    {
        return $this->postJson($this->tenantUrl('tickets-ai/structure'), array_merge([
            'subject' => 'printer dead',
            'description' => 'printer stopped working after the windows update, nobody in the office can print',
            'context' => 'create',
        ], $payload));
    }

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

    /** The prompt as the model receives it, decoded past the JSON envelope's escapes. */
    private function chatPrompt(): string
    {
        $payload = json_decode($this->chatBody(), true);

        return implode("\n\n", array_map(
            fn (array $message) => (string) ($message['content'] ?? ''),
            $payload['messages'] ?? [],
        ));
    }

    // ───────────────── Finding A — a suggestion before a client is chosen ─────────────────

    public function test_a_per_tier_only_workspace_suggests_a_priority_with_no_client_selected(): void
    {
        // The shape the user hit on the live workspace: twelve policies, every one
        // tier-specific, no catch-all. Evaluated against `client_tier = null` nothing is
        // admissible and the button reads as broken; evaluated against the tiers the
        // workspace actually writes policies for, all four survive any client.
        $tenant = $this->createTenant(catchAllPolicy: false);
        $this->actAs($tenant);

        foreach (['basic', 'premium', 'enterprise'] as $tier) {
            foreach (['low', 'medium', 'high', 'critical'] as $priority) {
                SlaPolicy::factory()->create([
                    'tenant_id' => $tenant->id,
                    'client_tier' => $tier,
                    'priority' => $priority,
                    'is_active' => true,
                ]);
            }
        }

        $response = $this->polish();

        $response->assertOk()
            ->assertJsonPath('priority', 'medium')
            // Nothing is missing, so there is no gap to explain.
            ->assertJsonPath('priority_gap', null);
        $this->assertStringContainsString(
            "PRIORITY OPTIONS (choose one or null)\nlow, medium, high, critical",
            $this->chatPrompt(),
        );
    }

    public function test_a_priority_suggested_with_no_client_survives_submission_with_any_client(): void
    {
        $tenant = $this->createTenant(catchAllPolicy: false);
        $this->actAs($tenant);

        foreach (['basic', 'premium', 'enterprise'] as $tier) {
            foreach (['low', 'medium', 'high', 'critical'] as $priority) {
                SlaPolicy::factory()->create([
                    'tenant_id' => $tenant->id,
                    'client_tier' => $tier,
                    'priority' => $priority,
                    'is_active' => true,
                ]);
            }
        }

        $response = $this->polish()->assertOk();
        $suggested = (string) $response->json('priority');
        // Every tier a client can hold is covered, so there is genuinely nothing to warn
        // about — the contrast with the partially-configured case below.
        $this->assertNull($response->json('priority_gap'));

        // Whichever client the engineer goes on to pick, the suggestion still submits:
        // that is the whole claim of the intersection, and the guard has the final word.
        foreach (['basic', 'premium', 'enterprise'] as $tier) {
            $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => $tier]);

            $this->post($this->tenantUrl('tickets'), [
                'client_id' => $client->id,
                'subject' => 'Printer offline',
                'description' => 'The office printer stopped responding.',
                'priority' => $suggested,
            ])->assertSessionHasNoErrors();

            $this->assertDatabaseHas('tickets', [
                'tenant_id' => $tenant->id,
                'client_id' => $client->id,
                'priority' => $suggested,
            ]);
        }
    }

    public function test_a_workspace_whose_tiers_disagree_gets_the_intersection_and_is_told_so(): void
    {
        // premium and enterprise are covered for everything; basic only up to medium. A
        // `high` suggestion would be refused the moment a basic client was picked, so it
        // is not offered — and the narrowing is explained rather than merely applied.
        // Every tier a client can hold must appear here: a tier left out entirely is the
        // partially-configured case below, where nothing is admissible at all.
        $tenant = $this->createTenant(catchAllPolicy: false);
        $this->actAs($tenant);

        foreach (['premium', 'enterprise'] as $tier) {
            foreach (['low', 'medium', 'high', 'critical'] as $priority) {
                SlaPolicy::factory()->create([
                    'tenant_id' => $tenant->id,
                    'client_tier' => $tier,
                    'priority' => $priority,
                    'is_active' => true,
                ]);
            }
        }
        foreach (['low', 'medium'] as $priority) {
            SlaPolicy::factory()->create([
                'tenant_id' => $tenant->id,
                'client_tier' => 'basic',
                'priority' => $priority,
                'is_active' => true,
            ]);
        }

        $response = $this->polish();

        $response->assertOk()
            ->assertJsonPath('priority', 'medium')
            ->assertJsonPath('priority_gap.basis', 'no-client')
            ->assertJsonPath('priority_gap.missing', ['high', 'critical']);

        $message = (string) $response->json('priority_gap.message');
        $this->assertStringContainsString('high', $message);
        $this->assertStringContainsString('critical', $message);
        $this->assertStringContainsString('Settings', $message);

        $this->assertStringContainsString(
            "PRIORITY OPTIONS (choose one or null)\nlow, medium",
            $this->chatPrompt(),
        );
    }

    public function test_an_empty_intersection_names_the_missing_configuration(): void
    {
        // No usable policy at all. The message must blame the configuration, not imply a
        // weak AI — this is the sentence the engineer reads when nothing is suggested.
        $tenant = $this->createTenant(catchAllPolicy: false);
        $this->actAs($tenant);

        $response = $this->polish();

        $response->assertOk()
            ->assertJsonPath('priority', null)
            ->assertJsonPath('priority_gap.basis', 'no-client');

        $message = (string) $response->json('priority_gap.message');
        $this->assertStringContainsString('No SLA policy', $message);
        $this->assertStringContainsString('Settings', $message);

        // The text polish still happened: the missing policy costs the engineer the
        // priority, not the button.
        $this->assertSame('Printer offline after Windows update', $response->json('subject'));
        $this->assertStringContainsString('(none available — return null)', $this->chatPrompt());
    }

    public function test_a_client_tier_with_no_policies_at_all_narrows_the_suggestion_and_says_so(): void
    {
        // The partially-configured workspace: basic and premium fully covered, enterprise
        // not configured at all. Before this was fixed the draft enumerated only the
        // tiers the workspace WROTE policies for, so it suggested `medium`, reported
        // `priority_gap: null` — "nothing is wrong" — and then the submit was refused the
        // moment an enterprise client was picked. That is the original complaint inside
        // out. `clients.tier` is NOT NULL with a default, so all three tiers can turn up
        // and all three must be probed.
        $tenant = $this->createTenant(catchAllPolicy: false);
        $this->actAs($tenant);

        foreach (['basic', 'premium'] as $tier) {
            foreach (['low', 'medium', 'high', 'critical'] as $priority) {
                SlaPolicy::factory()->create([
                    'tenant_id' => $tenant->id,
                    'client_tier' => $tier,
                    'priority' => $priority,
                    'is_active' => true,
                ]);
            }
        }

        $response = $this->polish();

        $response->assertOk()
            ->assertJsonPath('priority', null)
            ->assertJsonPath('priority_gap.basis', 'no-client')
            ->assertJsonPath('priority_gap.missing', ['low', 'medium', 'high', 'critical']);
        $this->assertStringContainsString('No SLA policy', (string) $response->json('priority_gap.message'));

        // And this is what the old behaviour walked the engineer into: the uncovered tier
        // refuses the very priority that used to be suggested, with no ticket created.
        $enterprise = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'enterprise']);

        $this->post($this->tenantUrl('tickets'), [
            'client_id' => $enterprise->id,
            'subject' => 'Printer offline',
            'description' => 'The office printer stopped responding.',
            'priority' => 'medium',
        ]);

        $this->assertSame(0, Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_another_workspaces_sla_policies_never_change_this_workspaces_admissible_set(): void
    {
        // The direct pin follow-up 28 asks for, on the one query path this package added.
        // The tenant is pinned inside hasPolicyFor() and nowhere else, and no end-to-end
        // assertion can see it: another workspace's policies would silently decide what
        // this workspace may suggest, and it fails closed, so the suite would stay green.
        $tenant = $this->createTenant(catchAllPolicy: false);
        $this->actAs($tenant);

        $other = $this->createTenant(catchAllPolicy: false);
        SlaPolicy::factory()->create([
            'tenant_id' => $other->id,
            'priority' => null,
            'client_tier' => null,
            'is_active' => true,
        ]);

        $drafts = app(DraftSuggestionService::class);

        // This workspace has no policy of its own, so nothing is admissible — a
        // neighbour's catch-all must not answer for it.
        $this->assertSame([], $drafts->admissiblePriorities($tenant, null), 'A foreign workspace\'s SLA policy must not widen this workspace\'s admissible set.');

        // And its own catch-all does.
        SlaPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'priority' => null,
            'client_tier' => null,
            'is_active' => true,
        ]);

        $this->assertSame(['low', 'medium', 'high', 'critical'], $drafts->admissiblePriorities($tenant, null));
    }

    // ───────────────────────── Finding B — the engineer suggestion ─────────────────────────

    public function test_the_draft_suggests_an_engineer_from_the_selected_department(): void
    {
        $tenant = $this->createTenant();
        $this->actAs($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'premium']);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $member = $this->candidate($tenant, $department, 'Ana Reyes');

        $response = $this->polish(['client_id' => $client->id, 'department_id' => $department->id]);

        $response->assertOk()
            ->assertJsonPath('engineer.id', $member->id)
            ->assertJsonPath('engineer.name', 'Ana Reyes')
            ->assertJsonPath('engineer.reason', 'Fewest open tickets and on shift now.')
            ->assertJsonPath('engineer.prefill', true)
            ->assertJsonPath('engineer.blocked_reason', null)
            ->assertJsonPath('rejected_fields', []);

        // One call: the engineer half rides the same prompt as the text polish.
        Http::assertSentCount(1);
    }

    public function test_a_member_of_another_workspace_is_never_a_draft_engineer_candidate(): void
    {
        $tenant = $this->createTenant();
        $this->actAs($tenant);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $this->candidate($tenant, $department, 'Ana Reyes');

        // A HOSTILE fixture: a member of another workspace raw-inserted into THIS
        // workspace's department pivot. A sanitised decoy would prove nothing — the only
        // thing standing between the two is the `whereHas('tenants', …)` predicate, and
        // this is the row that exercises it.
        $other = $this->createTenant();
        $foreigner = User::factory()->create(['name' => 'ZZ Foreign Intruder', 'is_available' => true]);
        $other->addUser($foreigner, 'member');
        DB::table('department_user')->insert([
            'department_id' => $department->getKey(),
            'user_id' => $foreigner->getKey(),
        ]);

        $response = $this->polish(['department_id' => $department->id]);

        $response->assertOk()->assertJsonPath('engineer.name', 'Ana Reyes');
        // One candidate was issued, and it is not the intruder.
        $this->assertStringNotContainsString('C2', $this->chatPrompt());
        $this->assertStringNotContainsString('ZZ Foreign Intruder', $this->chatBody());
        $this->assertStringNotContainsString('ZZ Foreign Intruder', $response->getContent() ?: '');
    }

    public function test_a_same_tenant_member_outside_the_selected_department_is_never_a_candidate(): void
    {
        $tenant = $this->createTenant();
        $this->actAs($tenant);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $elsewhere = Department::factory()->create(['tenant_id' => $tenant->id]);

        $this->candidate($tenant, $department, 'Ana Reyes');
        $this->candidate($tenant, $elsewhere, 'ZZ Other Desk');
        // Same workspace, no department at all — the fail-closed half.
        $this->candidate($tenant, null, 'ZZ No Desk');

        $response = $this->polish(['department_id' => $department->id]);

        $response->assertOk()->assertJsonPath('engineer.name', 'Ana Reyes');
        $this->assertStringNotContainsString('C2', $this->chatPrompt());
        $this->assertStringNotContainsString('ZZ Other Desk', $response->getContent() ?: '');
        $this->assertStringNotContainsString('ZZ No Desk', $response->getContent() ?: '');
    }

    public function test_a_foreign_department_id_is_refused_by_validation(): void
    {
        $tenant = $this->createTenant();
        $this->actAs($tenant);

        $other = $this->createTenant();
        $foreignDepartment = Department::factory()->create(['tenant_id' => $other->id]);

        $this->polish(['department_id' => $foreignDepartment->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('department_id');

        Http::assertNothingSent();
    }

    public function test_a_foreign_department_produces_no_engineer_suggestion(): void
    {
        // The second defence, reached past the FormRequest by calling the service
        // directly: a department id this workspace does not own resolves to null, which
        // yields no candidates rather than another workspace's staff.
        $tenant = $this->createTenant();
        $this->actAs($tenant);

        $other = $this->createTenant();
        $foreignDepartment = Department::factory()->create(['tenant_id' => $other->id]);
        $foreigner = User::factory()->create(['name' => 'ZZ Foreign Intruder', 'is_available' => true]);
        $other->addUser($foreigner, 'member');
        $foreigner->departments()->attach($foreignDepartment->getKey());

        // A member of BOTH workspaces, sitting in the other one's department — the only
        // fixture under which a foreign department could yield anybody at all, since
        // `whereHas('tenants', …)` would otherwise drop the intruder regardless.
        $shared = User::factory()->create(['name' => 'ZZ Shared Member', 'is_available' => true]);
        $other->addUser($shared, 'member');
        $tenant->addUser($shared, 'member');
        $shared->departments()->attach($foreignDepartment->getKey());

        // The resolver's own guarantee, asserted directly. It has to be: end to end this
        // request has a SESSION, so `whereHas('departments', …)` carries Department's
        // global TenantScope and masks an unscoped resolver — the candidate list comes
        // back empty either way. The explicit `tenant_id` predicate is what protects the
        // SESSION-LESS caller (the automatic-analysis job), where TenantScope applies no
        // predicate at all, so it is pinned where it can actually be seen.
        $this->assertNull(
            app(AnalyzeTicketService::class)->departmentOwnedBy($tenant, (int) $foreignDepartment->getKey()),
            'The one department resolver must refuse an id this workspace does not own.',
        );

        $suggestion = app(DraftSuggestionService::class)->suggest(
            $tenant,
            auth()->user(),
            'printer dead',
            'printer stopped working after the windows update',
            null,
            (int) $foreignDepartment->getKey(),
        );

        $this->assertNull($suggestion->engineerId);
        $this->assertNull($suggestion->engineerName);
        $this->assertStringNotContainsString('ZZ Foreign Intruder', $this->chatBody());
        $this->assertStringNotContainsString('ENGINEER OPTIONS', $this->chatPrompt());
    }

    public function test_no_department_selected_suggests_no_engineer_but_still_suggests_a_priority(): void
    {
        $tenant = $this->createTenant();
        $this->actAs($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'premium']);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $this->candidate($tenant, $department, 'Ana Reyes');

        $response = $this->polish(['client_id' => $client->id]);

        $response->assertOk()
            ->assertJsonPath('engineer', null)
            ->assertJsonPath('priority', 'medium');

        // Fails closed and is never widened: the department the engineer did not pick
        // does not get to supply candidates, and the model is not even asked.
        $this->assertStringNotContainsString('ENGINEER OPTIONS', $this->chatPrompt());
        $this->assertStringNotContainsString('Ana Reyes', $this->chatBody());
    }

    public function test_the_outbound_candidate_payload_carries_no_identifying_field(): void
    {
        Carbon::setTestNow('2026-10-05 10:00:00');

        $tenant = $this->createTenant();
        $this->actAs($tenant);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $member = $this->candidate($tenant, $department, 'Ana Reyes', 'tier_2');

        AgentSchedule::create([
            'tenant_id' => $tenant->id,
            'user_id' => $member->getKey(),
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_available' => true,
            'notes' => 'SENTINEL-SCHEDULE-NOTE do not send this anywhere',
        ]);

        $this->polish(['department_id' => $department->id])->assertOk();

        $body = $this->chatBody();

        // Decoded, because the request body JSON-escapes the payload's own quotes.
        $this->assertStringContainsString(
            '{"ref":"C1","tier":"tier_2","open_tickets":0,"available":true}',
            $this->chatPrompt(),
        );
        $this->assertStringNotContainsString('Ana Reyes', $body);
        $this->assertStringNotContainsString('@candidates.test', $body);
        $this->assertStringNotContainsString('"id":'.$member->getKey(), $body);
        $this->assertStringNotContainsString('SENTINEL-SCHEDULE-NOTE', $body);
        // The department selects the candidate list; it does not appear in the prompt.
        $this->assertStringNotContainsString((string) $department->name, $body);
    }

    public function test_the_candidate_payload_has_exactly_one_builder(): void
    {
        // The guard against a second, drifting copy of the outbound shape — which is what
        // got `panelCandidates()` deleted, after the copy had already drifted.
        $hits = [];

        foreach ($this->phpFilesIn(app_path()) as $file) {
            $contents = (string) file_get_contents($file);
            $count = substr_count($contents, "'open_tickets' =>");

            if ($count > 0) {
                $hits[str_replace(base_path().'/', '', $file)] = $count;
            }
        }

        $this->assertSame(
            ['app/Services/AI/AnalyzeTicketService.php' => 1],
            $hits,
            'The outbound candidate payload must have exactly one builder: AnalyzeTicketService::candidatePayload().',
        );
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string $directory): array
    {
        $files = [];

        /** @var \SplFileInfo $file */
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    public function test_an_engineer_ref_that_was_never_issued_records_nothing(): void
    {
        $tenant = $this->createTenant();
        $this->actAs($tenant);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $this->candidate($tenant, $department, 'Ana Reyes');

        // C7 was never issued. An invented ref is a rejection, never a lookup — there is
        // no `exists:` anywhere in this path for the model's word to reach.
        $this->modelReply['engineer_ref'] = 'C7';

        $this->polish(['department_id' => $department->id])
            ->assertOk()
            ->assertJsonPath('engineer', null)
            ->assertJsonPath('rejected_fields', [AiSuggestionVerdict::TYPE_ENGINEER]);
    }

    public function test_an_off_schedule_suggested_engineer_is_advisory_and_not_prefilled(): void
    {
        // Monday 20:00: the engineer's Monday 09:00-17:00 shift has ended, so
        // guardAgentAvailability() would refuse the assignment at submit. The answer is
        // still given — only the dead-end click is removed.
        Carbon::setTestNow('2026-10-05 20:00:00');

        $tenant = $this->createTenant();
        $this->actAs($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'premium']);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $member = $this->candidate($tenant, $department, 'Ana Reyes');

        AgentSchedule::create([
            'tenant_id' => $tenant->id,
            'user_id' => $member->getKey(),
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_available' => true,
        ]);

        $response = $this->polish(['client_id' => $client->id, 'department_id' => $department->id]);

        $response->assertOk()
            ->assertJsonPath('engineer.name', 'Ana Reyes')
            ->assertJsonPath('engineer.prefill', false);
        $this->assertStringContainsString('off-schedule', (string) $response->json('engineer.blocked_reason'));

        // And the candidate list was never filtered: the suggestion exists precisely
        // because availability annotates rather than removes.
        $this->assertStringContainsString('"ref":"C1"', $this->chatPrompt());
    }

    public function test_an_entirely_off_shift_department_still_produces_an_engineer_suggestion(): void
    {
        Carbon::setTestNow('2026-10-05 20:00:00');

        $tenant = $this->createTenant();
        $this->actAs($tenant);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);

        foreach (['Ana Reyes', 'Bea Santos'] as $name) {
            $member = $this->candidate($tenant, $department, $name);
            AgentSchedule::create([
                'tenant_id' => $tenant->id,
                'user_id' => $member->getKey(),
                'day_of_week' => 1,
                'start_time' => '09:00:00',
                'end_time' => '17:00:00',
                'is_available' => true,
            ]);
        }

        $response = $this->polish(['department_id' => $department->id]);

        // Filtering would hand an entirely off-shift department an empty list and omit
        // the suggestion at exactly the hour triage help matters most.
        $response->assertOk()
            ->assertJsonPath('engineer.name', 'Ana Reyes')
            ->assertJsonPath('engineer.prefill', false);
        $this->assertStringContainsString('"available":false', $this->chatPrompt());
    }

    public function test_an_engineer_who_cannot_handle_the_suggested_priority_is_advisory_not_prefilled(): void
    {
        // Each half is fine on its own: the workspace covers `critical`, and the engineer
        // is on shift. The PAIR is refused, because guardAgentTier() will not give a
        // tier_1 agent a critical ticket. Priority is the SLA-bearing signal, so it is
        // kept and the engineer is demoted to advisory.
        $tenant = $this->createTenant();
        $this->actAs($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'premium']);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $this->candidate($tenant, $department, 'Ana Reyes', 'tier_1');

        $this->modelReply['priority'] = 'critical';

        $response = $this->polish(['client_id' => $client->id, 'department_id' => $department->id]);

        $response->assertOk()
            ->assertJsonPath('priority', 'critical')
            ->assertJsonPath('engineer.name', 'Ana Reyes')
            ->assertJsonPath('engineer.prefill', false);
        $this->assertStringContainsString('TIER 1', (string) $response->json('engineer.blocked_reason'));
    }

    public function test_the_prefilled_engineer_always_survives_submission(): void
    {
        $tenant = $this->createTenant();
        $this->actAs($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'premium']);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $this->candidate($tenant, $department, 'Ana Reyes', 'tier_3');

        $response = $this->polish(['client_id' => $client->id, 'department_id' => $department->id])->assertOk();
        $this->assertTrue($response->json('engineer.prefill'));

        // The pre-fill is only honest if createTicket() accepts it — the same ladder,
        // dry-run before the suggestion and enforced at submit.
        $this->post($this->tenantUrl('tickets'), [
            'client_id' => $client->id,
            'department_id' => $department->id,
            'subject' => 'Printer offline',
            'description' => 'The office printer stopped responding.',
            'priority' => (string) $response->json('priority'),
            'assigned_to' => (int) $response->json('engineer.id'),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tickets', [
            'tenant_id' => $tenant->id,
            'assigned_to' => (int) $response->json('engineer.id'),
        ]);
    }

    public function test_an_advisory_engineer_the_submitter_assigned_anyway_is_not_recorded_as_accepted(): void
    {
        // The `prefilled` flag's FIRST reachable false branch (follow-up 24). The
        // engineer was named in words and never written into the select, so an equal
        // landed value is the submitter's own decision — not an acceptance of ours.
        // Hardcoding the flag true turns this row into `accepted`.
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'premium']);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $member = $this->candidate($tenant, $department, 'Ana Reyes', 'tier_1');

        $this->modelReply['priority'] = 'critical';

        $response = $this->polish(['client_id' => $client->id, 'department_id' => $department->id])->assertOk();
        $this->assertFalse($response->json('engineer.prefill'));
        $token = (string) $response->json('draft_token');

        // Submitted at a priority the tier_1 engineer CAN handle, so the assignment lands.
        $this->post($this->tenantUrl('tickets'), [
            'client_id' => $client->id,
            'department_id' => $department->id,
            'subject' => 'Printer offline',
            'description' => 'The office printer stopped responding.',
            'priority' => 'medium',
            'assigned_to' => $member->getKey(),
            'ai_draft_token' => $token,
        ])->assertSessionHasNoErrors();

        $ticket = Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();
        $analysis = AiTicketAnalysis::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('ticket_id', $ticket->getKey())
            ->sole();

        $verdict = AiSuggestionVerdict::withoutGlobalScopes()
            ->where('ai_ticket_analysis_id', $analysis->getKey())
            ->where('suggestion_type', AiSuggestionVerdict::TYPE_ENGINEER)
            ->sole();

        $this->assertSame(AiSuggestionVerdict::VERDICT_REJECTED, $verdict->verdict);
        $this->assertSame(0, (int) $verdict->minutes_saved);
        $this->assertSame((int) $user->getKey(), (int) $verdict->decided_by);
    }

    public function test_a_prefilled_engineer_that_landed_records_accepted(): void
    {
        // The true branch beside it, so the flag is proven in both directions rather
        // than only in the one that is new.
        $tenant = $this->createTenant();
        $this->actAs($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'tier' => 'premium']);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $member = $this->candidate($tenant, $department, 'Ana Reyes', 'tier_3');

        $token = (string) $this->polish([
            'client_id' => $client->id,
            'department_id' => $department->id,
        ])->assertOk()->json('draft_token');

        $this->post($this->tenantUrl('tickets'), [
            'client_id' => $client->id,
            'department_id' => $department->id,
            'subject' => 'Printer offline',
            'description' => 'The office printer stopped responding.',
            'priority' => 'medium',
            'assigned_to' => $member->getKey(),
            'ai_draft_token' => $token,
        ])->assertSessionHasNoErrors();

        $analysis = AiTicketAnalysis::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();
        $verdict = AiSuggestionVerdict::withoutGlobalScopes()
            ->where('ai_ticket_analysis_id', $analysis->getKey())
            ->where('suggestion_type', AiSuggestionVerdict::TYPE_ENGINEER)
            ->sole();

        $this->assertSame(AiSuggestionVerdict::VERDICT_ACCEPTED, $verdict->verdict);
        $this->assertSame('Ana Reyes', $analysis->suggestedValueFor(AiSuggestionVerdict::TYPE_ENGINEER));
    }

    public function test_the_edit_form_draft_response_carries_no_engineer_keys(): void
    {
        $tenant = $this->createTenant();
        $this->actAs($tenant);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $this->candidate($tenant, $department, 'Ana Reyes');

        $response = $this->polish(['context' => 'edit', 'department_id' => $department->id]);

        $response->assertOk()
            ->assertJsonPath('engineer', null)
            ->assertJsonPath('priority', null)
            ->assertJsonPath('draft_token', null);

        $this->assertStringNotContainsString('ENGINEER OPTIONS', $this->chatPrompt());
        $this->assertStringNotContainsString('Ana Reyes', $this->chatBody());
    }
}
