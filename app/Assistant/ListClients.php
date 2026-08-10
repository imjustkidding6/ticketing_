<?php

namespace App\Assistant;

use App\Assistant\Concerns\AuthorizesTenantUser;
use App\Models\Client;
use Cliqueha\AssistantConnector\AssistantTool;

class ListClients extends AssistantTool
{
    use AuthorizesTenantUser;

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
        if ($denied = $this->denyUnless($user, 'view clients')) {
            return $denied;
        }

        return ['clients' => Client::orderBy('name')->limit(50)->pluck('name')->all()];
    }
}
