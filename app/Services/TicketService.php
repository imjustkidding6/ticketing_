<?php

namespace App\Services;

use App\Enums\PlanFeature;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketHistory;
use App\Models\User;
use App\Notifications\ClientTicketAssignedNotification;
use App\Notifications\ClientTicketCreatedNotification;
use App\Notifications\ClientTicketStatusChangedNotification;
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketStatusChangedNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class TicketService
{
    public function __construct(
        private SlaService $slaService,
        private PlanService $planService,
    ) {}

    /**
     * Create a new ticket.
     *
     * @param  array<string, mixed>  $data
     */
    public function createTicket(array $data): Ticket
    {
        $productIds = $data['product_ids'] ?? [];
        unset($data['product_ids']);

        $tasks = $data['tasks'] ?? [];
        unset($data['tasks']);

        $data['ticket_number'] = Ticket::generateTicketNumber();
        $data['created_by'] = $data['created_by'] ?? (Auth::check() ? Auth::id() : null);
        $data['status'] = 'open';

        $ticket = Ticket::create($data);

        if (! empty($productIds)) {
            $ticket->products()->sync($productIds);
        }

        foreach ($tasks as $index => $taskDescription) {
            if (! empty(trim($taskDescription))) {
                $ticket->tasks()->create([
                    'description' => trim($taskDescription),
                    'sort_order' => $index,
                ]);
            }
        }

        $this->slaService->assignSla($ticket);

        $this->addHistory($ticket, 'created', description: 'Ticket created.');

        if ($this->notificationsEnabled()) {
            $ticketId = $ticket->id;
            $tenantId = $ticket->tenant_id;
            $creatorId = Auth::id();

            $this->afterResponse(function () use ($ticketId, $tenantId, $creatorId) {
                TenantMailService::configure($tenantId);
                $ticket = \App\Models\Ticket::with(['client', 'department'])->find($ticketId);

                if (! $ticket) {
                    return;
                }

                // Notify client (client-friendly template)
                $client = $ticket->client;
                if ($client?->user_id) {
                    $client->user->notify(new ClientTicketCreatedNotification($ticket));
                } elseif ($client?->email) {
                    Notification::route('mail', $client->email)
                        ->notify(new ClientTicketCreatedNotification($ticket));
                }

                // Notify department members (internal template)
                if ($ticket->department_id) {
                    $deptUsers = \App\Models\User::query()
                        ->whereHas('departments', fn ($q) => $q->where('departments.id', $ticket->department_id))
                        ->whereHas('tenants', fn ($q) => $q->where('tenant_id', $tenantId))
                        ->where('id', '!=', $creatorId)
                        ->get();

                    foreach ($deptUsers as $deptUser) {
                        $deptUser->notify(new TicketCreatedNotification($ticket));
                    }
                }

                // Notify admin email
                $this->notifyAdminEmail($tenantId, new TicketCreatedNotification($ticket));
            });
        }

        return $ticket;
    }

    /**
     * Update a ticket.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateTicket(Ticket $ticket, array $data): Ticket
    {
        $productIds = $data['product_ids'] ?? null;
        unset($data['product_ids']);

        $tracked = ['subject', 'priority', 'status', 'department_id', 'category_id', 'client_id'];

        foreach ($tracked as $field) {
            if (array_key_exists($field, $data) && $ticket->getAttribute($field) != $data[$field]) {
                $this->addHistory($ticket, 'updated', $field, (string) $ticket->getAttribute($field), (string) $data[$field]);
            }
        }

        $ticket->update($data);

        if ($productIds !== null) {
            $ticket->products()->sync($productIds);
        }

        return $ticket->fresh();
    }

    /**
     * Assign a ticket to an agent.
     */
    public function assignTicket(Ticket $ticket, User $agent, ?User $assignedBy = null): void
    {
        $oldAssignee = $ticket->assigned_to;

        $ticket->update([
            'assigned_to' => $agent->id,
            'status' => $ticket->status === 'open' ? 'assigned' : $ticket->status,
        ]);

        TicketAssignment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'assigned_by' => $assignedBy?->id ?? Auth::id(),
        ]);

        $this->addHistory($ticket, 'assigned', 'assigned_to', (string) $oldAssignee, (string) $agent->id, 'Assigned to '.$agent->name);

        if ($this->notificationsEnabled()) {
            $ticketId = $ticket->id;
            $tenantId = $ticket->tenant_id;
            $agentId = $agent->id;
            $currentUserId = Auth::id();

            $this->afterResponse(function () use ($ticketId, $tenantId, $agentId) {
                TenantMailService::configure($tenantId);
                $ticket = \App\Models\Ticket::with('client')->find($ticketId);
                $agent = \App\Models\User::find($agentId);

                if (! $ticket || ! $agent) {
                    return;
                }

                // Notify the assigned agent
                $agent->notify(new TicketAssignedNotification($ticket));

                // Notify client (client-friendly template)
                $client = $ticket->client;
                if ($client?->user_id && $client->user_id !== $agentId) {
                    $client->user->notify(new ClientTicketAssignedNotification($ticket));
                } elseif ($client?->email) {
                    Notification::route('mail', $client->email)
                        ->notify(new ClientTicketAssignedNotification($ticket));
                }

                // Notify admin email
                $this->notifyAdminEmail($tenantId, new TicketAssignedNotification($ticket));
            });
        }
    }

    /**
     * Change ticket status.
     */
    public function changeStatus(Ticket $ticket, string $status): void
    {
        $oldStatus = $ticket->status;
        $updates = ['status' => $status];

        if ($status === 'in_progress' && ! $ticket->in_progress_at) {
            $updates['in_progress_at'] = now();
        }

        if ($status === 'closed') {
            $tasks = $ticket->tasks;

            if ($tasks->isEmpty()) {
                throw new \InvalidArgumentException('Cannot close ticket: no tasks have been created.');
            }

            $incomplete = $tasks->whereNotIn('status', ['completed', 'cancelled']);

            if ($incomplete->isNotEmpty()) {
                throw new \InvalidArgumentException('Cannot close ticket: all tasks must be completed or cancelled first.');
            }

            $updates['closed_at'] = now();
        }

        if ($status === 'on_hold') {
            $updates['hold_started_at'] = now();
        }

        if ($ticket->status === 'on_hold' && $status !== 'on_hold' && $ticket->hold_started_at) {
            $holdMinutes = (int) now()->diffInMinutes($ticket->hold_started_at);
            $updates['total_hold_time_minutes'] = $ticket->total_hold_time_minutes + $holdMinutes;
            $updates['hold_started_at'] = null;
        }

        $ticket->update($updates);

        $this->addHistory($ticket, 'status_changed', 'status', $oldStatus, $status, "Status changed from {$oldStatus} to {$status}.");

        if ($this->notificationsEnabled()) {
            $ticketId = $ticket->id;
            $tenantId = $ticket->tenant_id;

            $this->afterResponse(function () use ($ticketId, $tenantId, $oldStatus, $status) {
                TenantMailService::configure($tenantId);
                $ticket = \App\Models\Ticket::with('client')->find($ticketId);

                if (! $ticket) {
                    return;
                }

                // Notify client (client-friendly template)
                $client = $ticket->client;
                if ($client?->user_id) {
                    $client->user->notify(new ClientTicketStatusChangedNotification($ticket, $oldStatus, $status));
                } elseif ($client?->email) {
                    Notification::route('mail', $client->email)
                        ->notify(new ClientTicketStatusChangedNotification($ticket, $oldStatus, $status));
                }

                // Notify admin email
                $this->notifyAdminEmail($tenantId, new TicketStatusChangedNotification($ticket, $oldStatus, $status));
            });
        }
    }

    /**
     * Change ticket priority.
     */
    public function changePriority(Ticket $ticket, string $priority): void
    {
        $oldPriority = $ticket->priority;
        $ticket->update(['priority' => $priority]);
        $this->addHistory($ticket, 'updated', 'priority', $oldPriority, $priority, "Priority changed from {$oldPriority} to {$priority}.");
    }

    /**
     * Close a ticket.
     */
    public function closeTicket(Ticket $ticket): void
    {
        $this->changeStatus($ticket, 'closed');
    }

    /**
     * Update billing information on a ticket.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateBilling(Ticket $ticket, array $data): Ticket
    {
        $ticket->update([
            'is_billable' => $data['is_billable'] ?? false,
            'billable_amount' => $data['billable_amount'] ?? null,
            'billable_description' => $data['billable_description'] ?? null,
            'billed_at' => ! empty($data['mark_billed']) ? now() : $ticket->billed_at,
        ]);

        $this->addHistory($ticket, 'billing_updated', description: 'Billing information updated.');

        return $ticket->fresh();
    }

    /**
     * Mark a ticket as spam.
     */
    public function markAsSpam(Ticket $ticket, ?string $reason = null): void
    {
        $ticket->update([
            'is_spam' => true,
            'marked_spam_at' => now(),
            'marked_spam_by' => Auth::id(),
            'spam_reason' => $reason,
        ]);

        $this->addHistory($ticket, 'spam_marked', description: 'Marked as spam.'.($reason ? " Reason: {$reason}" : ''));
    }

    /**
     * Unmark a ticket as spam.
     */
    public function unmarkAsSpam(Ticket $ticket): void
    {
        $ticket->update([
            'is_spam' => false,
            'marked_spam_at' => null,
            'marked_spam_by' => null,
            'spam_reason' => null,
        ]);

        $this->addHistory($ticket, 'spam_unmarked', description: 'Removed spam flag.');
    }

    /**
     * Add a history entry for a ticket.
     */
    public function addHistory(
        Ticket $ticket,
        string $action,
        ?string $fieldName = null,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?string $description = null,
        ?array $metadata = null,
    ): TicketHistory {
        return TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'action' => $action,
            'field_name' => $fieldName,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    private function notificationsEnabled(): bool
    {
        if (! $this->planService->currentTenantHasFeature(PlanFeature::EmailNotifications)) {
            return false;
        }

        TenantMailService::configure();

        return true;
    }

    /**
     * Send a notification to the configured admin email.
     */
    private function notifyAdminEmail(int $tenantId, \Illuminate\Notifications\Notification $notification): void
    {
        $adminEmail = \App\Models\AppSetting::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('key', 'admin_notification_email')
            ->first()?->getTypedValue();

        if ($adminEmail) {
            Notification::route('mail', $adminEmail)->notify($notification);
        }
    }

    /**
     * Run a callback after the HTTP response, or immediately in testing.
     */
    private function afterResponse(\Closure $callback): void
    {
        if (app()->runningUnitTests()) {
            $callback();
        } else {
            dispatch($callback)->afterResponse();
        }
    }
}
