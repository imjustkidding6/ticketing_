<?php

namespace App\Services;

use App\Exceptions\InvalidMergeException;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

class TicketMergeService
{
    public function __construct(
        private TicketService $ticketService,
    ) {}

    /**
     * Merge a source ticket into a target ticket.
     *
     * Tasks and comments move to the target, so what moved is recorded on the
     * source — otherwise unmerge has no way to put anything back.
     *
     * @throws InvalidMergeException
     */
    public function merge(Ticket $source, Ticket $target): void
    {
        $this->guardMerge($source, $target);

        DB::transaction(function () use ($source, $target) {
            // Capture what we are about to move, and the state we are about to
            // overwrite, so the merge is reversible.
            $movedTaskIds = $source->tasks()->pluck('id')->all();
            $movedCommentIds = $source->comments()->pluck('id')->all();

            $source->tasks()->update(['ticket_id' => $target->id]);
            $source->comments()->update(['ticket_id' => $target->id]);

            $source->update([
                'is_merged' => true,
                'merged_into_ticket_id' => $target->id,
                'merged_at' => now(),
                'status' => 'closed',
                'closed_at' => now(),
                'merge_metadata' => [
                    'previous_status' => $source->status,
                    'previous_closed_at' => $source->closed_at?->toIso8601String(),
                    'moved_task_ids' => $movedTaskIds,
                    'moved_comment_ids' => $movedCommentIds,
                ],
            ]);

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
     * Reverse a merge, restoring the ticket to the state it was in beforehand.
     */
    public function unmerge(Ticket $source): void
    {
        if (! $source->is_merged || ! $source->merged_into_ticket_id) {
            return;
        }

        DB::transaction(function () use ($source) {
            $metadata = $source->merge_metadata ?? [];

            // Move back exactly the rows this merge moved — not everything now on
            // the target, which may include the target's own comments and tasks.
            if (! empty($metadata['moved_task_ids'])) {
                $source->tasks()->getModel()->newQuery()
                    ->whereIn('id', $metadata['moved_task_ids'])
                    ->update(['ticket_id' => $source->id]);
            }

            if (! empty($metadata['moved_comment_ids'])) {
                $source->comments()->getModel()->newQuery()
                    ->whereIn('id', $metadata['moved_comment_ids'])
                    ->update(['ticket_id' => $source->id]);
            }

            // Restore the pre-merge status. Merges predating this metadata have
            // nothing recorded, so fall back to the historic behaviour of opening.
            $previousStatus = $metadata['previous_status'] ?? 'open';
            $previousClosedAt = $metadata['previous_closed_at'] ?? null;

            $source->update([
                'is_merged' => false,
                'merged_into_ticket_id' => null,
                'merged_at' => null,
                'merge_metadata' => null,
                'status' => $previousStatus,
                'closed_at' => $previousClosedAt,
            ]);

            $this->ticketService->addHistory(
                $source,
                'unmerged',
                description: 'Ticket was unmerged and restored to '.str_replace('_', ' ', $previousStatus).'.'
            );
        });
    }

    /**
     * Reject merges that would corrupt the parent/child structure.
     *
     * @throws InvalidMergeException
     */
    private function guardMerge(Ticket $source, Ticket $target): void
    {
        if ($source->id === $target->id) {
            throw new InvalidMergeException('A ticket cannot be merged into itself.');
        }

        if ($source->is_merged) {
            throw new InvalidMergeException(
                "Ticket {$source->ticket_number} has already been merged. Unmerge it first."
            );
        }

        if ($target->is_merged) {
            throw new InvalidMergeException(
                "Ticket {$target->ticket_number} has itself been merged into another ticket. ".
                'Merge into the ticket at the top of the chain instead.'
            );
        }

        if ($target->merged_into_ticket_id === $source->id) {
            throw new InvalidMergeException('These two tickets are already merged the other way round.');
        }
    }
}
