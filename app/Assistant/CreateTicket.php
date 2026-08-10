<?php

namespace App\Assistant;

use App\Assistant\Concerns\AuthorizesTenantUser;
use App\Models\Client;
use App\Models\Ticket;
use Cliqueha\AssistantConnector\AssistantTool;

class CreateTicket extends AssistantTool
{
    use AuthorizesTenantUser;

    public function name(): string
    {
        return 'create_ticket';
    }

    public function description(): string
    {
        return 'Open a new support ticket for a client. The ticket number is generated automatically.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => [
            'client' => ['type' => 'string', 'description' => 'Client (customer) name — use list_clients if unsure.'],
            'subject' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical']],
        ], 'required' => ['client', 'subject', 'description']];
    }

    public function writes(): bool
    {
        return true;
    }

    public function handle(array $input, mixed $user): array
    {
        if ($denied = $this->denyUnless($user, 'create tickets')) {
            return $denied;
        }

        $client = Client::where('name', 'like', '%'.($input['client'] ?? '').'%')->first();

        if (! $client) {
            // Only suggest names to users who are allowed to browse clients —
            // otherwise this branch would leak the client list past list_clients.
            if (! $this->userMay($user, 'view clients')) {
                return ['error' => 'No matching client.'];
            }

            $available = Client::orderBy('name')->limit(10)->pluck('name')->implode(', ');

            return ['error' => 'No matching client. Available: '.($available ?: 'none').'.'];
        }

        try {
            $ticket = Ticket::create([
                'client_id' => $client->id,
                'subject' => $input['subject'],
                'description' => $input['description'],
                'priority' => $input['priority'] ?? 'medium',
                'created_by' => $user->getKey(),
                // tenant_id, status ('open') and ticket_number are set automatically.
            ]);

            return ['status' => 'created', 'ticket_number' => $ticket->ticket_number, 'client' => $client->name, 'subject' => $ticket->subject];
        } catch (\Throwable $e) {
            return ['error' => 'Could not create the ticket: '.$e->getMessage()];
        }
    }
}
