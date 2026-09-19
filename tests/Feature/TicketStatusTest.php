<?php

namespace Tests\Feature;

use App\Models\Ticket;
use Tests\TestCase;

/**
 * The status list used to be spelled out at every call site. These tests pin the
 * consolidated constants together, so a status added to TRANSITIONS but forgotten
 * in STATUSES — which is how a status silently disappears from half the reports —
 * fails the build instead of shipping.
 */
class TicketStatusTest extends TestCase
{
    public function test_status_constants_cover_every_transition_key(): void
    {
        foreach (array_keys(Ticket::TRANSITIONS) as $status) {
            $this->assertContains($status, Ticket::STATUSES, "'{$status}' is a transition key but missing from Ticket::STATUSES.");
        }

        foreach (Ticket::STATUSES as $status) {
            $this->assertArrayHasKey($status, Ticket::TRANSITIONS, "'{$status}' is in Ticket::STATUSES but has no transition map entry.");
        }
    }

    public function test_every_transition_target_is_a_known_status(): void
    {
        foreach (Ticket::TRANSITIONS as $from => $targets) {
            foreach ($targets as $target) {
                $this->assertContains($target, Ticket::STATUSES, "'{$from}' can move to unknown status '{$target}'.");
            }
        }
    }

    public function test_open_and_paused_statuses_are_subsets_of_statuses(): void
    {
        $this->assertEmpty(array_diff(Ticket::OPEN_STATUSES, Ticket::STATUSES));
        $this->assertEmpty(array_diff(Ticket::PAUSED_STATUSES, Ticket::STATUSES));
        $this->assertEmpty(array_diff(Ticket::CLOSED_STATUSES, Ticket::STATUSES));

        // A paused ticket is still live work; if it were not in the open set it
        // would vanish from every open count while the customer waits.
        $this->assertEmpty(array_diff(Ticket::PAUSED_STATUSES, Ticket::OPEN_STATUSES));

        $this->assertEqualsCanonicalizing(
            Ticket::STATUSES,
            array_merge(Ticket::OPEN_STATUSES, Ticket::CLOSED_STATUSES),
        );
    }
}
