<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\Client;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SlaService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The system previously held two different definitions of an SLA breach: the
 * scheduler compared wall-clock against resolution_due_at and ignored hold time,
 * while the compliance report subtracted it. These tests pin the single definition.
 */
class SlaLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->create(['slug' => 'business', 'features' => PlanFeature::forPlan('business')]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $this->tenant = Tenant::factory()->create(['license_id' => $license->id]);

        $user = User::factory()->create();
        $this->tenant->addUser($user, 'owner');
        $this->actingAs($user)->withTenant($this->tenant)
            ->withSession(['current_tenant_id' => $this->tenant->id]);
    }

    private function policy(string $priority, int $response, int $resolution): SlaPolicy
    {
        return SlaPolicy::factory()->create([
            'tenant_id' => $this->tenant->id,
            'priority' => $priority,
            'client_tier' => null,
            'is_active' => true,
            'response_time_hours' => $response,
            'resolution_time_hours' => $resolution,
        ]);
    }

    private function ticket(string $priority): Ticket
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        return app(TicketService::class)->createTicket([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'subject' => 'sla', 'description' => 'sla',
            'priority' => $priority,
        ]);
    }

    public function test_raising_priority_moves_the_deadlines(): void
    {
        $this->policy('low', 48, 96);
        $this->policy('critical', 1, 4);

        $ticket = $this->ticket('low');
        $lowDue = $ticket->resolution_due_at;
        $this->assertSame(96, (int) $ticket->created_at->diffInHours($lowDue));

        app(TicketService::class)->changePriority($ticket, 'critical');
        $ticket->refresh();

        $this->assertSame(4, (int) $ticket->created_at->diffInHours($ticket->resolution_due_at),
            'A ticket triaged up to critical must be measured against the critical target');
        $this->assertTrue($ticket->resolution_due_at->lt($lowDue));
    }

    public function test_hold_time_extends_the_deadline_by_the_time_held(): void
    {
        $this->policy('high', 2, 8);
        $ticket = $this->ticket('high');
        $originalDue = $ticket->resolution_due_at->copy();

        $service = app(TicketService::class);
        $service->changeStatus($ticket, 'on_hold');

        // Rewind the hold start so a measurable period has elapsed.
        $ticket->refresh()->update(['hold_started_at' => now()->subHours(3)]);

        $service->changeStatus($ticket->fresh(), 'in_progress');
        $ticket->refresh();

        $this->assertSame(180, $ticket->total_hold_time_minutes);
        $this->assertSame(
            $originalDue->copy()->addMinutes(180)->timestamp,
            $ticket->resolution_due_at->timestamp,
            'Time on hold is not the team\'s to answer for, so the deadline moves with it'
        );
    }

    public function test_a_ticket_on_hold_is_not_swept_for_breach_warnings(): void
    {
        $this->policy('high', 1, 2);
        $ticket = $this->ticket('high');

        app(TicketService::class)->changeStatus($ticket, 'on_hold');
        $ticket->refresh()->update([
            'response_due_at' => now()->subHour(),
            'resolution_due_at' => now()->subHour(),
        ]);

        $overdue = app(SlaService::class)->getTicketsNeedingBreachWarning();

        $this->assertFalse($overdue->contains('id', $ticket->id),
            'A held ticket has a paused clock; warning on it reports time the team does not owe');
    }

    public function test_breach_scheduler_and_compliance_report_agree_on_a_held_ticket(): void
    {
        $this->policy('high', 2, 4);
        $ticket = $this->ticket('high');
        $service = app(TicketService::class);

        // Held for 10 hours, then closed 12 hours after creation: 2 working hours.
        $service->changeStatus($ticket, 'on_hold');
        $ticket->refresh()->update(['hold_started_at' => now()->subHours(10)]);
        $service->changeStatus($ticket->fresh(), 'in_progress');

        $ticket->refresh()->update(['created_at' => now()->subHours(12)]);
        $service->changeStatus($ticket->fresh(), 'closed');
        $ticket->refresh();

        // Wall-clock view: the deadline was pushed out past the closure.
        $this->assertTrue($ticket->closed_at->lte($ticket->resolution_due_at),
            'Scheduler view: not breached');

        // Report view: effective hours exclude the hold and sit under the target.
        $this->assertLessThanOrEqual(
            $ticket->slaPolicy->resolution_time_hours,
            $ticket->getEffectiveResolutionTimeHours(),
            'Report view: also not breached — the two definitions must not disagree'
        );
    }
}
