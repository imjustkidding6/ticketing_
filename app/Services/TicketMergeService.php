<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

class TicketMergeService
{
    public function __construct(
        private TicketService $ticketService,
    ) {}

    /**
     * Merge a source ticket into a target ticket.
     */
    public function merge(Ticket $source, Ticket $target): void
    {
        DB::transaction(function () use ($source, $target) {
            // Move tasks from source to target
            $source->tasks()->update(['ticket_id' => $target->id]);

            // Move comments from source to target
            $source->comments()->update(['ticket_id' => $target->id]);

            // Mark source as merged
            $source->update([
                'is_merged' => true,
                'merged_into_ticket_id' => $target->id,
                'merged_at' => now(),
                'status' => 'closed',
                'closed_at' => now(),
            ]);

            // Add history entries
            $this->ticketService->addHistory(
                $source,
                'merged',
                description: "Merged into ticket {$target->ticket_number}."
            );

            $this->ticketService->addHistory(
                $target,
                'merged',
                description: "Ticket {$source->ticket_number} was merged into this ticket."
            );
        });
    }

    /**
     * Reverse a merge (unmerge a ticket).
     */
    public function unmerge(Ticket $source): void
    {
        if (! $source->is_merged || ! $source->merged_into_ticket_id) {
            return;
        }

        $source->update([
            'is_merged' => false,
            'merged_into_ticket_id' => null,
            'merged_at' => null,
            'status' => 'open',
            'closed_at' => null,
        ]);

        $this->ticketService->addHistory(
            $source,
            'unmerged',
            description: 'Ticket was unmerged and reopened.'
        );
    }
}
