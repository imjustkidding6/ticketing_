<?php

namespace App\Http\Controllers;

use App\Enums\PlanFeature;
use App\Http\Controllers\Concerns\HasSortableQuery;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Models\CannedResponse;
use App\Models\Client;
use App\Models\Department;
use App\Models\Product;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\PlanService;
use App\Services\TicketMergeService;
use App\Services\TicketService;
use App\Services\TicketWorkflowService;
use App\Support\TenantValidation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends Controller
{
    use HasSortableQuery;

    public function __construct(
        private TicketService $ticketService,
        private TicketMergeService $mergeService,
        private TicketWorkflowService $workflowService,
    ) {}

    /**
     * Scope ticket query by the user's department access.
     * Owners/admins see all; agents/managers see only their departments.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    private function scopeByUserDepartments($query)
    {
        $user = Auth::user();
        $tenant = $user->currentTenant();

        if (! $tenant) {
            return $query;
        }

        $role = $user->roleInTenant($tenant);

        if (in_array($role, ['owner', 'admin'])) {
            return $query;
        }

        // 'view all tickets' lifts the department restriction for roles that hold
        // it (manager, supervisor, senior agent, viewer). Unlike the other
        // permissions this one decides scope, not access — never a 403.
        if ($this->userCan('view all tickets')) {
            return $query;
        }

        $departmentIds = $user->departments()->pluck('departments.id');

        if ($departmentIds->isEmpty()) {
            return $query;
        }

        return $query->where(function ($q) use ($departmentIds, $user) {
            $q->whereIn('department_id', $departmentIds)
                ->orWhere('assigned_to', $user->id)
                ->orWhere('created_by', $user->id);
        });
    }

    /**
     * Display a listing of tickets.
     */
    public function index(Request $request): View
    {
        $this->checkPermission('view tickets');

        $tickets = $this->scopeByUserDepartments(Ticket::query())
            ->with(['client', 'category', 'department', 'creator', 'assignee'])
            ->withCount('mergedTickets')
            ->notSpam()
            ->when(! $request->boolean('show_merged'), fn ($q) => $q->where('is_merged', false))
            ->when($request->status, function ($query, $status) {
                if ($status === 'open') {
                    $query->open();
                } elseif ($status === 'closed') {
                    $query->closed();
                } else {
                    $query->where('status', $status);
                }
            })
            ->when($request->priority, fn ($query, $priority) => $query->where('priority', $priority))
            ->when($request->sla_policy_id, fn ($query, $policyId) => $query->where('sla_policy_id', $policyId))
            ->when($request->boolean('sla_breached') || $request->sla_breached === '1', function ($query) {
                $query->where(function ($q) {
                    $q->where('resolution_due_at', '<', now())
                        ->orWhere(function ($q2) {
                            $q2->whereNull('first_response_at')
                                ->where('response_due_at', '<', now());
                        });
                });
            })
            ->when($request->department_id, fn ($query, $dept) => $query->where('department_id', $dept))
            ->when($request->category_id, fn ($query, $cat) => $query->where('category_id', $cat))
            ->when($request->client_id, fn ($query, $client) => $query->where('client_id', $client))
            ->when($request->assigned_to, fn ($query, $agent) => $query->where('assigned_to', $agent))
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('subject', 'like', "%{$search}%")
                        ->orWhere('ticket_number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            });

        // If a sort is explicitly requested, honor it; otherwise use the default
        // status-priority order from the list spec.
        if ($request->filled('sort')) {
            $sort = $request->input('sort');
            $direction = strtolower($request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

            switch ($sort) {
                case 'client':
                    $tickets->leftJoin('clients', 'tickets.client_id', '=', 'clients.id')
                        ->select('tickets.*')
                        ->orderBy('clients.name', $direction);
                    break;
                case 'assignee':
                    $tickets->leftJoin('users', 'tickets.assigned_to', '=', 'users.id')
                        ->select('tickets.*')
                        ->orderBy('users.name', $direction);
                    break;
                case 'resolution':
                    // Sort by elapsed hours (closed_at - created_at); open tickets use NOW().
                    $tickets->orderByRaw("TIMESTAMPDIFF(MINUTE, tickets.created_at, COALESCE(tickets.closed_at, NOW())) {$direction}");
                    break;
                case 'priority':
                    // Order priority by severity: critical > high > medium > low
                    $tickets->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low') {$direction}");
                    break;
                case 'ticket_number':
                case 'subject':
                case 'status':
                case 'created_at':
                    $tickets->orderBy($sort, $direction);
                    break;
                default:
                    $tickets->orderByRaw("FIELD(status, 'open', 'assigned', 'in_progress', 'on_hold', 'closed', 'cancelled')")
                        ->latest();
            }
        } else {
            $tickets->orderByRaw("FIELD(status, 'open', 'assigned', 'in_progress', 'on_hold', 'closed', 'cancelled')")
                ->latest();
        }

        $tickets = $tickets->paginate(20)->withQueryString();

        $departments = Department::active()->ordered()->get();
        $agents = User::query()
            ->whereHas('tenants', fn ($q) => $q->where('tenant_id', session('current_tenant_id')))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('tickets.index', compact('tickets', 'departments', 'agents'));
    }

    /**
     * Show the form for creating a new ticket.
     */
    public function create(): View
    {
        $this->checkPermission('create tickets');

        $clients = Client::active()->orderBy('name')->get(['id', 'name', 'tier']);
        $departments = Department::active()->ordered()->get();
        $categories = TicketCategory::active()->ordered()->get();
        $products = Product::active()->ordered()->get();
        $agents = User::query()
            ->whereHas('tenants', fn ($q) => $q->where('tenant_id', session('current_tenant_id')))
            ->with('schedules')
            ->orderBy('name')
            ->get(['id', 'name', 'support_tier']);

        // Build SLA lookup: { tier: { priority: { response, resolution } } }
        $slaLookup = SlaPolicy::active()
            ->whereNotNull('client_tier')
            ->whereNotNull('priority')
            ->get()
            ->groupBy('client_tier')
            ->map(fn ($policies) => $policies->keyBy('priority')->map(fn ($p) => [
                'response' => $p->response_time_hours,
                'resolution' => $p->resolution_time_hours,
            ]));

        return view('tickets.create', compact('clients', 'departments', 'categories', 'products', 'agents', 'slaLookup'));
    }

    /**
     * Store a newly created ticket.
     */
    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $this->checkPermission('create tickets');

        $data = $request->validated();

        if ($request->hasFile('attachments') && app(PlanService::class)->currentTenantHasFeature(PlanFeature::Attachments)) {
            $data['attachments'] = $this->storeAttachments($request);
        } else {
            unset($data['attachments']);
        }

        try {
            $ticket = $this->ticketService->createTicket($data);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withInput()->withErrors(['assigned_to' => $e->getMessage()]);
        }

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Ticket created successfully.');
    }

    /**
     * Display the specified ticket.
     */
    public function show(Ticket $ticket): View
    {
        $this->checkPermission('view tickets');

        // Verify department access
        $accessCheck = $this->scopeByUserDepartments(Ticket::query()->where('id', $ticket->id))->exists();
        abort_unless($accessCheck, 403);

        $ticket->load([
            'client',
            'category',
            'department',
            'products',
            'creator',
            'assignee',
            'slaPolicy',
            'assignments.user',
            'tasks',
            'history' => fn ($q) => $q->with('user')->latest(),
            // Comments are a separate grant — a role may see the ticket without
            // seeing the conversation on it.
            'comments' => fn ($q) => $q->when(
                $this->userCan('view ticket comments'),
                fn ($c) => $c->with('user')->oldest(),
                fn ($c) => $c->whereRaw('1 = 0'),
            ),
            'escalations' => fn ($q) => $q->with(['escalatedByUser', 'fromUser', 'toUser'])->latest(),
            'mergedTickets' => fn ($q) => $q->with('client')->latest('merged_at'),
        ]);

        $agents = User::query()
            ->whereHas('tenants', fn ($q) => $q->where('tenant_id', session('current_tenant_id')))
            ->with('schedules')
            ->orderBy('name')
            ->get(['id', 'name', 'support_tier']);

        $mergeableTickets = collect();
        if (app(PlanService::class)->currentTenantHasFeature(PlanFeature::TicketMerging)) {
            $mergeableTickets = Ticket::query()
                ->where('id', '!=', $ticket->id)
                ->where('is_merged', false)
                ->whereNotIn('status', ['closed', 'cancelled'])
                ->orderByDesc('created_at')
                ->limit(100)
                ->get(['id', 'ticket_number', 'subject']);
        }

        $cannedResponses = collect();
        if (app(PlanService::class)->currentTenantHasFeature(PlanFeature::CannedResponses)) {
            $cannedResponses = CannedResponse::query()->ordered()->get(['id', 'name', 'category', 'content']);
        }

        return view('tickets.show', compact('ticket', 'agents', 'mergeableTickets', 'cannedResponses'));
    }

    /**
     * Show the form for editing the specified ticket.
     */
    public function edit(Ticket $ticket): View
    {
        $this->checkPermission('update tickets');

        $ticket->load('products');
        $clients = Client::active()->orderBy('name')->get(['id', 'name', 'tier']);
        $departments = Department::active()->ordered()->get();
        $categories = TicketCategory::active()->ordered()->get();
        $products = Product::active()->ordered()->get();
        $agents = User::query()
            ->whereHas('tenants', fn ($q) => $q->where('tenant_id', session('current_tenant_id')))
            ->orderBy('name')
            ->get(['id', 'name']);

        $slaLookup = SlaPolicy::active()
            ->whereNotNull('client_tier')
            ->whereNotNull('priority')
            ->get()
            ->groupBy('client_tier')
            ->map(fn ($policies) => $policies->keyBy('priority')->map(fn ($p) => [
                'response' => $p->response_time_hours,
                'resolution' => $p->resolution_time_hours,
            ]));

        return view('tickets.edit', compact('ticket', 'clients', 'departments', 'categories', 'products', 'agents', 'slaLookup'));
    }

    /**
     * Update the specified ticket.
     */
    public function update(UpdateTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->checkPermission('update tickets');

        $data = $request->validated();

        if ($request->hasFile('attachments') && app(PlanService::class)->currentTenantHasFeature(PlanFeature::Attachments)) {
            $existing = $ticket->attachments ?? [];
            $data['attachments'] = array_merge($existing, $this->storeAttachments($request));
        } else {
            unset($data['attachments']);
        }

        try {
            $this->ticketService->updateTicket($ticket, $data);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withInput()->withErrors(['assigned_to' => $e->getMessage()]);
        }

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Ticket updated successfully.');
    }

    /**
     * Assign a ticket to an agent.
     */
    public function assign(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->checkPermission('assign tickets');
        $validated = $request->validate([
            'assigned_to' => ['required', TenantValidation::user()],
            'priority' => ['nullable', 'in:low,medium,high,critical'],
        ]);

        $agent = User::query()
            ->whereHas('tenants', fn ($q) => $q->where('tenant_id', session('current_tenant_id')))
            ->findOrFail($validated['assigned_to']);

        try {
            DB::transaction(function () use ($ticket, $agent, $validated) {
                if (! empty($validated['priority']) && $validated['priority'] !== $ticket->priority) {
                    $this->ticketService->changePriority($ticket, $validated['priority']);
                }
                // assignTicket will guard against tier vs. the (possibly just-updated) priority;
                // any exception rolls back the priority change above.
                $this->ticketService->assignTicket($ticket, $agent);
            });
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('tickets.show', $ticket)->with('error', $e->getMessage());
        }

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Ticket assigned to '.$agent->name.'.');
    }

    /**
     * Self-assign a ticket.
     */
    public function selfAssign(Ticket $ticket): RedirectResponse
    {
        $this->checkPermission('update tickets');

        try {
            $this->ticketService->assignTicket($ticket, Auth::user());
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('tickets.show', $ticket)->with('error', $e->getMessage());
        }

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Ticket assigned to you.');
    }

    /**
     * Change ticket status.
     */
    public function changeStatus(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->checkPermission('update tickets');

        $validated = $request->validate([
            'status' => ['required', 'in:open,assigned,in_progress,on_hold,closed,cancelled'],
            'closing_remarks' => [
                Rule::requiredIf(fn () => $request->input('status') === 'closed' && $ticket->status !== 'closed'),
                'nullable', 'string', 'max:2000',
            ],
        ]);

        if ($ticket->status === 'closed'
            && ! app(PlanService::class)->currentTenantHasFeature(PlanFeature::TicketReopening)
        ) {
            return redirect()->route('tickets.show', $ticket)
                ->with('error', 'Reopening closed tickets requires the Enterprise plan.');
        }

        if ($validated['status'] === 'closed' && ! empty($validated['closing_remarks'])) {
            $ticket->update(['closing_remarks' => $validated['closing_remarks']]);
        }

        try {
            $this->ticketService->changeStatus($ticket->fresh(), $validated['status']);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('tickets.show', $ticket)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Ticket status changed to '.ucfirst(str_replace('_', ' ', $validated['status'])).'.');
    }

    /**
     * Change ticket priority.
     */
    public function changePriority(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->checkPermission('update tickets');

        $validated = $request->validate([
            'priority' => ['required', 'in:low,medium,high,critical'],
        ]);

        $this->ticketService->changePriority($ticket, $validated['priority']);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Priority updated to '.ucfirst($validated['priority']).'.');
    }

    /**
     * Update billing information on a ticket.
     */
    public function updateBilling(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->checkPermission('manage billing');

        $validated = $request->validate([
            'is_billable' => ['nullable'],
            'billable_amount' => ['nullable', 'numeric', 'min:0'],
            'billable_description' => ['nullable', 'string', 'max:1000'],
            'mark_billed' => ['nullable'],
        ]);

        $validated['is_billable'] = $request->has('is_billable');

        $this->ticketService->updateBilling($ticket, $validated);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Billing information updated.');
    }

    /**
     * Mark a ticket as spam.
     */
    public function markAsSpam(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->checkPermission('update tickets');

        $validated = $request->validate([
            'spam_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->ticketService->markAsSpam($ticket, $validated['spam_reason'] ?? null);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Ticket marked as spam.');
    }

    /**
     * Unmark a ticket as spam.
     */
    public function unmarkAsSpam(Ticket $ticket): RedirectResponse
    {
        $this->checkPermission('update tickets');

        $this->ticketService->unmarkAsSpam($ticket);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Spam flag removed.');
    }

    /**
     * Merge this ticket into another ticket.
     */
    public function merge(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->checkPermission('merge tickets');

        $validated = $request->validate([
            'target_ticket_id' => ['required', TenantValidation::exists('tickets'), 'different:ticket'],
        ]);

        $target = Ticket::findOrFail($validated['target_ticket_id']);
        $this->mergeService->merge($ticket, $target);

        return redirect()->route('tickets.show', $target)
            ->with('success', "Ticket {$ticket->ticket_number} merged into this ticket.");
    }

    /**
     * Unmerge a previously merged ticket, restoring it as a standalone open ticket.
     */
    public function unmerge(Ticket $ticket): RedirectResponse
    {
        $this->checkPermission('unmerge tickets');

        $target = $ticket->merged_into_ticket_id ? Ticket::find($ticket->merged_into_ticket_id) : null;
        $this->mergeService->unmerge($ticket);

        return redirect()->route('tickets.show', $target ?? $ticket)
            ->with('success', "Ticket {$ticket->ticket_number} was unmerged and reopened.");
    }

    /**
     * Reopen a closed ticket.
     */
    public function reopen(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->checkPermission('reopen tickets');

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $reason = $validated['reason'] ?? null;
        $nextCount = $ticket->reopened_count + 1;

        $description = 'Ticket reopened (cycle '.($nextCount + 1).')';
        if ($reason) {
            $description .= ' — reason: '.$reason;
        }

        $this->ticketService->addHistory($ticket, 'reopened', 'status', $ticket->status, 'open', $description);

        $ticket->update([
            'closed_at' => null,
            'reopened_count' => $nextCount,
            'last_reopened_at' => now(),
            'last_reopen_reason' => $reason,
        ]);

        $this->ticketService->changeStatus($ticket->fresh(), 'open');

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Ticket reopened.');
    }

    /**
     * Download a ticket attachment.
     */
    public function downloadAttachment(Ticket $ticket, int $index): StreamedResponse
    {
        $this->checkPermission('view attachments');

        $attachments = $ticket->attachments ?? [];

        abort_unless(isset($attachments[$index]), 404);

        $attachment = $attachments[$index];

        return Storage::download($attachment['path'], $attachment['name']);
    }

    /**
     * Soft delete a ticket.
     */
    public function destroy(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->checkPermission('delete tickets');

        $this->ticketService->addHistory($ticket, 'deleted', null, null, null, 'Ticket deleted'.($request->input('reason') ? ': '.$request->input('reason') : ''));

        $ticket->update([
            'deleted_by' => Auth::id(),
            'deletion_reason' => $request->input('reason'),
        ]);

        $ticket->delete();

        return redirect()->route('tickets.index')
            ->with('success', 'Ticket deleted successfully.');
    }

    /**
     * Spam repository — list all spam tickets, with bulk delete.
     */
    public function spam(Request $request): View
    {
        $this->checkPermission('view tickets');

        $tickets = Ticket::query()
            ->onlySpam()
            ->with(['client', 'department', 'markedSpamBy'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('subject', 'like', "%{$search}%")
                        ->orWhere('ticket_number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->latest('marked_spam_at')
            ->paginate(25)
            ->withQueryString();

        return view('tickets.spam', compact('tickets'));
    }

    /**
     * Permanently delete one or more spam tickets.
     */
    public function destroySpam(Request $request): RedirectResponse
    {
        $this->checkPermission('delete tickets');

        $validated = $request->validate([
            'ticket_ids' => ['required', 'array', 'min:1'],
            'ticket_ids.*' => ['integer'],
        ]);

        $tickets = Ticket::query()
            ->onlySpam()
            ->whereIn('id', $validated['ticket_ids'])
            ->get();

        $count = 0;
        foreach ($tickets as $ticket) {
            if ($ticket->attachments) {
                foreach ($ticket->attachments as $attachment) {
                    Storage::delete($attachment['path'] ?? '');
                }
            }
            $ticket->forceDelete();
            $count++;
        }

        return redirect()->route('tickets.spam')
            ->with('success', $count === 1
                ? '1 spam ticket deleted.'
                : "{$count} spam tickets deleted.");
    }

    /**
     * Search tickets by keyword.
     */
    public function search(Request $request): View
    {
        $this->checkPermission('view tickets');

        $query = $request->input('q', '');

        $tickets = collect();
        if (strlen($query) >= 2) {
            $tickets = Ticket::query()
                ->with(['client', 'assignee', 'department'])
                ->notSpam()
                ->where(function ($q) use ($query) {
                    $q->where('ticket_number', 'like', "%{$query}%")
                        ->orWhere('subject', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%")
                        ->orWhereHas('client', fn ($cq) => $cq->where('name', 'like', "%{$query}%"));
                })
                ->latest()
                ->limit(50)
                ->get();
        }

        return view('tickets.search', compact('tickets', 'query'));
    }

    /**
     * Show trashed (soft-deleted) tickets.
     */
    public function trashed(Request $request): View
    {
        $this->checkPermission('delete tickets');

        $tickets = Ticket::onlyTrashed()
            ->with(['client', 'department', 'deletedByUser'])
            ->latest('deleted_at')
            ->paginate(20)
            ->withQueryString();

        return view('tickets.trashed', compact('tickets'));
    }

    /**
     * Restore a soft-deleted ticket.
     */
    public function restore(Ticket $ticket): RedirectResponse
    {
        $this->checkPermission('delete tickets');

        $ticket->restore();
        $ticket->update(['deleted_by' => null, 'deletion_reason' => null]);

        $this->ticketService->addHistory($ticket, 'restored', null, null, null, 'Ticket restored from trash');

        return redirect()->route('tickets.trashed')
            ->with('success', "Ticket {$ticket->ticket_number} restored.");
    }

    /**
     * Permanently delete a ticket.
     */
    public function forceDestroy(Ticket $ticket): RedirectResponse
    {
        $this->checkPermission('delete tickets');

        $ticketNumber = $ticket->ticket_number;

        // Cleanup attachments
        if ($ticket->attachments) {
            foreach ($ticket->attachments as $attachment) {
                Storage::delete($attachment['path'] ?? '');
            }
        }

        $ticket->forceDelete();

        return redirect()->route('tickets.trashed')
            ->with('success', "Ticket {$ticketNumber} permanently deleted.");
    }

    /**
     * Mark a ticket as false alarm and close it.
     */
    public function markFalseAlarm(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->checkPermission('update tickets');

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->workflowService->markFalseAlarm($ticket, $validated['reason'] ?? null);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Ticket marked as false alarm and closed.');
    }

    /**
     * Create a child ticket linked to a parent.
     */
    public function createChild(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->checkPermission('create tickets');

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', 'in:low,medium,high,critical'],
        ]);

        $validated['parent_ticket_id'] = $ticket->id;
        $validated['client_id'] = $ticket->client_id;
        $validated['department_id'] = $ticket->department_id;
        $validated['category_id'] = $ticket->category_id;

        $child = $this->ticketService->createTicket($validated);

        return redirect()->route('tickets.show', $child)
            ->with('success', 'Child ticket created successfully.');
    }

    /**
     * Store uploaded attachment files and return metadata array.
     *
     * @return list<array{name: string, path: string, size: int, mime: string}>
     */
    private function storeAttachments(Request $request): array
    {
        $tenantId = session('current_tenant_id');
        $stored = [];

        foreach ($request->file('attachments') as $file) {
            $path = $file->store("tenants/{$tenantId}/attachments", config('filesystems.default'));
            $stored[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
            ];
        }

        return $stored;
    }
}
