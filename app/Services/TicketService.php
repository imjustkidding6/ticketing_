<?php

namespace App\Services;

use App\Enums\PlanFeature;
use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\TicketAlreadyAssignedException;
use App\Models\AppSetting;
use App\Models\Client;
use App\Models\Scopes\TenantScope;
use App\Models\SlaPolicy;
use App\Models\Tenant;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class TicketService
{
    public function __construct(
        private SlaService $slaService,
        private PlanService $planService,
        private ServiceReportService $serviceReportService,
    ) {}

    /**
     * Block assignment when the agent's tier can't handle the ticket's priority.
     * Only enforced for tenants with the agent_escalation feature (Enterprise).
     * Owners bypass.
     */
    /**
     * Block a priority assignment if the tenant requires an SLA policy
     * and none exists for the given (client_tier, priority) pair.
     */
    private function guardSlaPolicy(int $tenantId, ?string $clientTier, ?string $priority, Tenant $tenant): void
    {
        if (! $priority) {
            return;
        }

        if (! $this->planService->tenantHasFeature($tenant, PlanFeature::SlaManagement)) {
            return;
        }

        if (! SlaPolicy::hasPolicyFor($tenantId, $clientTier, $priority)) {
            $tierLabel = $clientTier ? " for {$clientTier} clients" : '';
            throw new \InvalidArgumentException(
                "Cannot set priority to '{$priority}'{$tierLabel}: no SLA policy defined. ".
                'Create or seed SLA policies under Settings → SLA first.'
            );
        }
    }

    private function guardAgentTier(Ticket $ticket, User $agent): void
    {
        if (! $this->planService->tenantHasFeature($ticket->tenant, PlanFeature::AgentEscalation)) {
            return;
        }

        if (! $ticket->priority) {
            return; // Untriaged tickets don't trigger the guard.
        }

        $pivot = $agent->tenants()->where('tenants.id', $ticket->tenant_id)->first()?->pivot;
        if ($pivot?->role === 'owner') {
            return;
        }

        if (! $agent->canHandlePriority($ticket->priority)) {
            $tierLabel = $agent->support_tier ? strtoupper(str_replace('_', ' ', $agent->support_tier)) : 'untiered';
            throw new \InvalidArgumentException(
                "Cannot assign a {$ticket->priority} priority ticket to {$agent->name} ({$tierLabel}). ".
                'Pick a higher-tier agent, or lower the ticket priority first.'
            );
        }
    }

    /**
     * Block assignment when the agent is outside their weekly schedule window.
     * Only enforced when the tenant has the agent_schedule feature.
     * Users with no configured schedule are allowed (not configured = no rule).
     * Owners bypass.
     */
    private function guardAgentAvailability(Ticket $ticket, User $agent): void
    {
        if (! $this->planService->tenantHasFeature($ticket->tenant, PlanFeature::AgentSchedule)) {
            return;
        }

        $pivot = $agent->tenants()->where('tenants.id', $ticket->tenant_id)->first()?->pivot;
        if ($pivot?->role === 'owner') {
            return;
        }

        if (! $agent->isOnScheduleAt(now())) {
            throw new \InvalidArgumentException(
                "Cannot assign to {$agent->name}: they're off-schedule right now. ".
                'Pick an available agent or wait until their working hours.'
            );
        }
    }

    /**
     * Would createTicket() admit this (priority, assignee) pair for this tenant/client?
     *
     * A DRY RUN over the same three private guards createTicket() runs, in the same
     * order and under the same preconditions — never a copy of their predicates. The AI
     * draft on the create form needs to know, before it suggests anything, which
     * priorities the workspace can actually honour; a fourth encoding of the SLA-coverage
     * and tier ladders is how a suggestion gets satisfied here and refused at submit.
     *
     * Only the first blocking message is returned: the guards throw, so the first one to
     * fire is the only verdict there is — which is exactly what the engineer will see.
     *
     * @return list<string> Blocking messages; empty when admissible.
     */
    public function draftAdmissionErrors(Tenant $tenant, ?int $clientId, ?string $priority, ?User $assignee = null): array
    {
        $clientTier = null;
        if ($clientId) {
            // Pinned to this tenant, exactly as createTicket() reads it: a client from
            // another workspace must not decide which policies cover the draft.
            $clientTier = Client::withoutGlobalScopes()
                ->where('tenant_id', $tenant->getKey())
                ->find($clientId)?->tier;
        }

        $probe = new Ticket([
            'tenant_id' => $tenant->getKey(),
            'priority' => $priority,
        ]);
        // The guards read $ticket->tenant for the feature check; handing them the tenant
        // we were given avoids a lookup and keeps the workspace explicit.
        $probe->setRelation('tenant', $tenant);

        try {
            $this->guardSlaPolicy((int) $tenant->getKey(), $clientTier, $priority, $tenant);

            // createTicket() reaches the agent guards only when BOTH an assignee and a
            // priority are present; a dry run that checked more would refuse drafts the
            // real path admits.
            if ($assignee !== null && ! empty($priority)) {
                $this->guardAgentTier($probe, $assignee);
                $this->guardAgentAvailability($probe, $assignee);
            }
        } catch (\InvalidArgumentException $e) {
            return [$e->getMessage()];
        }

        return [];
    }

    /**
     * The priority-only convenience over draftAdmissionErrors(), for building the set of
     * priorities an AI draft may offer.
     */
    public function draftWouldAdmitPriority(Tenant $tenant, ?int $clientId, string $priority): bool
    {
        return $this->draftAdmissionErrors($tenant, $clientId, $priority) === [];
    }

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
        $data['created_by'] = $data['created_by'] ?? Auth::id();
        $data['status'] = 'open';

        // BelongsToTenant fills tenant_id on `creating`, which is far too late:
        // the guards below run first and silently skipped every web request
        // because $data['tenant_id'] was still empty.
        $data['tenant_id'] = $data['tenant_id'] ?? TenantScope::getCurrentTenantId();

        if (! empty($data['priority']) && ! empty($data['tenant_id'])) {
            $tenant = Tenant::find($data['tenant_id']);
            if ($tenant) {
                $clientTier = null;
                if (! empty($data['client_id'])) {
                    // Scoped to the ticket's own tenant: reading a client from another
                    // workspace here would let a foreign client_id through the guard.
                    $clientTier = Client::withoutGlobalScopes()
                        ->where('tenant_id', $data['tenant_id'])
                        ->find($data['client_id'])?->tier;
                }
                $this->guardSlaPolicy($data['tenant_id'], $clientTier, $data['priority'], $tenant);
            }
        }

        if (! empty($data['assigned_to']) && ! empty($data['tenant_id']) && ! empty($data['priority'])) {
            $agent = User::find($data['assigned_to']);
            if ($agent) {
                $probe = new Ticket([
                    'tenant_id' => $data['tenant_id'],
                    'priority' => $data['priority'],
                ]);
                $this->guardAgentTier($probe, $agent);
                $this->guardAgentAvailability($probe, $agent);
            }
        }

        // Five tables: tickets, product_ticket, ticket_tasks, the SLA columns and
        // ticket_histories. A failure part-way through used to leave a live ticket
        // with no SLA and no `created` audit entry. Notifications stay outside the
        // transaction — they are deferred until after the response, and an email
        // that cannot be unsent has no business holding a database lock.
        $ticket = DB::transaction(function () use ($data, $productIds, $tasks) {
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

            return $ticket->fresh();
        });

        if ($this->notificationsEnabled()) {
            $ticketId = $ticket->id;
            $tenantId = $ticket->tenant_id;
            $creatorId = Auth::id();

            $this->afterResponse(function () use ($ticketId, $tenantId, $creatorId) {
                TenantMailService::configure($tenantId);
                $ticket = Ticket::with(['client', 'department'])->find($ticketId);

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
                    $deptUsers = User::query()
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

        $originalPriority = $ticket->priority;
        $nextAssignee = array_key_exists('assigned_to', $data) ? $data['assigned_to'] : $ticket->assigned_to;
        $nextPriority = array_key_exists('priority', $data) ? $data['priority'] : $ticket->priority;

        if ($nextPriority && array_key_exists('priority', $data) && $data['priority'] !== $ticket->priority) {
            $this->guardSlaPolicy($ticket->tenant_id, $ticket->client?->tier, $nextPriority, $ticket->tenant);
        }

        if ($nextAssignee && $nextPriority) {
            $agent = User::find($nextAssignee);
            if ($agent) {
                $probe = (clone $ticket)->forceFill(['priority' => $nextPriority]);
                $this->guardAgentTier($probe, $agent);
                $this->guardAgentAvailability($probe, $agent);
            }
        }

        $tracked = ['subject', 'priority', 'status', 'department_id', 'category_id', 'client_id'];

        foreach ($tracked as $field) {
            if (array_key_exists($field, $data) && $ticket->getAttribute($field) != $data[$field]) {
                $this->addHistory($ticket, 'updated', $field, (string) $ticket->getAttribute($field), (string) $data[$field]);
            }
        }

        $status = null;

        if (isset($data['status']) && $data['status'] !== $ticket->status) {
            $status = $data['status'];
            unset($data['status']);
        }

        $ticket->update($data);

        if ($productIds !== null) {
            $ticket->products()->sync($productIds);
        }

        // A priority change through the edit form must move the deadlines for the
        // same reason changePriority() does.
        if (array_key_exists('priority', $data) && $data['priority'] !== $originalPriority) {
            $this->slaService->assignSla($ticket->fresh());
        }

        if ($status !== null) {
            $this->changeStatus($ticket->fresh(), $status);
        }

        return $ticket->fresh();
    }

    /**
     * Assign a ticket to an agent.
     */
    public function assignTicket(Ticket $ticket, User $agent, ?User $assignedBy = null, bool $onlyIfUnassigned = false): void
    {
        $this->guardAgentTier($ticket, $agent);
        $this->guardAgentAvailability($ticket, $agent);

        $oldAssignee = $ticket->assigned_to;

        DB::transaction(function () use ($ticket, $agent, $assignedBy, $oldAssignee, $onlyIfUnassigned) {
            // Claiming an unassigned ticket is a race: two agents can both read
            // assigned_to as null and both write. Make the write conditional and
            // let the database decide the winner. Deliberate reassignment by a
            // manager passes $onlyIfUnassigned = false and overwrites as before.
            $update = Ticket::withoutGlobalScopes()->where('id', $ticket->id);

            if ($onlyIfUnassigned) {
                $update->whereNull('assigned_to');
            }

            $claimed = $update->update([
                'assigned_to' => $agent->id,
                'status' => $ticket->status === 'open' ? 'assigned' : $ticket->status,
                'updated_at' => now(),
            ]);

            if ($onlyIfUnassigned && $claimed === 0) {
                throw new TicketAlreadyAssignedException(
                    'This ticket was just picked up by another agent.'
                );
            }

            $ticket->refresh();

            TicketAssignment::create([
                'ticket_id' => $ticket->id,
                'user_id' => $agent->id,
                'assigned_by' => $assignedBy?->id ?? Auth::id(),
            ]);

            $this->addHistory($ticket, 'assigned', 'assigned_to', (string) $oldAssignee, (string) $agent->id, 'Assigned to '.$agent->name);
        });

        if ($this->notificationsEnabled()) {
            $ticketId = $ticket->id;
            $tenantId = $ticket->tenant_id;
            $agentId = $agent->id;
            $currentUserId = Auth::id();

            $this->afterResponse(function () use ($ticketId, $tenantId, $agentId) {
                TenantMailService::configure($tenantId);
                $ticket = Ticket::with('client')->find($ticketId);
                $agent = User::find($agentId);

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

        // tickets.status is a varchar, so the database will store any string we
        // hand it. Ticket::STATUSES is the only enumeration left, and this is
        // the one write path every caller funnels through, so it is where an
        // unknown status has to be refused — before it is persisted and starts
        // silently dropping the ticket out of every status-keyed query.
        if (! in_array($status, Ticket::STATUSES, true)) {
            throw new \InvalidArgumentException("Unknown ticket status: {$status}");
        }

        // The transition map is enforced here rather than in a caller, so that
        // every entry point — controller, edit form, API, assistant tools —
        // is covered by the same rule.
        if ($oldStatus !== $status && ! $ticket->canTransitionTo($status)) {
            throw new InvalidStatusTransitionException($oldStatus, $status);
        }

        $updates = ['status' => $status];

        if ($status === 'in_progress' && ! $ticket->in_progress_at) {
            $updates['in_progress_at'] = now();
        }

        // Response time ends when a ticket first moves to "in progress".
        if ($status === 'in_progress' && ! $ticket->first_response_at) {
            $updates['first_response_at'] = now();
        }

        if ($status === 'closed') {
            $tasks = $ticket->tasks;

            $incomplete = $tasks->whereNotIn('status', ['completed', 'cancelled']);

            if ($incomplete->isNotEmpty()) {
                throw new \InvalidArgumentException('Cannot close ticket: all tasks must be completed or cancelled first.');
            }

            $updates['closed_at'] = now();
            if (! $ticket->first_closed_at) {
                $updates['first_closed_at'] = now();
            }
        }

        // Leaving closed: drop the closure timestamp, or the ticket stays open
        // while every closed_at-keyed report still counts it as resolved.
        if ($oldStatus === 'closed' && $status !== 'closed') {
            $updates['closed_at'] = null;
        }

        // 'pending' is a labelled hold: it pauses through the same machinery as
        // 'on_hold' so there is exactly one pause implementation, and the SLA
        // maths downstream does not learn a new concept.
        $wasPaused = in_array($oldStatus, Ticket::PAUSED_STATUSES, true);
        $isPaused = in_array($status, Ticket::PAUSED_STATUSES, true);

        $holdMinutes = 0;

        // Close out the running segment whenever the status moves off the one that
        // started it — including on_hold to pending, or that segment would be lost
        // when an agent relabels a pause.
        if ($wasPaused && $oldStatus !== $status && $ticket->hold_started_at) {
            $holdMinutes = max(0, (int) $ticket->hold_started_at->diffInMinutes(now()));
            $updates['total_hold_time_minutes'] = ($ticket->total_hold_time_minutes ?? 0) + $holdMinutes;
            $updates['hold_started_at'] = null;

            // Informational split only — this is a subset of the hold total above,
            // so it must never be subtracted from an SLA figure a second time.
            if ($oldStatus === 'pending') {
                $updates['total_pending_time_minutes'] = ($ticket->total_pending_time_minutes ?? 0) + $holdMinutes;
                $updates['pending_since'] = null;
            }
        }

        if ($isPaused) {
            $updates['hold_started_at'] = now();

            if ($status === 'pending' && $oldStatus !== 'pending') {
                $updates['pending_since'] = now();
            }
        }

        DB::transaction(function () use ($ticket, $updates, $holdMinutes, $oldStatus, $status) {
            $ticket->update($updates);

            // Push the deadlines out by the hold period, so the breach scheduler's
            // wall-clock check and the report's hold-excluding calculation agree.
            if ($holdMinutes > 0) {
                $this->slaService->extendForHold($ticket->fresh(), $holdMinutes);
                $ticket->refresh();
            }

            $this->addHistory($ticket, 'status_changed', 'status', $oldStatus, $status, "Status changed from {$oldStatus} to {$status}.");

            if ($status === 'closed'
                && $oldStatus !== 'closed'
                && $this->planService->tenantHasFeature($ticket->tenant, PlanFeature::ServiceReports)
                && (AppSetting::get('auto_generate_on_close', '1') === '1')
            ) {
                $this->serviceReportService->generate($ticket->fresh());
            }
        });

        if ($this->notificationsEnabled()) {
            $ticketId = $ticket->id;
            $tenantId = $ticket->tenant_id;

            $this->afterResponse(function () use ($ticketId, $tenantId, $oldStatus, $status) {
                TenantMailService::configure($tenantId);
                $ticket = Ticket::with('client')->find($ticketId);

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
        $this->guardSlaPolicy($ticket->tenant_id, $ticket->client?->tier, $priority, $ticket->tenant);

        $oldPriority = $ticket->priority;
        $ticket->update(['priority' => $priority]);

        // Targets differ per priority, so the deadlines have to be recomputed —
        // otherwise a ticket triaged up to critical keeps its low-priority SLA.
        $this->slaService->assignSla($ticket->fresh());
        $ticket->refresh();

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
            // Set explicitly rather than relying on the BelongsToTenant creating
            // hook: history is also written from queued jobs and scheduled
            // commands, where there is no session to resolve a tenant from.
            'tenant_id' => $ticket->tenant_id,
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

        return true;
    }

    /**
     * Send a notification to the configured admin email.
     */
    private function notifyAdminEmail(int $tenantId, \Illuminate\Notifications\Notification $notification): void
    {
        $adminEmail = AppSetting::withoutGlobalScopes()
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
        // Callbacks configure the mailer for a specific tenant. Always restore the
        // application defaults afterwards, or the next tenant handled by this
        // worker inherits the previous tenant's SMTP server and From address.
        $guarded = function () use ($callback) {
            try {
                $callback();
            } finally {
                TenantMailService::reset();
            }
        };

        if (app()->runningUnitTests()) {
            $guarded();
        } else {
            dispatch($guarded)->afterResponse();
        }
    }
}
