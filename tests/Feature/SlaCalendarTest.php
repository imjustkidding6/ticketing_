<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\SlaCalendar;
use App\Models\SlaCalendarHoliday;
use App\Models\SlaCalendarHour;
use App\Models\Tenant;
use App\Models\User;
use App\Support\SlaClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The calendar tables and their wiring to SlaClock. Nothing reads them yet — zero
 * behaviour change is the promise of this commit, and the last test here is the only
 * thing that can prove it.
 */
class SlaCalendarTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TenantScope reads session('current_tenant_id') but returns null unless a user is
     * authenticated, so acting as a member of the workspace is what makes the global
     * scope apply at all. Without it these assertions would pass against an unscoped
     * query and prove nothing.
     */
    private function actAsMemberOf(Tenant $tenant): void
    {
        $user = User::factory()->create();
        $tenant->addUser($user, 'member');

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);
    }

    public function test_calendar_is_scoped_to_its_tenant(): void
    {
        $tenantA = Tenant::factory()->withLicense()->create();
        $tenantB = Tenant::factory()->withLicense()->create();

        $calendarA = SlaCalendar::factory()->nineToFive()->create(['tenant_id' => $tenantA->id]);
        SlaCalendarHoliday::factory()->create([
            'tenant_id' => $tenantA->id,
            'sla_calendar_id' => $calendarA->id,
        ]);

        $this->actAsMemberOf($tenantB);
        $this->assertSame(0, SlaCalendar::count());
        // The children too, queried directly — a child reachable only through its parent
        // is still a child someone will query directly.
        $this->assertSame(0, SlaCalendarHour::count());
        $this->assertSame(0, SlaCalendarHoliday::count());

        $this->actAsMemberOf($tenantA);
        $this->assertSame(1, SlaCalendar::count());
        $this->assertSame(5, SlaCalendarHour::count());
        $this->assertSame(1, SlaCalendarHoliday::count());
    }

    public function test_calendar_defaults_its_timezone_from_the_workspace_setting(): void
    {
        $tenant = Tenant::factory()->withLicense()->create();
        $this->actAsMemberOf($tenant);

        AppSetting::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'group' => 'general', 'key' => 'timezone', 'value' => 'Asia/Manila',
        ]);

        $calendar = SlaCalendar::create(['tenant_id' => $tenant->id, 'name' => 'Support hours']);

        $this->assertSame('Asia/Manila', $calendar->timezone);

        // Changing where reports are read must not silently move every deadline: the
        // zone is seeded once and owned by the calendar from then on.
        AppSetting::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('key', 'timezone')
            ->update(['value' => 'Europe/Berlin']);

        $this->assertSame('Asia/Manila', $calendar->fresh()->timezone);
    }

    public function test_from_calendar_builds_a_clock_matching_the_rows(): void
    {
        $tenant = Tenant::factory()->withLicense()->create();
        $this->actAsMemberOf($tenant);

        $calendar = SlaCalendar::factory()->splitShift()->create([
            'tenant_id' => $tenant->id,
            'timezone' => 'UTC',
        ]);
        SlaCalendarHoliday::factory()->create([
            'tenant_id' => $tenant->id,
            'sla_calendar_id' => $calendar->id,
            'month' => 9, 'day' => 22, 'year' => null,
        ]);

        $fromRows = SlaClock::fromCalendar($calendar->fresh());

        $windows = [];
        for ($day = 1; $day <= 5; $day++) {
            $windows[] = ['day' => $day, 'start' => 9 * 60, 'end' => 12 * 60];
            $windows[] = ['day' => $day, 'start' => 13 * 60, 'end' => 17 * 60];
        }
        $byHand = new SlaClock('UTC', $windows, [['month' => 9, 'day' => 22, 'year' => null]]);

        $this->assertSame('UTC', $fromRows->timezone());
        $this->assertTrue($fromRows->hasOpenWindows());

        foreach (['2026-09-21 11:30', '2026-09-18 18:00', '2026-09-22 10:00'] as $start) {
            $from = CarbonImmutable::parse($start, 'UTC');

            $this->assertSame(
                $byHand->addBusinessMinutes($from, 240)->toIso8601String(),
                $fromRows->addBusinessMinutes($from, 240)->toIso8601String(),
                "fromCalendar() diverges from the hand-built clock at {$start}"
            );
        }
    }

    public function test_has_open_windows_is_false_for_a_calendar_with_no_hours(): void
    {
        $tenant = Tenant::factory()->withLicense()->create();
        $this->actAsMemberOf($tenant);

        $calendar = SlaCalendar::factory()->create(['tenant_id' => $tenant->id]);

        // The cheap check that lets a future editor refuse "business hours" at
        // configuration time, where the mistake is made and can be fixed.
        $this->assertFalse($calendar->hasOpenWindows());
        $this->assertFalse(SlaClock::fromCalendar($calendar)->hasOpenWindows());
    }

    /**
     * Zero behaviour change is the promise of this commit. Shipping the arithmetic dark
     * is deliberate — it is the part most likely to be subtly wrong and it can be proven
     * in isolation — but a promise nothing checks is a promise someone breaks by
     * accident in the next commit.
     */
    public function test_nothing_reads_the_calendar_yet(): void
    {
        $roots = [base_path('app/Services'), base_path('app/Http'), base_path('routes')];

        $offenders = [];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($files as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                if (preg_match('/\bSlaClock\b|\bSlaCalendar(Hour|Holiday)?\b/', $contents)) {
                    $offenders[] = str_replace(base_path().'/', '', $file->getPathname());
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['SlaClock/SlaCalendar must have no consumer yet. Found references in:'],
            $offenders,
        )));
    }
}
