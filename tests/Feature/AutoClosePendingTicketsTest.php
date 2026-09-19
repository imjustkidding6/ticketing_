<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\AppSetting;
use App\Models\Client;
use App\Models\License;
use App\Models\Plan;
use App\Models\ServiceReport;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\TicketTask;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sweep runs outside any tenant session, closes through TicketService so the
 * pause is ended and history written, and must survive one stuck ticket without
 * abandoning the rest of the run.
 */
class AutoClosePendingTicketsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createBusinessTenant();
        $this->actAsOwnerOf($this->tenant);
    }

    private function createBusinessTenant(): Tenant
    {
        $plan = Plan::firstOrCreate(
            ['slug' => 'business'],
            Plan::factory()->raw(['slug' => 'business', 'features' => PlanFeature::forPlan('business')]),
        );
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);

        // Business+ enforces the SLA-policy guard on ticket priority; a catch-all
        // policy matches everything so TicketService ops don't throw.
        SlaPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'priority' => null,
            'client_tier' => null,
            'is_active' => true,
        ]);

        return $tenant;
    }

    private function actAsOwnerOf(Tenant $tenant): User
    {
        $user = User::factory()->create();
        $tenant->addUser($user, 'owner');
        $this->actingAs($user)->withTenant($tenant)
            ->withSession(['current_tenant_id' => $tenant->id]);

        return $user;
    }

    /**
     * A ticket parked in pending, waiting on the customer since $daysAgo.
     */
    private function pendingTicket(Tenant $tenant, int $daysAgo): Ticket
    {
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $ticket = app(TicketService::class)->createTicket([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'subject' => 'waiting on customer',
            'description' => 'waiting on customer',
            'priority' => 'medium',
        ]);

        app(TicketService::class)->changeStatus($ticket, 'pending');

        // Backdate the wait rather than travelling the clock, so the ticket's own
        // pause bookkeeping stays consistent with what changeStatus() wrote.
        $ticket->forceFill([
            'pending_since' => now()->subDays($daysAgo),
            'hold_started_at' => now()->subDays($daysAgo),
        ])->save();

        return $ticket->fresh();
    }

    private function setThreshold(Tenant $tenant, int $days): void
    {
        AppSetting::updateOrCreate(
            ['tenant_id' => $tenant->id, 'key' => 'auto_close_pending_days'],
            ['value' => (string) $days, 'type' => 'string', 'group' => 'ticket'],
        );
    }

    public function test_closes_only_pending_tickets_past_the_threshold(): void
    {
        $this->setThreshold($this->tenant, 5);

        $stale = $this->pendingTicket($this->tenant, 7);
        $fresh = $this->pendingTicket($this->tenant, 2);

        $open = $this->pendingTicket($this->tenant, 30);
        app(TicketService::class)->changeStatus($open, 'in_progress');

        $this->artisan('tickets:auto-close-pending')->assertExitCode(0);

        $this->assertSame('closed', $stale->fresh()->status);
        $this->assertSame('pending', $fresh->fresh()->status);
        $this->assertSame('in_progress', $open->fresh()->status);
    }

    public function test_is_a_noop_when_the_setting_is_zero(): void
    {
        // Zero is the default — nothing auto-closes without a tenant opting in.
        $ticket = $this->pendingTicket($this->tenant, 90);

        $this->artisan('tickets:auto-close-pending')->assertExitCode(0);

        $this->assertSame('pending', $ticket->fresh()->status);
    }

    public function test_skips_a_ticket_with_incomplete_tasks_and_continues(): void
    {
        $this->setThreshold($this->tenant, 1);

        $stuck = $this->pendingTicket($this->tenant, 10);
        TicketTask::factory()->create([
            'tenant_id' => $this->tenant->id,
            'ticket_id' => $stuck->id,
            'status' => 'pending',
        ]);

        $next = $this->pendingTicket($this->tenant, 10);

        $this->artisan('tickets:auto-close-pending')->assertExitCode(0);

        // The refused ticket is one ticket's problem, not the run's.
        $this->assertSame('pending', $stuck->fresh()->status);
        $this->assertSame('closed', $next->fresh()->status);
    }

    public function test_closure_goes_through_change_status(): void
    {
        $this->setThreshold($this->tenant, 1);

        $ticket = $this->pendingTicket($this->tenant, 10);

        $this->artisan('tickets:auto-close-pending')->assertExitCode(0);

        $closed = $ticket->fresh();

        $this->assertSame('closed', $closed->status);
        $this->assertNotNull($closed->closed_at);
        $this->assertNotNull($closed->first_closed_at);

        // The pause is ended by the same branch as any other exit from pending,
        // so the wait is accounted for rather than left running.
        $this->assertNull($closed->hold_started_at);
        $this->assertGreaterThan(0, $closed->total_hold_time_minutes);

        $this->assertTrue(
            TicketHistory::withoutGlobalScopes()
                ->where('ticket_id', $ticket->id)
                ->where('action', 'status_changed')
                ->where('new_value', 'closed')
                ->exists(),
        );
    }

    public function test_service_report_generated_by_the_sweep_carries_the_tickets_tenant(): void
    {
        // ServiceReport is a BelongsToTenant model, and the sweep has no tenant
        // session of its own — so tenant_id has to be stamped from the ticket.
        // Without that it lands null and the report is invisible to its owner.
        $this->setThreshold($this->tenant, 1);

        $ticket = $this->pendingTicket($this->tenant, 10);

        $this->artisan('tickets:auto-close-pending')->assertExitCode(0);

        $report = ServiceReport::withoutGlobalScopes()->where('ticket_id', $ticket->id)->first();

        $this->assertNotNull($report, 'closing a Business tenant ticket should generate a service report');
        $this->assertSame($this->tenant->id, $report->tenant_id);
    }

    public function test_does_not_cross_tenants(): void
    {
        $other = $this->createBusinessTenant();

        $this->setThreshold($this->tenant, 1);
        // The other workspace never opted in, so its ticket must survive a sweep
        // driven by someone else's threshold.
        $this->setThreshold($other, 0);

        $mine = $this->pendingTicket($this->tenant, 10);

        $this->actAsOwnerOf($other);
        $theirs = $this->pendingTicket($other, 10);

        $this->actAsOwnerOf($this->tenant);

        $this->artisan('tickets:auto-close-pending')->assertExitCode(0);

        $this->assertSame('closed', $mine->fresh()->status);
        $this->assertSame('pending', Ticket::withoutGlobalScopes()->find($theirs->id)->status);
    }

    public function test_tenant_option_limits_the_sweep(): void
    {
        $other = $this->createBusinessTenant();

        $this->setThreshold($this->tenant, 1);
        $this->setThreshold($other, 1);

        $mine = $this->pendingTicket($this->tenant, 10);

        $this->actAsOwnerOf($other);
        $theirs = $this->pendingTicket($other, 10);

        $this->actAsOwnerOf($this->tenant);

        $this->artisan('tickets:auto-close-pending', ['--tenant' => $this->tenant->id])->assertExitCode(0);

        $this->assertSame('closed', $mine->fresh()->status);
        $this->assertSame('pending', Ticket::withoutGlobalScopes()->find($theirs->id)->status);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->setThreshold($this->tenant, 1);

        $ticket = $this->pendingTicket($this->tenant, 10);

        $this->artisan('tickets:auto-close-pending', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame('pending', $ticket->fresh()->status);
    }
}
