<?php

namespace App\Assistant;

use App\Models\Ticket;
use App\Models\TicketComment;
use Cliqueha\AssistantConnector\AssistantTool;

class AddComment extends AssistantTool
{
    public function name(): string
    {
        return 'add_comment';
    }

    public function description(): string
    {
        return 'Add a public comment/reply to a ticket.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => [
            'ticket_number' => ['type' => 'string'],
            'content' => ['type' => 'string', 'description' => 'The comment text.'],
        ], 'required' => ['ticket_number', 'content']];
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
            TicketComment::create([
                'tenant_id' => $ticket->tenant_id,
                'ticket_id' => $ticket->id,
                'user_id' => $user->getKey(),
                'content' => $input['content'],
                'type' => 'public',
                'is_public' => true,
            ]);

            return ['status' => 'comment_added', 'ticket_number' => $ticket->ticket_number];
        } catch (\Throwable $e) {
            return ['error' => 'Could not add the comment: '.$e->getMessage()];
        }
    }
}
