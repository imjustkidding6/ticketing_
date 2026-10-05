<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Jobs\AnalyzePublicTicketJob;
use App\Models\AgentSchedule;
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
use App\Services\AI\AnalyzeTicketService;
use App\Services\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Availability as a candidate attribute on suggestion 7 (P6b-2).
 *
 * Everything here runs through AnalyzePublicTicketJob, with NO session, deliberately:
 * the shift columns are tenant-local wall clock, and the only thing that makes the
 * comparison tenant-local is the workspace's own `timezone` setting, which the job
 * establishes from an explicit Tenant. A test that set the session itself would prove
 * the maths and not the plumbing.
 *
 * The clock is pinned with Carbon::setTestNow() on a UTC instant, so "09:00 in Manila"
 * and "01:00 on the server" are the same frozen moment and the assertion cannot pass by
 * accident of when the suite ran.
 */
class EngineerAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $modelReply = [
        'summary' => 'Printer offline after update',
        'polished_subject' => 'Printer offline after Windows update',
        'polished_description' => 'Issue: the office printer stopped responding.',
        'category_ref' => null,
        'priority' => null,
        'checks' => ['Restart the print spooler'],
        'engineer_ref' => null,
        'engineer_reason' => null,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.openai.api_key', 'sk-test-key-12345');
        Config::set('services.openai.model', 'gpt-4o-mini');

        // OPENAI_API_KEY is live in this environment: an unmatched endpoint must fail the
        // test rather than bill the account.
        Http::preventStrayRequests();

        Http::fake([
            'https://api.openai.com/v1/embeddings' => fn () => Http::response([
                'data' => [['embedding' => array_fill(0, 8, 0.1)]],
                'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 0],
            ]),
            'https://api.openai.com/v1/chat/completions' => fn () => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [['message' => ['role' => 'assistant', 'content' => json_encode($this->modelReply)]]],
                'usage' => ['prompt_tokens' => 900, 'completion_tokens' => 180],
            ]),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  list<string>|null  $features  Null = the whole Enterprise list.
     */
    private function createTenant(?array $features = null): Tenant
    {
        $slug = $features === null ? 'enterprise' : 'enterprise-trimmed';

        // Reused rather than recreated: `plans.slug` is unique and two workspaces in one
        // test is the normal case here.
        $plan = Plan::query()->where('slug', $slug)->first()
            ?? Plan::factory()->create([
                'slug' => $slug,
                'features' => $features ?? PlanFeature::forPlan('enterprise'),
            ]);
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
     * The workspace switches, written while a session exists and then deliberately
     * forgotten — the job must resolve all of them against the tenant.
     */
    private function switches(Tenant $tenant, string $timezone = 'UTC'): void
    {
        session(['current_tenant_id' => $tenant->id]);
        AppSetting::set('ai_enabled', true, 'boolean', 'ai');
        AppSetting::set('ai_agents_enabled', true, 'boolean', 'ai');
        AppSetting::set('timezone', $timezone, 'string', 'general');
        session()->forget('current_tenant_id');
        Cache::flush();
    }

    private function ticketFor(Tenant $tenant): Ticket
    {
        $department = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Technical Software']);
        TicketCategory::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
            'name' => 'Printer Faults',
        ]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        return Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
            'category_id' => null,
            'client_id' => $client->id,
            'priority' => null,
            'status' => 'open',
            'subject' => 'Printer offline after update',
            'description' => 'The office printer stopped responding after the Windows update.',
        ]);
    }

    private function candidate(Tenant $tenant, Ticket $ticket, string $name): User
    {
        $member = User::factory()->create([
            'name' => $name,
            'is_available' => true,
            'support_tier' => null,
        ]);
        $tenant->addUser($member, 'member');
        $member->departments()->attach($ticket->department_id);

        return $member;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function schedule(Tenant $tenant, User $member, array $overrides = []): AgentSchedule
    {
        return AgentSchedule::create(array_merge([
            'tenant_id' => $tenant->id,
            'user_id' => $member->getKey(),
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_available' => true,
            'notes' => 'SENTINEL-SCHEDULE-NOTE do not send this anywhere',
        ], $overrides));
    }

    private function runJob(Tenant $tenant, Ticket $ticket): void
    {
        (new AnalyzePublicTicketJob($tenant->id, (int) $ticket->getKey()))
            ->handle(app(AnalyzeTicketService::class), app(PlanService::class));
    }

    /**
     * The chat call's prompt as the model receives it, decoded so assertions read the
     * text rather than the JSON envelope's escaped quotes.
     */
    private function chatPrompt(): string
    {
        foreach (Http::recorded() as [$request]) {
            if (! str_contains($request->url(), 'chat/completions')) {
                continue;
            }

            $payload = json_decode($request->body(), true);

            return implode("\n\n", array_map(
                fn (array $message) => (string) ($message['content'] ?? ''),
                $payload['messages'] ?? [],
            ));
        }

        return '';
    }

    /**
     * The `available` value the prompt carried for one ref, read back out of the
     * outbound body rather than out of the service.
     */
    private function sentAvailability(string $ref): string
    {
        $body = $this->chatPrompt();

        $this->assertMatchesRegularExpression('/"ref":"'.$ref.'".*/', $body, 'The ref was never issued.');

        preg_match('/\{"ref":"'.$ref.'".*?\}/', $body, $m);
        $payload = json_decode($m[0] ?? '{}', true);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('available', $payload, 'The candidate payload carried no `available` key.');

        return var_export($payload['available'], true);
    }

    public function test_availability_is_true_on_shift_false_off_shift_and_null_without_a_schedule(): void
    {
        // Monday 10:00 UTC — inside a 09:00-17:00 Monday shift.
        Carbon::setTestNow('2026-10-05 10:00:00');

        $tenant = $this->createTenant();
        $this->switches($tenant);
        $ticket = $this->ticketFor($tenant);

        // Ordered by name, so the refs are stable: C1 Ana, C2 Bea, C3 Cely.
        $onShift = $this->candidate($tenant, $ticket, 'Ana Reyes');
        $offShift = $this->candidate($tenant, $ticket, 'Bea Santos');
        $this->candidate($tenant, $ticket, 'Cely Tan');

        $this->schedule($tenant, $onShift);
        // A real schedule, on a day that is not today: has one, is not on it.
        $this->schedule($tenant, $offShift, ['day_of_week' => 3]);

        $this->runJob($tenant, $ticket);

        $this->assertSame('true', $this->sentAvailability('C1'));
        $this->assertSame('false', $this->sentAvailability('C2'));
        $this->assertSame('NULL', $this->sentAvailability('C3'));
    }

    public function test_an_overnight_shift_is_on_shift_after_midnight(): void
    {
        // Tuesday 01:00 local. The shift row is Monday 22:00-06:00; the engineer is
        // mid-shift, and a naive BETWEEN would read 01:00 as outside it.
        Carbon::setTestNow('2026-10-06 01:00:00');

        $tenant = $this->createTenant();
        $this->switches($tenant);
        $ticket = $this->ticketFor($tenant);
        $member = $this->candidate($tenant, $ticket, 'Ana Reyes');

        $this->schedule($tenant, $member, [
            'day_of_week' => 2,
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
        ]);

        $this->runJob($tenant, $ticket);

        $this->assertSame('true', $this->sentAvailability('C1'));
    }

    public function test_availability_is_computed_in_the_workspace_timezone(): void
    {
        // 01:00 UTC is 09:00 in Manila. Under the server clock the engineer is hours off
        // shift; by the workspace's clock they have just started. This is the whole
        // department reported off shift during their working day.
        Carbon::setTestNow('2026-10-05 01:00:00');

        $tenant = $this->createTenant();
        $this->switches($tenant, timezone: 'Asia/Manila');
        $ticket = $this->ticketFor($tenant);
        $member = $this->candidate($tenant, $ticket, 'Ana Reyes');

        $this->schedule($tenant, $member, ['day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '17:00:00']);

        $this->runJob($tenant, $ticket);

        $this->assertSame('true', $this->sentAvailability('C1'));
    }

    public function test_the_workspace_timezone_can_also_move_a_candidate_onto_the_previous_day(): void
    {
        // Monday 01:00 UTC is still Sunday 18:00 in Los Angeles, so the Sunday evening
        // shift is the one that applies — the day index must come from the workspace's
        // calendar too, not only the time of day.
        Carbon::setTestNow('2026-10-05 01:00:00');

        $tenant = $this->createTenant();
        $this->switches($tenant, timezone: 'America/Los_Angeles');
        $ticket = $this->ticketFor($tenant);
        $member = $this->candidate($tenant, $ticket, 'Ana Reyes');

        $this->schedule($tenant, $member, ['day_of_week' => 0, 'start_time' => '17:00:00', 'end_time' => '21:00:00']);

        $this->runJob($tenant, $ticket);

        $this->assertSame('true', $this->sentAvailability('C1'));
    }

    public function test_an_entirely_off_shift_department_still_produces_an_engineer_suggestion(): void
    {
        Carbon::setTestNow('2026-10-05 10:00:00');

        $this->modelReply['engineer_ref'] = 'C1';
        $this->modelReply['engineer_reason'] = 'Lightest load at a sufficient tier';

        $tenant = $this->createTenant();
        $this->switches($tenant);
        $ticket = $this->ticketFor($tenant);

        $first = $this->candidate($tenant, $ticket, 'Ana Reyes');
        $second = $this->candidate($tenant, $ticket, 'Bea Santos');

        // Both scheduled, neither on shift today.
        $this->schedule($tenant, $first, ['day_of_week' => 3]);
        $this->schedule($tenant, $second, ['day_of_week' => 4]);

        $this->runJob($tenant, $ticket);

        // The list is annotated, not filtered: both candidates are still offered.
        $this->assertSame('false', $this->sentAvailability('C1'));
        $this->assertSame('false', $this->sentAvailability('C2'));

        $suggestions = AiTicketAnalysis::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->sole()
            ->suggestions;

        $this->assertNotNull($suggestions['engineer']);
        $this->assertSame((int) $first->getKey(), (int) $suggestions['engineer']['id']);
    }

    public function test_another_workspaces_schedule_row_is_never_read(): void
    {
        Carbon::setTestNow('2026-10-05 10:00:00');

        $tenant = $this->createTenant();
        $other = $this->createTenant();
        $this->switches($tenant);
        $ticket = $this->ticketFor($tenant);

        $member = $this->candidate($tenant, $ticket, 'Ana Reyes');
        // The same person works in both workspaces, and is on shift — in the OTHER one.
        $other->addUser($member, 'member');
        $otherTicket = $this->ticketFor($other);
        $this->schedule($other, $member);

        // This workspace has schedules, so the signal is sent (level 3) and the candidate
        // must read UNKNOWN rather than borrowing the other workspace's shift.
        $colleague = $this->candidate($tenant, $ticket, 'Bea Santos');
        $this->schedule($tenant, $colleague, ['day_of_week' => 3]);

        $this->runJob($tenant, $ticket);

        $this->assertSame('NULL', $this->sentAvailability('C1'));
        $this->assertNotNull($otherTicket);
    }

    public function test_the_outbound_candidate_payload_carries_availability_and_nothing_identifying(): void
    {
        Carbon::setTestNow('2026-10-05 10:00:00');

        $tenant = $this->createTenant();
        $this->switches($tenant);
        $ticket = $this->ticketFor($tenant);

        $member = User::factory()->create([
            'name' => 'Marivic Dela Cruz',
            'email' => 'marivic.delacruz@example.net',
            'is_available' => true,
            'support_tier' => 'tier_2',
        ]);
        $tenant->addUser($member, 'member');
        $member->departments()->attach($ticket->department_id);

        $this->schedule($tenant, $member);

        $this->runJob($tenant, $ticket);

        $body = $this->chatPrompt();

        $this->assertStringContainsString('{"ref":"C1","tier":"tier_2","open_tickets":0,"available":true}', $body);
        $this->assertStringNotContainsString('Marivic Dela Cruz', $body);
        $this->assertStringNotContainsString('marivic.delacruz@example.net', $body);
        $this->assertStringNotContainsString('"id":'.$member->getKey(), $body);
        // Neither the shift times nor the human-written note ever leave the server.
        $this->assertStringNotContainsString('SENTINEL-SCHEDULE-NOTE', $body);
        $this->assertStringNotContainsString('09:00', $body);
    }

    public function test_a_workspace_without_the_agent_schedule_feature_sends_no_availability_key(): void
    {
        Carbon::setTestNow('2026-10-05 10:00:00');

        // Enterprise minus agent_schedule. `agent_schedule` is Business+, so an Enterprise
        // workspace normally holds it — the case has to be constructed deliberately.
        $features = array_values(array_filter(
            PlanFeature::forPlan('enterprise'),
            fn (string $feature) => $feature !== PlanFeature::AgentSchedule->value,
        ));

        $tenant = $this->createTenant($features);
        $this->switches($tenant);
        $ticket = $this->ticketFor($tenant);
        $member = $this->candidate($tenant, $ticket, 'Ana Reyes');

        // Rows exist and are on shift; without the feature they are meaningless data.
        $this->schedule($tenant, $member);

        $this->runJob($tenant, $ticket);

        $body = $this->chatPrompt();

        $this->assertStringContainsString('{"ref":"C1","tier":null,"open_tickets":0}', $body);
        $this->assertStringNotContainsString('"available"', $body);
        $this->assertStringNotContainsString('null means UNKNOWN', $body);
    }

    public function test_a_workspace_with_no_schedules_at_all_sends_no_availability_key(): void
    {
        Carbon::setTestNow('2026-10-05 10:00:00');

        $tenant = $this->createTenant();
        $this->switches($tenant);
        $ticket = $this->ticketFor($tenant);
        $this->candidate($tenant, $ticket, 'Ana Reyes');

        $this->runJob($tenant, $ticket);

        $body = $this->chatPrompt();

        // A column of uniform nulls invites the model to reason about it; the prompt stays
        // byte-identical to the shape it had before availability existed.
        $this->assertStringContainsString('{"ref":"C1","tier":null,"open_tickets":0}', $body);
        $this->assertStringNotContainsString('"available"', $body);
        $this->assertStringNotContainsString('null means UNKNOWN', $body);
    }

    public function test_an_unavailable_schedule_row_is_off_shift_not_unknown(): void
    {
        Carbon::setTestNow('2026-10-05 10:00:00');

        $tenant = $this->createTenant();
        $this->switches($tenant);
        $ticket = $this->ticketFor($tenant);
        $member = $this->candidate($tenant, $ticket, 'Ana Reyes');

        // Today's shift, marked unavailable — leave booked, say.
        $this->schedule($tenant, $member, ['is_available' => false]);

        $this->runJob($tenant, $ticket);

        $this->assertSame('false', $this->sentAvailability('C1'));
    }

    public function test_a_schedule_that_has_not_taken_effect_yet_is_off_shift(): void
    {
        Carbon::setTestNow('2026-10-05 10:00:00');

        $tenant = $this->createTenant();
        $this->switches($tenant);
        $ticket = $this->ticketFor($tenant);
        $member = $this->candidate($tenant, $ticket, 'Ana Reyes');

        $this->schedule($tenant, $member, ['effective_date' => '2026-10-06']);

        $this->runJob($tenant, $ticket);

        $this->assertSame('false', $this->sentAvailability('C1'));
    }

    public function test_a_schedule_that_has_ended_is_off_shift(): void
    {
        Carbon::setTestNow('2026-10-05 10:00:00');

        $tenant = $this->createTenant();
        $this->switches($tenant);
        $ticket = $this->ticketFor($tenant);
        $member = $this->candidate($tenant, $ticket, 'Ana Reyes');

        $this->schedule($tenant, $member, ['end_date' => '2026-10-04']);

        $this->runJob($tenant, $ticket);

        $this->assertSame('false', $this->sentAvailability('C1'));
    }

    public function test_the_effective_and_end_dates_are_inclusive_of_today(): void
    {
        Carbon::setTestNow('2026-10-05 10:00:00');

        $tenant = $this->createTenant();
        $this->switches($tenant);
        $ticket = $this->ticketFor($tenant);
        $member = $this->candidate($tenant, $ticket, 'Ana Reyes');

        // Both boundaries land on today: a one-day rota is in effect, not excluded.
        $this->schedule($tenant, $member, [
            'effective_date' => '2026-10-05',
            'end_date' => '2026-10-05',
        ]);

        $this->runJob($tenant, $ticket);

        $this->assertSame('true', $this->sentAvailability('C1'));
    }

    public function test_a_day_shift_is_off_shift_in_the_evening_of_the_same_weekday(): void
    {
        // Monday 20:00 — the RIGHT weekday, outside the hours. This is the only case that
        // distinguishes the two branches of isOnShift()'s `$start > $end` selector: the
        // overnight form reads 20:00 >= 09:00 as on shift, so forcing it unconditionally
        // leaves every other availability test green and reds only this one.
        Carbon::setTestNow('2026-10-05 20:00:00');

        $tenant = $this->createTenant();
        $this->switches($tenant);
        $ticket = $this->ticketFor($tenant);
        $member = $this->candidate($tenant, $ticket, 'Ana Reyes');

        // The helper's default: Monday 09:00-17:00, a plain day shift.
        $this->schedule($tenant, $member);

        $this->runJob($tenant, $ticket);

        $this->assertSame('false', $this->sentAvailability('C1'), 'A 09:00-17:00 Monday shift must read off shift at Monday 20:00.');
    }

    public function test_the_schedules_for_every_candidate_are_read_in_one_query(): void
    {
        Carbon::setTestNow('2026-10-05 10:00:00');

        $tenant = $this->createTenant();
        $this->switches($tenant);
        $ticket = $this->ticketFor($tenant);

        foreach (['Ana Reyes', 'Bea Santos', 'Cely Tan', 'Dina Cruz'] as $name) {
            $this->schedule($tenant, $this->candidate($tenant, $ticket, $name));
        }

        $queries = 0;
        DB::listen(function ($query) use (&$queries) {
            if (str_contains($query->sql, 'agent_schedules')) {
                $queries++;
            }
        });

        $this->runJob($tenant, $ticket);

        // One query for four candidates. An N+1 inside a prompt builder is paid on every
        // analysis, on the surface that already makes two HTTP calls.
        $this->assertSame(1, $queries);
        $this->assertSame('true', $this->sentAvailability('C4'));
    }
}
