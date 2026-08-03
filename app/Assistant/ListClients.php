<?php

namespace App\Assistant;

use App\Models\Client;
use Cliqueha\AssistantConnector\AssistantTool;

class ListClients extends AssistantTool
{
    public function name(): string
    {
        return 'list_clients';
    }

    public function description(): string
    {
        return 'List the clients (customers) in this workspace — useful before creating a ticket.';
    }

    public function handle(array $input, mixed $user): array
    {
        return ['clients' => Client::orderBy('name')->limit(50)->pluck('name')->all()];
    }
}
