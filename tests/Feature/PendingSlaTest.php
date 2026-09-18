<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Client;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\ReportService;
use App\Services\SlaService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 'pending' means the ball is in the customer's court. It is deliberately a
 * labelled hold — the same pause machinery, the same SLA arithmetic — so these
 * tests pin that it behaves identically to on_hold where the clock is concerned,
 * and that its own counter never reaches an SLA figure.
 */
class PendingSlaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->create(['slug' => 'business', 'features' => PlanFeature::forPlan('business')]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $this->tenant = Tenant::factory()->create(['license_id' => $license->id]);

        SlaPolicy::factory()->create([
            'tenant_id' => $this->tenant->id,
            'priority' => 'high',
            'client_tier' => null,
            'is_active' => true,
            'response_time_hours' => 2,
            'resolution_time_hours' => 8,
        ]);

        $user = User::factory()->create();
        $this->tenant->addUser($user, 'owner');
        $this->actingAs($user)->withTenant($this->tenant)
            ->withSession(['current_tenant_id' => $this->tenant->id]);
    }

    private function ticket(): Ticket
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        return app(TicketService::class)->createTicket([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'subject' => 'pending', 'description' => 'pending',
            'priority' => 'high',
        ]);
    }

    public function test_pending_transitions_are_allowed_and_illegal_ones_throw(): void
    {
        $service = app(TicketService::class);
        $ticket = $this->ticket();

        $service->changeStatus($ticket, 'in_progress');
        $service->changeStatus($ticket->fresh(), 'pending');

        $this->assertSame('pending', $ticket->fresh()->status);

        $service->changeStatus($ticket->fresh(), 'closed');

        $this->expectException(InvalidStatusTransitionException::class);
        $service->changeStatus($ticket->fresh(), 'pending');
    }

    public function test_pending_pauses_the_resolution_clock(): void
    {
        $service = app(TicketService::class);
        $ticket = $this->ticket();
        $originalDue = $ticket->resolution_due_at->copy();

        $service->changeStatus($ticket, 'pending');

        $this->assertNotNull($ticket->fresh()->pending_since);

        // Rewind the pause start so a measurable period has elapsed.
        $ticket->refresh()->update(['hold_started_at' => now()->subHours(3), 'pending_since' => now()->subHours(3)]);

        $service->changeStatus($ticket->fresh(), 'in_progress');
        $ticket->refresh();

        $this->assertSame(180, $ticket->total_hold_time_minutes);
        $this->assertSame(180, $ticket->total_pending_time_minutes);
        $this->assertNull($ticket->pending_since);
        $this->assertSame(
            $originalDue->copy()->addMinutes(180)->timestamp,
            $ticket->resolution_due_at->timestamp,
            'Time waiting on the customer is not the team\'s to answer for, so the deadline moves with it'
        );
    }

    public function test_pending_tickets_are_excluded_from_the_breach_sweep(): void
    {
        $ticket = $this->ticket();

        app(TicketService::class)->changeStatus($ticket, 'pending');
        $ticket->refresh()->update([
            'response_due_at' => now()->subHour(),
            'resolution_due_at' => now()->subHour(),
        ]);

        $sla = app(SlaService::class);

        // Both predicates, because excluding it from one and not the other is the
        // classic half-fix: the scheduler and the overdue list would disagree.
        $this->assertFalse($sla->getTicketsNeedingBreachWarning()->contains('id', $ticket->id));
        $this->assertFalse($sla->getOverdueTickets()->contains('id', $ticket->id));
    }

    public function test_pending_minutes_are_not_double_subtracted(): void
    {
        $service = app(TicketService::class);

        $held = $this->ticket();
        $service->changeStatus($held, 'on_hold');
        $held->refresh()->update(['hold_started_at' => now()->subHours(4)]);
        $service->changeStatus($held->fresh(), 'in_progress');
        $held->refresh()->update(['created_at' => now()->subHours(10)]);
        $service->changeStatus($held->fresh(), 'closed');

        $waiting = $this->ticket();
        $service->changeStatus($waiting, 'pending');
        $waiting->refresh()->update(['hold_started_at' => now()->subHours(4), 'pending_since' => now()->subHours(4)]);
        $service->changeStatus($waiting->fresh(), 'in_progress');
        $waiting->refresh()->update(['created_at' => now()->subHours(10)]);
        $service->changeStatus($waiting->fresh(), 'closed');

        $held->refresh();
        $waiting->refresh();

        $this->assertSame(240, $waiting->total_pending_time_minutes);
        $this->assertSame(0, $held->total_pending_time_minutes);

        // total_pending_time_minutes is a subset of total_hold_time_minutes. If it
        // ever entered the SLA maths, the same four hours would be subtracted twice
        // and the pending ticket would look four hours faster than the held one.
        $this->assertSame(
            $held->getEffectiveResolutionTimeHours(),
            $waiting->getEffectiveResolutionTimeHours(),
            'Pending time must be subtracted exactly once, like any other hold'
        );
        $this->assertSame($held->getTotalHoldTimeMinutes(), $waiting->getTotalHoldTimeMinutes());
    }

    public function test_relabelling_a_hold_as_pending_keeps_the_elapsed_pause(): void
    {
        $service = app(TicketService::class);
        $ticket = $this->ticket();

        $service->changeStatus($ticket, 'on_hold');
        $ticket->refresh()->update(['hold_started_at' => now()->subHours(2)]);

        $service->changeStatus($ticket->fresh(), 'pending');
        $ticket->refresh();

        $this->assertSame(120, $ticket->total_hold_time_minutes,
            'Moving between two paused statuses must bank the segment, not discard it');
        $this->assertSame(0, $ticket->total_pending_time_minutes);

        $ticket->update(['hold_started_at' => now()->subHour(), 'pending_since' => now()->subHour()]);
        $service->changeStatus($ticket->fresh(), 'in_progress');
        $ticket->refresh();

        $this->assertSame(180, $ticket->total_hold_time_minutes);
        $this->assertSame(60, $ticket->total_pending_time_minutes);
    }

    public function test_pending_tickets_are_counted_as_open(): void
    {
        $ticket = $this->ticket();
        app(TicketService::class)->changeStatus($ticket, 'pending');

        $this->assertTrue(Ticket::query()->open()->where('id', $ticket->id)->exists(),
            'A ticket waiting on a customer is still live work; dropping it from the open count is a reporting bug');

        $volume = app(ReportService::class)->getTicketVolumeReport([]);

        $this->assertSame(1, $volume['open']);
        $this->assertSame(1, $volume['pending']);
        $this->assertSame(1, $volume['by_status']['pending']);
    }
}
