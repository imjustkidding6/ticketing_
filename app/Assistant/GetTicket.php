<?php

namespace App\Assistant;

use App\Assistant\Concerns\AuthorizesTenantUser;
use App\Models\Ticket;
use Cliqueha\AssistantConnector\AssistantTool;

class GetTicket extends AssistantTool
{
    use AuthorizesTenantUser;

    public function name(): string
    {
        return 'get_ticket';
    }

    public function description(): string
    {
        return 'Get the full details of one ticket by its ticket number, including recent comments.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => [
            'ticket_number' => ['type' => 'string', 'description' => 'The ticket number, e.g. TKT-...'],
        ], 'required' => ['ticket_number']];
    }

    public function handle(array $input, mixed $user): array
    {
        if ($denied = $this->denyUnless($user, 'view tickets')) {
            return $denied;
        }

        $ticket = Ticket::with('client', 'assignee', 'category', 'comments.user')
            ->where('ticket_number', $input['ticket_number'] ?? '')
            ->first();

        if (! $ticket) {
            return ['error' => 'No ticket found with that number.'];
        }

        return [
            'number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'description' => $ticket->description,
            'client' => $ticket->client->name ?? null,
            'assignee' => $ticket->assignee->name ?? null,
            'category' => $ticket->category->name ?? null,
            'created' => $ticket->created_at?->toDateTimeString(),
            'comments' => $ticket->comments->sortByDesc('created_at')->take(5)->map(fn ($c) => [
                'by' => $c->user->name ?? null,
                'type' => $c->type,
                'content' => $c->content,
                'at' => $c->created_at?->toDateTimeString(),
            ])->values()->all(),
        ];
    }
}
