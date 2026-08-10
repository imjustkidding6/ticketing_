<?php

namespace App\Assistant;

use App\Assistant\Concerns\AuthorizesTenantUser;
use App\Models\Ticket;
use Cliqueha\AssistantConnector\AssistantTool;

class TicketSummary extends AssistantTool
{
    use AuthorizesTenantUser;

    public function name(): string
    {
        return 'ticket_summary';
    }

    public function description(): string
    {
        return 'Counts of tickets by status in this workspace (open vs closed totals).';
    }

    public function handle(array $input, mixed $user): array
    {
        if ($denied = $this->denyUnless($user, 'view tickets')) {
            return $denied;
        }

        $byStatus = Ticket::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        $open = collect(['open', 'assigned', 'in_progress', 'on_hold'])->sum(fn ($s) => (int) $byStatus->get($s, 0));
        $closed = collect(['closed', 'cancelled'])->sum(fn ($s) => (int) $byStatus->get($s, 0));

        return [
            'by_status' => $byStatus->all(),
            'open_total' => $open,
            'closed_total' => $closed,
        ];
    }
}
