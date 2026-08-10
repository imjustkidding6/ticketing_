<?php

namespace App\Assistant;

use App\Assistant\Concerns\AuthorizesTenantUser;
use App\Models\Ticket;
use Cliqueha\AssistantConnector\AssistantTool;

class UpdateStatus extends AssistantTool
{
    use AuthorizesTenantUser;

    /** Statuses that count as closing a ticket. */
    private const CLOSED_STATUSES = ['closed', 'cancelled'];

    public function name(): string
    {
        return 'update_status';
    }

    public function description(): string
    {
        return 'Change a ticket\'s status.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => [
            'ticket_number' => ['type' => 'string'],
            'status' => ['type' => 'string', 'enum' => ['open', 'assigned', 'in_progress', 'on_hold', 'closed', 'cancelled']],
        ], 'required' => ['ticket_number', 'status']];
    }

    public function writes(): bool
    {
        return true;
    }

    public function handle(array $input, mixed $user): array
    {
        if ($denied = $this->denyUnless($user, 'update tickets')) {
            return $denied;
        }

        $ticket = Ticket::where('ticket_number', $input['ticket_number'] ?? '')->first();

        if (! $ticket) {
            return ['error' => 'No ticket found with that number.'];
        }

        $target = $input['status'];
        $wasClosed = in_array($ticket->status, self::CLOSED_STATUSES, true);

        // Closing and reopening carry their own permissions in the UI.
        if (in_array($target, self::CLOSED_STATUSES, true) && ! $wasClosed) {
            if ($denied = $this->denyUnless($user, 'close tickets')) {
                return $denied;
            }
        }

        if ($wasClosed && ! in_array($target, self::CLOSED_STATUSES, true)) {
            if ($denied = $this->denyUnless($user, 'reopen tickets')) {
                return $denied;
            }
        }

        try {
            $ticket->update(['status' => $input['status']]);

            return ['status' => 'updated', 'ticket_number' => $ticket->ticket_number, 'new_status' => $ticket->status];
        } catch (\Throwable $e) {
            return ['error' => 'Could not update the ticket: '.$e->getMessage()];
        }
    }
}
