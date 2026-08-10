<?php

namespace App\Assistant;

use App\Assistant\Concerns\AuthorizesTenantUser;
use App\Models\Ticket;
use Cliqueha\AssistantConnector\AssistantTool;

class FindTickets extends AssistantTool
{
    use AuthorizesTenantUser;

    public function name(): string
    {
        return 'find_tickets';
    }

    public function description(): string
    {
        return 'Search support tickets in this workspace, optionally by status, priority, or a keyword in the subject.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => [
            'status' => ['type' => 'string', 'enum' => ['open', 'assigned', 'in_progress', 'on_hold', 'closed', 'cancelled']],
            'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical']],
            'search' => ['type' => 'string', 'description' => 'Keyword to match in the ticket subject.'],
            'limit' => ['type' => 'integer', 'description' => 'Max tickets to return (default 10).'],
        ]];
    }

    public function handle(array $input, mixed $user): array
    {
        if ($denied = $this->denyUnless($user, 'view tickets')) {
            return $denied;
        }

        $limit = min((int) ($input['limit'] ?? 10), 25);

        $tickets = Ticket::with('client', 'assignee')
            ->when($input['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($input['priority'] ?? null, fn ($q, $p) => $q->where('priority', $p))
            ->when($input['search'] ?? null, fn ($q, $t) => $q->where('subject', 'like', "%{$t}%"))
            ->latest()
            ->limit($limit)
            ->get();

        return [
            'count' => $tickets->count(),
            'tickets' => $tickets->map(fn (Ticket $t) => [
                'number' => $t->ticket_number,
                'subject' => $t->subject,
                'status' => $t->status,
                'priority' => $t->priority,
                'client' => $t->client->name ?? null,
                'assignee' => $t->assignee->name ?? null,
                'created' => $t->created_at?->toDateString(),
            ])->all(),
        ];
    }
}
