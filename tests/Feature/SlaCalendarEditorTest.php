<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\ActivityLog;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaCalendar;
use App\Models\SlaCalendarHour;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The calendar editor: one screen, one POST, replace-all semantics.
 *
 * It ships in the same commit as the mode control because a mode of 'business' is
 * meaningless without it — shipping the control alone would give every existing tenant a
 * select whose only interesting option is permanently disabled.
 */
class SlaCalendarEditorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function setupContext(string $planSlug = 'business', string $role = 'admin'): array
    {
        $plan = Plan::factory()->create(['slug' => $planSlug, 'features' => PlanFeature::forPlan($planSlug)]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);

        $roleService = app(TenantRoleService::class);
        $roleService->setupDefaultRoles($tenant);

        $user = User::factory()->create();
        $tenant->addUser($user, $role);
        $roleService->syncRole($user, $role, $tenant);

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);

        return [$tenant, $user];
    }

    /**
     * @param  array<int, list<array{start:string,end:string}>>  $windows
     * @param  list<array<string, mixed>>  $holidays
     * @return array<string, mixed>
     */
    private function payload(array $windows = [], array $holidays = [], string $tz = 'UTC'): array
    {
        // Every day carrying windows is implicitly switched on, which is what the form
        // posts. Tests that care about the toggle set `days` themselves.
        $days = [];
        foreach (array_keys($windows) as $day) {
            $days[$day] = 1;
        }

        return ['timezone' => $tz, 'days' => $days, 'windows' => $windows, 'holidays' => $holidays];
    }

    private function weekdays(): array
    {
        $windows = [];
        foreach (range(1, 5) as $day) {
            $windows[$day] = [['start' => '09:00', 'end' => '17:00']];
        }

        return $windows;
    }

    public function test_calendar_editor_is_404_for_a_starter_tenant(): void
    {
        $this->setupContext('starter');

        // The route joins the existing feature:sla_management group; no new PlanFeature
        // was added, so a Starter tenant sees exactly what it sees for every other
        // sla.* route.
        $this->get($this->tenantUrl('/sla/calendar'))->assertForbidden();
    }

    public function test_a_viewer_cannot_open_the_calendar_editor(): void
    {
        $this->setupContext('business', 'viewer');

        // 'update sla policies' — already defined, already granted in the role matrix,
        // already enforced on the tier editor this screen is reached from. No new
        // permission was invented for it.
        $this->get($this->tenantUrl('/sla/calendar'))->assertForbidden();
        $this->post($this->tenantUrl('/sla/calendar'), $this->payload($this->weekdays()))->assertForbidden();
    }

    public function test_saving_creates_the_calendar_and_its_windows(): void
    {
        [$tenant] = $this->setupContext();

        $this->post($this->tenantUrl('/sla/calendar'), $this->payload($this->weekdays(), [], 'Asia/Manila'))
            ->assertRedirect($this->tenantUrl('/sla/calendar'));

        $this->assertDatabaseHas('sla_calendars', [
            'tenant_id' => $tenant->id,
            'timezone' => 'Asia/Manila',
            'is_default' => true,
        ]);
        $this->assertDatabaseCount('sla_calendar_hours', 5);
        $this->assertDatabaseHas('sla_calendar_hours', [
            'tenant_id' => $tenant->id,
            'day_of_week' => 1,
            'start_minute' => 540,
            'end_minute' => 1020,
        ]);
    }

    public function test_split_shifts_round_trip(): void
    {
        [$tenant] = $this->setupContext();

        $this->post($this->tenantUrl('/sla/calendar'), $this->payload([
            1 => [
                ['start' => '09:00', 'end' => '12:00'],
                ['start' => '13:00', 'end' => '17:00'],
            ],
        ]))->assertRedirect();

        // Two rows on one weekday IS the split shift — there is no start/end pair on a
        // day row, which is what stopped this being a retrofit.
        $this->assertDatabaseCount('sla_calendar_hours', 2);

        $response = $this->get($this->tenantUrl('/sla/calendar'));
        $response->assertOk();
        $response->assertSee('09:00', false);
        $response->assertSee('12:00', false);
        $response->assertSee('13:00', false);
        $response->assertSee('17:00', false);
    }

    public function test_a_day_with_no_windows_is_closed(): void
    {
        $this->setupContext();

        $this->post($this->tenantUrl('/sla/calendar'), $this->payload([
            1 => [['start' => '09:00', 'end' => '17:00']],
        ]))->assertRedirect();

        $this->assertDatabaseCount('sla_calendar_hours', 1);
    }

    public function test_a_holiday_repeats_annually_by_default(): void
    {
        [$tenant] = $this->setupContext();

        // Posted without a year, with "repeats every year" ticked — the default state of
        // the checkbox. The JSM failure mode (a calendar nobody repopulates in January)
        // must require a deliberate act to opt into.
        $this->post($this->tenantUrl('/sla/calendar'), $this->payload($this->weekdays(), [
            ['name' => 'Christmas Day', 'month' => 12, 'day' => 25, 'recurring' => 1],
        ]))->assertRedirect();

        $this->assertDatabaseHas('sla_calendar_holidays', [
            'tenant_id' => $tenant->id,
            'name' => 'Christmas Day',
            'month' => 12,
            'day' => 25,
            'year' => null,
        ]);
    }

    public function test_a_one_off_holiday_keeps_its_year(): void
    {
        $this->setupContext();

        $this->post($this->tenantUrl('/sla/calendar'), $this->payload($this->weekdays(), [
            ['name' => 'Company offsite', 'month' => 3, 'day' => 3, 'year' => 2027],
        ]))->assertRedirect();

        $this->assertDatabaseHas('sla_calendar_holidays', ['name' => 'Company offsite', 'year' => 2027]);
    }

    public function test_a_one_off_holiday_without_a_year_is_refused(): void
    {
        $this->setupContext();

        $this->post($this->tenantUrl('/sla/calendar'), $this->payload($this->weekdays(), [
            ['name' => 'Company offsite', 'month' => 3, 'day' => 3],
        ]))->assertSessionHasErrors('holidays.0.year');

        $this->assertDatabaseCount('sla_calendar_holidays', 0);
    }

    public function test_a_window_ending_before_it_starts_is_refused(): void
    {
        $this->setupContext();

        // A shift running past midnight is two shifts on two days; an inverted row would
        // otherwise be dropped silently by SlaClock and the hours would just vanish.
        $this->post($this->tenantUrl('/sla/calendar'), $this->payload([
            1 => [['start' => '17:00', 'end' => '09:00']],
        ]))->assertSessionHasErrors('windows.1.0.end');

        $this->assertDatabaseCount('sla_calendar_hours', 0);
    }

    public function test_removing_every_window_is_refused_while_a_policy_uses_business_hours(): void
    {
        [$tenant] = $this->setupContext();

        SlaCalendar::factory()->nineToFive()->create(['tenant_id' => $tenant->id]);
        SlaPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'priority' => 'high',
            'client_tier' => null,
            'is_active' => true,
            'resolution_hours_mode' => 'business',
        ]);

        // Saving an empty calendar would strand that target: SlaService falls back to
        // calendar hours rather than throwing, so the target would silently start meaning
        // something else. Refused at configuration time, the only place it fails safely.
        $response = $this->post($this->tenantUrl('/sla/calendar'), $this->payload([]));

        $response->assertSessionHasErrors('windows');
        $this->assertSame(5, SlaCalendarHour::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_an_empty_calendar_is_allowed_when_nothing_uses_business_hours(): void
    {
        [$tenant] = $this->setupContext();

        SlaCalendar::factory()->nineToFive()->create(['tenant_id' => $tenant->id]);

        $this->post($this->tenantUrl('/sla/calendar'), $this->payload([]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('sla_calendar_hours', 0);
    }

    public function test_a_second_tenant_cannot_read_or_write_this_calendar(): void
    {
        $tenantA = Tenant::factory()->withLicense()->create();
        SlaCalendar::factory()->nineToFive()->create([
            'tenant_id' => $tenantA->id,
            'timezone' => 'Australia/Sydney',
        ]);

        // Authenticated as a member of B, with B's session — the calendar is resolved by
        // an ordinary scoped query and never by an id from the request, so B gets its own
        // empty calendar rather than A's.
        [$tenantB] = $this->setupContext();

        $response = $this->get($this->tenantUrl('/sla/calendar'));
        $response->assertOk();
        // Not assertDontSee on the zone name: every IANA zone is an <option> on this
        // form. What must not leak is A's *state* — its selected zone and its windows.
        $response->assertDontSee('value="Australia/Sydney" selected', false);
        // And nothing of A's is reachable through the scoped queries the editor uses.
        $this->assertNull(SlaCalendar::query()->default()->first());
        $this->assertSame(0, SlaCalendarHour::query()->count());

        $this->post($this->tenantUrl('/sla/calendar'), $this->payload($this->weekdays(), [], 'Europe/Lisbon'))
            ->assertRedirect();

        // A's calendar is untouched; B got a new one.
        $this->assertDatabaseHas('sla_calendars', ['tenant_id' => $tenantA->id, 'timezone' => 'Australia/Sydney']);
        $this->assertDatabaseHas('sla_calendars', ['tenant_id' => $tenantB->id, 'timezone' => 'Europe/Lisbon']);
        $this->assertSame(5, SlaCalendarHour::withoutGlobalScopes()->where('tenant_id', $tenantA->id)->count());
    }

    public function test_saving_the_calendar_writes_an_activity_log_entry(): void
    {
        [$tenant] = $this->setupContext();

        SlaCalendar::factory()->nineToFive()->create(['tenant_id' => $tenant->id, 'timezone' => 'UTC']);
        ActivityLog::withoutGlobalScopes()->delete();

        // Only the windows move here, so the parent's own attributes are unchanged. The
        // touch() in the controller is what makes "who deleted our Saturday hours"
        // answerable — the child rows deliberately carry no LogsActivity.
        $this->post($this->tenantUrl('/sla/calendar'), $this->payload([
            1 => [['start' => '09:00', 'end' => '17:00']],
        ]))->assertRedirect();

        $this->assertTrue(
            ActivityLog::withoutGlobalScopes()
                ->where('subject_type', SlaCalendar::class)
                ->exists()
        );
    }

    public function test_the_editor_names_the_consequences_of_a_change(): void
    {
        $this->setupContext();

        $response = $this->get($this->tenantUrl('/sla/calendar'));

        $response->assertOk();
        $response->assertSee('Tickets already open keep the deadline they were given.');
        $response->assertSee('Changing these hours affects reporting for tickets already closed.');
    }

    public function test_first_visit_prefills_the_ordinary_working_week(): void
    {
        $this->setupContext();

        $response = $this->get($this->tenantUrl('/sla/calendar'));

        $response->assertOk();
        // Not an empty grid. The workspace is here to answer "which days are we open",
        // and Mon-Fri 09:00-17:00 with the weekend off is one click rather than seven
        // rows of typing. Still only a prefill — a GET writes nothing.
        $this->assertDatabaseCount('sla_calendars', 0);
        // Js::from() renders the state as \u0022-escaped JSON inside the x-data attribute.
        $this->assertSame(5, substr_count($response->getContent(), '\u0022start\u0022:\u002209:00\u0022'));
        $response->assertSee('\u00226\u0022:false', false);
        $response->assertSee('\u00227\u0022:false', false);
    }

    public function test_a_day_switched_on_with_no_times_is_refused(): void
    {
        $this->setupContext();

        $this->post($this->tenantUrl('/sla/calendar'), [
            'timezone' => 'UTC',
            'days' => [1 => 1],
            'windows' => [],
        ])->assertSessionHasErrors('windows.1');

        $this->assertDatabaseCount('sla_calendar_hours', 0);
    }

    public function test_a_day_switched_off_is_closed_whatever_its_times_say(): void
    {
        $this->setupContext();

        // The toggle is the answer to "do we work weekends"; leftover times on a day that
        // is off must not quietly reopen it.
        $this->post($this->tenantUrl('/sla/calendar'), [
            'timezone' => 'UTC',
            'days' => [1 => 1],
            'windows' => [
                1 => [['start' => '09:00', 'end' => '17:00']],
                6 => [['start' => '10:00', 'end' => '14:00']],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('sla_calendar_hours', 1);
        $this->assertDatabaseMissing('sla_calendar_hours', ['day_of_week' => 6]);
    }

    public function test_the_page_says_what_a_48_hour_target_works_out_at(): void
    {
        [$tenant] = $this->setupContext();

        SlaCalendar::factory()->nineToFive()->create(['tenant_id' => $tenant->id, 'timezone' => 'UTC']);

        $response = $this->get($this->tenantUrl('/sla/calendar'));

        $response->assertOk();
        // 48 business hours on a five-day, eight-hour week is six working days. Computed
        // with SlaClock, so the page cannot claim one thing while the tickets do another.
        $response->assertSee('A 48 business-hour target on this calendar is about 6 working days', false);
    }
}
