<?php

namespace App\Assistant;

use App\Models\Ticket;
use Cliqueha\AssistantConnector\AssistantTool;

class UpdateStatus extends AssistantTool
{
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
        $ticket = Ticket::where('ticket_number', $input['ticket_number'] ?? '')->first();

        if (! $ticket) {
            return ['error' => 'No ticket found with that number.'];
        }

        try {
            $ticket->update(['status' => $input['status']]);

            return ['status' => 'updated', 'ticket_number' => $ticket->ticket_number, 'new_status' => $ticket->status];
        } catch (\Throwable $e) {
            return ['error' => 'Could not update the ticket: '.$e->getMessage()];
        }
    }
}
