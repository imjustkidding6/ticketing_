<?php

namespace Tests\Unit;

use App\Models\Ticket;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The projected deadlines are display-only: they answer "where will this deadline
 * stand once the running hold is banked?" so the SLA panel never prints a number
 * the system is about to contradict. They must apply exactly the arithmetic
 * SlaService::extendForHold() applies, and they must never write anything.
 */
class TicketProjectedDueTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function ticket(array $attributes = []): Ticket
    {
        $ticket = new Ticket;
        $ticket->forceFill(array_merge([
            'status' => 'open',
            'response_due_at' => Carbon::parse('2026-09-21 12:00:00'),
            'resolution_due_at' => Carbon::parse('2026-09-22 12:00:00'),
            'first_response_at' => null,
            'hold_started_at' => null,
            'total_hold_time_minutes' => 0,
        ], $attributes));

        // Sync originals so isDirty() below means "the projection wrote to the model",
        // rather than "this instance was hydrated by hand".
        $ticket->syncOriginal();

        return $ticket;
    }

    public function test_an_unpaused_ticket_projects_the_stored_deadline_unchanged(): void
    {
        Carbon::setTestNow('2026-09-21 09:00:00');

        $ticket = $this->ticket();

        $this->assertTrue($ticket->projectedResponseDueAt()->eq($ticket->response_due_at));
        $this->assertTrue($ticket->projectedResolutionDueAt()->eq($ticket->resolution_due_at));
    }

    public function test_a_paused_ticket_projects_the_deadline_forward_by_the_running_hold(): void
    {
        Carbon::setTestNow('2026-09-21 09:00:00');

        $ticket = $this->ticket([
            'status' => 'on_hold',
            'hold_started_at' => Carbon::parse('2026-09-21 06:00:00'),
        ]);

        $this->assertTrue(
            $ticket->projectedResolutionDueAt()->eq(Carbon::parse('2026-09-22 15:00:00')),
            'Three hours on hold must push the projection out by three hours.'
        );
        $this->assertTrue($ticket->projectedResponseDueAt()->eq(Carbon::parse('2026-09-21 15:00:00')));
    }

    /**
     * The degenerate state: paused with no hold_started_at. Older rows have it, and
     * the Jude connector's UpdateStatus tool writes status directly without ever
     * setting it. Projecting from nothing would invent a deadline.
     */
    public function test_paused_without_a_hold_start_projects_nothing_new(): void
    {
        Carbon::setTestNow('2026-09-21 09:00:00');

        $ticket = $this->ticket(['status' => 'on_hold', 'hold_started_at' => null]);

        $this->assertTrue($ticket->projectedResolutionDueAt()->eq($ticket->resolution_due_at));
    }

    /**
     * extendForHold() leaves response_due_at alone once the first response has landed —
     * the response clock has already stopped. The projection must agree, or the panel
     * shows a response deadline moving after it no longer can.
     */
    public function test_an_answered_response_clock_is_not_projected_forward(): void
    {
        Carbon::setTestNow('2026-09-21 09:00:00');

        $ticket = $this->ticket([
            'status' => 'on_hold',
            'hold_started_at' => Carbon::parse('2026-09-21 06:00:00'),
            'first_response_at' => Carbon::parse('2026-09-21 05:00:00'),
        ]);

        $this->assertTrue($ticket->projectedResponseDueAt()->eq($ticket->response_due_at));
        // The resolution clock is still running and still projects.
        $this->assertTrue($ticket->projectedResolutionDueAt()->eq(Carbon::parse('2026-09-22 15:00:00')));
    }

    /**
     * getTotalHoldTimeMinutes() deliberately ignores a running segment on a terminal
     * ticket so a historical figure stops moving; the projection inherits that.
     */
    public function test_a_closed_ticket_does_not_project(): void
    {
        Carbon::setTestNow('2026-09-21 09:00:00');

        $ticket = $this->ticket([
            'status' => 'closed',
            'hold_started_at' => Carbon::parse('2026-09-21 06:00:00'),
        ]);

        $this->assertTrue($ticket->projectedResolutionDueAt()->eq($ticket->resolution_due_at));
    }

    public function test_a_null_deadline_projects_null(): void
    {
        Carbon::setTestNow('2026-09-21 09:00:00');

        $ticket = $this->ticket([
            'status' => 'on_hold',
            'hold_started_at' => Carbon::parse('2026-09-21 06:00:00'),
            'response_due_at' => null,
            'resolution_due_at' => null,
        ]);

        $this->assertNull($ticket->projectedResponseDueAt());
        $this->assertNull($ticket->projectedResolutionDueAt());
    }

    public function test_the_projection_does_not_mutate_the_stored_value(): void
    {
        Carbon::setTestNow('2026-09-21 09:00:00');

        $ticket = $this->ticket([
            'status' => 'on_hold',
            'hold_started_at' => Carbon::parse('2026-09-21 06:00:00'),
        ]);

        $stored = $ticket->resolution_due_at->copy();
        $ticket->projectedResolutionDueAt();

        $this->assertTrue($ticket->resolution_due_at->eq($stored));
        $this->assertFalse($ticket->isDirty());
    }
}
