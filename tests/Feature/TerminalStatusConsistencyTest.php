<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

/**
 * Two branches independently asked "is this ticket finished?" — one through
 * Ticket::CLOSED_STATUSES, the other through a copied ['closed','cancelled']
 * literal — and merged into a file holding both. Nothing failed, because the
 * two agreed on the day they merged.
 *
 * They only have to disagree once. Adding a terminal status updates the
 * constant and silently leaves every literal behind, so the overdue checks and
 * the hold-time guard would start answering differently about the same ticket.
 * These tests pin the agreement rather than the answer.
 */
class TerminalStatusConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_terminal_status_literal_survives_in_the_model(): void
    {
        $source = file_get_contents((new ReflectionClass(Ticket::class))->getFileName());

        // The declaration itself is the one legitimate occurrence.
        $declaration = "public const CLOSED_STATUSES = ['closed', 'cancelled'];";
        $body = str_replace($declaration, '', $source);

        $this->assertDoesNotMatchRegularExpression(
            "/\[\s*'closed'\s*,\s*'cancelled'\s*\]/",
            $body,
            'A copied terminal-status literal is back in Ticket. Use Ticket::CLOSED_STATUSES, '
            .'or the next status added will be honoured in some checks and ignored in others.'
        );
    }

    public function test_the_terminal_set_is_exactly_the_statuses_with_no_way_forward(): void
    {
        // CLOSED_STATUSES must mean "terminal", not "happens to be these two".
        // A status is terminal when the only moves out of it are reopens.
        $terminal = collect(Ticket::STATUSES)
            ->filter(fn (string $s) => ! in_array($s, Ticket::OPEN_STATUSES, true)
                && ! in_array($s, Ticket::PAUSED_STATUSES, true))
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing(
            Ticket::CLOSED_STATUSES,
            $terminal,
            'CLOSED_STATUSES has drifted from the statuses that are neither open nor paused.'
        );
    }

    public function test_the_overdue_checks_and_the_hold_guard_agree_on_every_terminal_status(): void
    {
        foreach (Ticket::CLOSED_STATUSES as $status) {
            $ticket = new Ticket([
                'status' => $status,
                'total_hold_time_minutes' => 60,
            ]);
            $ticket->resolution_due_at = now()->subDay();
            $ticket->response_due_at = now()->subDay();
            $ticket->hold_started_at = now()->subDay();
            $ticket->first_response_at = null;

            $this->assertFalse($ticket->isOverdue(), "isOverdue() should be false for '{$status}'.");
            $this->assertFalse($ticket->isResponseOverdue(), "isResponseOverdue() should be false for '{$status}'.");
            $this->assertSame(
                60,
                $ticket->getTotalHoldTimeMinutes(),
                "A '{$status}' ticket must not accrue more hold time; its resolution figure is history."
            );
        }
    }

    public function test_a_live_ticket_still_accrues_hold_and_can_still_be_overdue(): void
    {
        // The control: the guard must not have switched the behaviour off wholesale.
        $ticket = new Ticket([
            'status' => 'on_hold',
            'total_hold_time_minutes' => 60,
        ]);
        $ticket->resolution_due_at = now()->subDay();
        $ticket->hold_started_at = now()->subMinutes(30);

        $this->assertTrue($ticket->isOverdue());
        $this->assertSame(90, $ticket->getTotalHoldTimeMinutes());
    }
}
