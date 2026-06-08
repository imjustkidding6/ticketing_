<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Support\Str;

/**
 * Turns the URL path the agent is currently viewing into a short, human-readable
 * description (plus key entity details for ticket/client pages) so the AI assistant
 * can answer "help me with this ticket / this page" without being told the specifics.
 *
 * Entity lookups rely on the tenant global scope (the session tenant is already set
 * on assistant requests), so nothing leaks across tenants.
 */
class PageContextResolver
{
    /** First-segment → friendly page label for pages without a rich entity. */
    private const LABELS = [
        'dashboard' => 'the Dashboard (overview of tickets and metrics).',
        'tickets' => 'the Tickets list page.',
        'clients' => 'the Clients list page.',
        'departments' => 'the Departments management page.',
        'categories' => 'the Ticket Categories page.',
        'products' => 'the Products & Services page.',
        'sla' => 'the SLA Policies page.',
        'reports' => 'the Reports & Analytics section.',
        'settings' => 'the Settings area.',
        'tutorials' => 'the Help & Tutorials page.',
        'knowledge-base' => 'the Knowledge Base management page.',
        'users' => 'the User Management page.',
        'profile' => 'their Profile page.',
        'agents' => 'the Agents page.',
    ];

    public function describe(Tenant $tenant, ?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = parse_url($path, PHP_URL_PATH) ?: $path;
        $segments = array_values(array_filter(explode('/', trim($path, '/')), fn ($s) => $s !== ''));

        // Drop the leading tenant slug.
        if (($segments[0] ?? null) === $tenant->slug) {
            array_shift($segments);
        }

        if ($segments === []) {
            return 'the home / dashboard page.';
        }

        $resource = $segments[0];
        $id = $segments[1] ?? null;

        if ($resource === 'tickets' && $id === 'create') {
            return 'the "Create Ticket" page — a form for opening a new support ticket.';
        }
        if ($resource === 'tickets' && is_numeric($id)) {
            return $this->ticketContext((int) $id);
        }
        if ($resource === 'clients' && is_numeric($id)) {
            return $this->clientContext((int) $id);
        }

        return self::LABELS[$resource] ?? ('the '.str_replace('-', ' ', $resource).' page.');
    }

    private function ticketContext(int $id): string
    {
        $ticket = Ticket::find($id); // tenant-scoped by the global scope

        if (! $ticket) {
            return 'a ticket detail page, but that ticket could not be found in this workspace.';
        }

        $recent = $ticket->comments()
            ->where('is_public', true)
            ->latest('id')
            ->limit(3)
            ->get()
            ->reverse()
            ->map(fn (TicketComment $c) => ($c->user_id ? 'Agent: ' : 'Customer: ').Str::limit((string) $c->content, 300))
            ->implode("\n");

        return "the detail page for ticket {$ticket->ticket_number} — \"{$ticket->subject}\".\n"
            ."Status: {$ticket->status}; Priority: {$ticket->priority}.\n"
            .'Description: '.Str::limit((string) $ticket->description, 700)."\n"
            .($recent !== '' ? "Most recent updates:\n".$recent : 'No public replies yet.');
    }

    private function clientContext(int $id): string
    {
        $client = Client::find($id);

        if (! $client) {
            return 'a client detail page, but that client could not be found in this workspace.';
        }

        $open = $client->tickets()->whereNotIn('status', ['closed', 'cancelled'])->count();

        return "the detail page for the client \"{$client->name}\" (tier: ".($client->tier ?: 'n/a').").\n"
            ."They currently have {$open} open ticket(s).";
    }
}
