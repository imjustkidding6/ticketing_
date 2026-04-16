<?php

namespace App\Services;

use App\Models\ChatbotSession;
use App\Models\Client;
use App\Models\Department;
use App\Models\Tenant;
use App\Models\Ticket;
use Illuminate\Support\Str;

class ChatbotEscalationService
{
    public function __construct(
        private TicketService $ticketService,
    ) {}

    /**
     * @param  array{subject: string, description: string, priority: string, department: string|null}  $draft
     */
    public function createTicket(Tenant $tenant, ChatbotSession $session, array $draft): Ticket
    {
        $client = $this->findOrCreateClient($tenant, $session);
        $departmentId = $this->resolveDepartmentId($tenant, $draft['department'] ?? null);
        $creatorId = $tenant->owners()->first()?->id ?? $tenant->users()->first()?->id;

        if (! $creatorId) {
            throw new \RuntimeException('No tenant user available for ticket creation.');
        }

        $ticket = $this->ticketService->createTicket([
            'tenant_id' => $tenant->id,
            'subject' => $draft['subject'],
            'description' => $draft['description'],
            'priority' => $this->normalizePriority($draft['priority']),
            'department_id' => $departmentId,
            'client_id' => $client->id,
            'created_by' => $creatorId,
        ]);

        $ticket->tracking_token = Str::random(64);
        $ticket->save();

        return $ticket;
    }

    private function findOrCreateClient(Tenant $tenant, ChatbotSession $session): Client
    {
        $email = $session->contact_email;
        $name = $session->contact_name ?? 'Guest';

        $client = Client::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->first();

        if ($client) {
            return $client;
        }

        return Client::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'email' => $email,
            'phone' => $session->contact_phone,
            'status' => Client::STATUS_ACTIVE,
            'tier' => Client::TIER_BASIC,
        ]);
    }

    private function resolveDepartmentId(Tenant $tenant, ?string $departmentName): ?int
    {
        if ($departmentName) {
            $department = Department::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('name', $departmentName)
                ->first();

            if ($department) {
                return $department->id;
            }
        }

        return Department::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->value('id');
    }

    private function normalizePriority(string $priority): string
    {
        return match ($priority) {
            'low', 'medium', 'high', 'critical' => $priority,
            default => 'medium',
        };
    }
}
