<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSlaCalendarRequest;
use App\Models\SlaCalendar;
use App\Models\SlaCalendarHoliday;
use App\Models\SlaCalendarHour;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Services\ActivityLogger;
use App\Services\SlaService;
use App\Support\SlaClock;
use App\Support\TenantTime;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SlaPolicyController extends Controller
{
    /**
     * Display SLA dashboard, health panel, registry, and tier workstations.
     */
    public function index(Request $request, SlaService $slaService): View
    {
        $this->checkPermission('view sla policies');

        // The one place scope is chosen. Admin is cross-tenant by intent (CLAUDE.md:
        // "Admin controllers intentionally operate across tenants"); previously it was
        // cross-tenant only by accident, because TenantScope adds no WHERE clause when
        // session('current_tenant_id') is unset — so the page changed with the admin's
        // browsing history. Every bypass below is inside this branch; the tenant branch
        // is the ordinary scoped query and must stay that way.
        $isAdmin = $request->routeIs('admin.*');

        // 1. Query policies with ticket usage counts
        $query = ($isAdmin ? SlaPolicy::withoutGlobalScopes() : SlaPolicy::query())
            ->withCount($this->ticketUsageCounts($isAdmin));

        // Cross-tenant rows are unreadable without the workspace that owns them.
        // Tenant is not a BelongsToTenant model, so an ordinary with() is correct
        // here — and one eager load, not a lookup per row.
        if ($isAdmin) {
            $query->with(['tenant:id,name,slug']);
        }

        // Filter: Search Name or Description
        if ($request->filled('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$search}%"]);
            });
        }

        // Filter: Tier
        if ($request->filled('tier') && $request->input('tier') !== 'All') {
            $query->where('client_tier', strtolower($request->input('tier')));
        }

        // Filter: Priority
        if ($request->filled('priority') && $request->input('priority') !== 'All') {
            $query->where('priority', strtolower($request->input('priority')));
        }

        // Filter: Status
        if ($request->filled('status') && $request->input('status') !== 'All') {
            $status = strtolower($request->input('status'));
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif (in_array($status, ['paused', 'archived', 'inactive'], true)) {
                $query->where('is_active', false);
            }
        }

        // Filter: Response Hours
        if ($request->filled('response_max')) {
            $query->where('response_time_hours', '<=', (int) $request->input('response_max'));
        }

        // Filter: Resolution Hours
        if ($request->filled('resolution_max')) {
            $query->where('resolution_time_hours', '<=', (int) $request->input('resolution_max'));
        }

        // Sorting
        $sortColumn = $request->input('sort', 'name');
        $sortDirection = strtolower($request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $allowedSorts = [
            'name' => 'name',
            'tier' => 'client_tier',
            'priority' => 'priority',
            'response' => 'response_time_hours',
            'resolution' => 'resolution_time_hours',
            'created' => 'created_at',
            'updated' => 'updated_at',
            'active' => 'is_active',
        ];

        $dbSortField = $allowedSorts[$sortColumn] ?? 'name';
        $query->orderBy($dbSortField, $sortDirection);

        $policies = $query->get();

        // 2. Compute Health Panel Stats across all policies
        $allPolicies = ($isAdmin ? SlaPolicy::withoutGlobalScopes() : SlaPolicy::query())
            ->withCount($this->ticketUsageCounts($isAdmin, full: false))
            ->get();

        $totalPoliciesCount = $allPolicies->count();
        $activePoliciesCount = $allPolicies->where('is_active', true)->count();
        $inactivePoliciesCount = $allPolicies->where('is_active', false)->count();

        $avgResponseTarget = $activePoliciesCount > 0
            ? round($allPolicies->where('is_active', true)->avg('response_time_hours'), 1)
            : 0;

        $avgResolutionTarget = $activePoliciesCount > 0
            ? round($allPolicies->where('is_active', true)->avg('resolution_time_hours'), 1)
            : 0;

        $mostUsedPolicy = $allPolicies->sortByDesc('total_tickets_count')->first();

        // Highest Breach Rate Policy
        $highestBreachPolicy = $allPolicies->filter(fn ($p) => $p->total_tickets_count > 0)
            ->sortByDesc(fn ($p) => $p->breached_tickets_count / max(1, $p->total_tickets_count))
            ->first();

        $highestBreachRate = $highestBreachPolicy && $highestBreachPolicy->total_tickets_count > 0
            ? round(($highestBreachPolicy->breached_tickets_count / $highestBreachPolicy->total_tickets_count) * 100, 1)
            : 0;

        // 3. Grouped Policies by Tier for Tier Workstations
        $grouped = [];
        foreach (SlaPolicy::TIERS as $tier) {
            $grouped[$tier] = [];
            foreach (SlaPolicy::PRIORITIES as $priority) {
                $grouped[$tier][$priority] = $allPolicies->first(
                    fn (SlaPolicy $p) => $p->client_tier === $tier && $p->priority === $priority
                );
            }
        }

        // Prepare JSON structure for Alpine JS registry
        $alpinePolicies = $policies->map(function (SlaPolicy $p) use ($isAdmin) {
            $routePrefix = $isAdmin ? 'admin.' : '';

            // The admin surface is read-only, so it gets the workspace instead of
            // the row controls. Editing happens inside the workspace — see the
            // admin view's copy about Impersonate.
            $surface = $isAdmin
                ? [
                    'workspace' => $p->tenant?->name ?? '—',
                    'workspace_url' => $p->tenant_id ? route('admin.tenants.show', $p->tenant_id) : '#',
                ]
                : [
                    'edit_url' => $p->client_tier && Route::has($routePrefix.'sla.edit-tier')
                        ? route($routePrefix.'sla.edit-tier', ['tier' => $p->client_tier])
                        : '#',
                    'toggle_url' => Route::has($routePrefix.'sla.toggle')
                        ? route($routePrefix.'sla.toggle', $p)
                        : '#',
                    'delete_url' => Route::has($routePrefix.'sla.destroy')
                        ? route($routePrefix.'sla.destroy', $p)
                        : '#',
                ];

            return $surface + [
                'id' => $p->id,
                'name' => $p->name,
                'client_tier' => $p->client_tier,
                'priority' => $p->priority,
                'response_time_hours' => $p->response_time_hours,
                'resolution_time_hours' => $p->resolution_time_hours,
                'is_active' => (bool) $p->is_active,
                'description' => $p->description ?? '',
                'updated_at' => $p->updated_at ? $p->updated_at->diffForHumans() : 'Never',
                'created_at' => $p->created_at ? $p->created_at->format('Y-m-d H:i') : 'N/A',
                'total_tickets_count' => $p->total_tickets_count ?? 0,
                'active_tickets_count' => $p->active_tickets_count ?? 0,
                'closed_tickets_count' => $p->closed_tickets_count ?? 0,
                'breached_tickets_count' => $p->breached_tickets_count ?? 0,
            ];
        })->values()->all();

        return view($isAdmin ? 'admin.sla.index' : 'sla.index', [
            'adminOps' => $isAdmin ? $this->adminOps($slaService, $allPolicies) : null,
            'grouped' => $grouped,
            'tiers' => SlaPolicy::TIERS,
            'priorities' => SlaPolicy::PRIORITIES,
            'hasAny' => $allPolicies->isNotEmpty(),
            // Admin is cross-tenant and has no workspace calendar to show; the card is
            // rendered on the tenant surface only.
            'calendar' => $isAdmin ? null : SlaCalendar::query()->default()->first(),
            'businessTargetCount' => $isAdmin ? 0 : $allPolicies->reduce(
                fn (int $carry, $p) => $carry
                    + ($p->response_hours_mode === 'business' ? 1 : 0)
                    + ($p->resolution_hours_mode === 'business' ? 1 : 0),
                0
            ),
            'policiesList' => $policies,
            'alpinePolicies' => $alpinePolicies,
            'healthStats' => [
                'totalPolicies' => $totalPoliciesCount,
                'activePolicies' => $activePoliciesCount,
                'inactivePolicies' => $inactivePoliciesCount,
                'avgResponseTarget' => $avgResponseTarget,
                'avgResolutionTarget' => $avgResolutionTarget,
                'mostUsedPolicy' => $mostUsedPolicy ? $mostUsedPolicy->name : 'None',
                'mostUsedCount' => $mostUsedPolicy ? $mostUsedPolicy->total_tickets_count : 0,
                'highestBreachPolicy' => $highestBreachPolicy ? $highestBreachPolicy->name : 'None',
                'highestBreachRate' => $highestBreachRate,
            ],
            'filters' => [
                'search' => $request->input('search', ''),
                'tier' => $request->input('tier', 'All'),
                'priority' => $request->input('priority', 'All'),
                'status' => $request->input('status', 'All'),
                'sort' => $sortColumn,
                'direction' => $sortDirection,
            ],
        ]);
    }

    /**
     * The withCount() selects for policy ticket usage.
     *
     * The relation query inside a withCount() closure carries Ticket's own global
     * scope, so bypassing the scope on the outer SlaPolicy query alone would leave
     * deterministic rows beside session-dependent counts — which looks fixed and
     * isn't.
     */
    private function ticketUsageCounts(bool $isAdmin, bool $full = true): array
    {
        $scoped = fn ($q) => $isAdmin ? $q->withoutGlobalScopes() : $q;

        $breached = fn ($q) => $scoped($q)->where(function ($q2) {
            $q2->where('resolution_due_at', '<', now())
                ->orWhere(function ($q3) {
                    $q3->whereNull('first_response_at')
                        ->where('response_due_at', '<', now());
                });
        });

        if (! $full) {
            return [
                'tickets as total_tickets_count' => $scoped,
                'tickets as breached_tickets_count' => $breached,
            ];
        }

        return [
            'tickets as total_tickets_count' => $scoped,
            'tickets as active_tickets_count' => fn ($q) => $scoped($q)->open(),
            'tickets as closed_tickets_count' => fn ($q) => $scoped($q)->closed(),
            'tickets as breached_tickets_count' => $breached,
        ];
    }

    /**
     * Everything the admin SLA page shows that is derived from tickets.
     *
     * Built here rather than in the Blade view: the view used to call SlaService
     * itself, which is why the controller's payload looked complete while the page
     * was showing figures nobody had chosen the scope of.
     *
     * @param  Collection<int, SlaPolicy>  $allPolicies
     */
    private function adminOps(SlaService $slaService, $allPolicies): array
    {
        // Explicit UTC by choice, not for want of a helper: this window has to agree
        // with SlaService's own wall-clock `resolution_due_at < now()` comparisons, so
        // shifting it into anyone's display zone would put the two out of step again.
        // AdminTime exists for admin *display*; query boundaries on this page stay UTC.
        // The page prints the window it used.
        $to = Carbon::now('UTC');
        $from = $to->copy()->subDays(30);

        $report = $slaService->getComplianceReport($from, $to, null);

        // A percentage over an empty denominator is as invented as the 98.4 that used
        // to be hard-coded here. Uncomputable is reported as uncomputable.
        $rate = fn (int $met, int $missed) => ($met + $missed) > 0
            ? round(($met / ($met + $missed)) * 100, 1)
            : null;

        $slots = SlaPolicy::matrixSlotCount();
        $workspacesTotal = Tenant::count();
        $workspacesCovered = SlaPolicy::withoutGlobalScopes()
            ->whereNotNull('client_tier')
            ->whereNotNull('priority')
            ->selectRaw('tenant_id, COUNT(DISTINCT client_tier, priority) as slots')
            ->groupBy('tenant_id')
            ->having('slots', '>=', $slots)
            ->get()
            ->count();

        // Cross-tenant, "the premium/high policy" is not a thing: $grouped would pick
        // whichever workspace sorts first. Report the aggregate that is actually true.
        $tierCells = [];
        foreach (SlaPolicy::TIERS as $tier) {
            foreach (SlaPolicy::PRIORITIES as $priority) {
                $cell = $allPolicies->filter(
                    fn (SlaPolicy $p) => $p->client_tier === $tier && $p->priority === $priority
                );

                $tierCells[$tier][$priority] = [
                    'workspaces' => $cell->pluck('tenant_id')->unique()->count(),
                    'avg_response' => $cell->isNotEmpty() ? round($cell->avg('response_time_hours'), 1) : null,
                    'avg_resolution' => $cell->isNotEmpty() ? round($cell->avg('resolution_time_hours'), 1) : null,
                ];
            }
        }

        // Weekly trend buckets group an already-fetched collection in PHP, so no SQL
        // date boundary is involved.
        $weeks = [];
        $weeklyCompliance = [];
        $weeklyResp = [];
        $weeklyRes = [];

        $weekly = $report['rows']
            ->groupBy(fn (array $row) => $row['ticket']->closed_at->format('Y-W'))
            ->sortKeys()
            ->take(6);

        foreach ($weekly as $weekKey => $group) {
            $met = $group->where('resolution_status', 'met')->count();
            $missed = $group->where('resolution_status', 'missed')->count();

            $weeks[] = 'Wk '.substr((string) $weekKey, 5);
            $weeklyCompliance[] = $rate($met, $missed);
            $weeklyResp[] = round($group->pluck('response_hours')->filter(fn ($v) => $v !== null)->avg() ?? 0, 1);
            $weeklyRes[] = round($group->pluck('resolution_hours')->filter(fn ($v) => $v !== null)->avg() ?? 0, 1);
        }

        $nearBreach = Ticket::withoutGlobalScopes()
            ->open()
            ->notMerged()
            ->notSpam()
            ->where(function ($q) {
                $q->whereBetween('resolution_due_at', [now(), now()->addHours(4)])
                    ->orWhere(function ($q2) {
                        $q2->whereNull('first_response_at')
                            ->whereBetween('response_due_at', [now(), now()->addHours(2)]);
                    });
            })
            // Same trap as the compliance report's eager loads: an unscoped ticket
            // query with scoped relations shows a foreign tenant's client as N/A
            // once an admin has a tenant in session.
            ->with([
                'client' => fn ($q) => $q->withoutGlobalScopes(),
                'assignee',
                'department' => fn ($q) => $q->withoutGlobalScopes(),
            ])
            ->get();

        return [
            'scope_label' => 'All workspaces',
            'window_from' => $from->toDateString(),
            'window_to' => $to->toDateString(),
            'compliance' => $report,
            'response_compliance' => $rate($report['response_met'], $report['response_missed']),
            'resolution_compliance' => $rate($report['resolution_met'], $report['resolution_missed']),
            'avg_response_hours' => $report['avg_response_hours'],
            'avg_resolution_hours' => $report['avg_resolution_hours'],
            'closed_without_sla' => $report['closed_without_sla'],
            'workspaces_total' => $workspacesTotal,
            'workspaces_fully_covered' => $workspacesCovered,
            'tier_cells' => $tierCells,
            'overdue' => $slaService->getOverdueTickets(),
            'near_breach' => $nearBreach,
            'near_breach_alpine' => $nearBreach->map(function (Ticket $t) {
                $due = $t->resolution_due_at ?? $t->response_due_at;

                return [
                    'id' => $t->id,
                    'number' => $t->ticket_number,
                    'subject' => $t->subject,
                    'priority' => $t->priority,
                    'client' => $t->client?->name ?? 'N/A',
                    'agent' => $t->assignee?->name ?? 'Unassigned',
                    'due_timestamp' => $due ? $due->timestamp : 0,
                    'due_formatted' => $due ? $due->format('H:i:s') : 'N/A',
                ];
            })->values()->all(),
            'weeks' => $weeks,
            'weekly_compliance' => $weeklyCompliance,
            'weekly_resp' => $weeklyResp,
            'weekly_res' => $weeklyRes,
        ];
    }

    /**
     * Store a newly created SLA Policy.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->checkPermission('create sla policies');

        $tenantId = session('current_tenant_id');

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sla_policies')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'client_tier' => ['required', 'string', Rule::in(SlaPolicy::TIERS)],
            'priority' => ['required', 'string', Rule::in(SlaPolicy::PRIORITIES)],
            'response_time_hours' => ['required', 'integer', 'min:0', 'lte:resolution_time_hours'],
            'resolution_time_hours' => ['required', 'integer', 'min:1', 'gte:response_time_hours'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'response_time_hours.lte' => 'Response time target cannot exceed resolution time target.',
            'resolution_time_hours.gte' => 'Resolution time target must be greater than or equal to response time.',
            'resolution_time_hours.min' => 'Resolution time target must be at least 1 hour.',
        ]);

        // Duplicate Combination Check for (client_tier, priority)
        $existing = SlaPolicy::query()
            ->where('client_tier', $validated['client_tier'])
            ->where('priority', $validated['priority'])
            ->first();

        if ($existing && ! $request->boolean('overwrite')) {
            if ($request->wantsJson()) {
                return response()->json([
                    'warning' => 'duplicate',
                    'message' => "An SLA policy '{$existing->name}' already exists for {$validated['client_tier']} tier and {$validated['priority']} priority.",
                    'existing_id' => $existing->id,
                ], 409);
            }

            return redirect()->back()
                ->withInput()
                ->with('duplicate_warning', "An SLA policy '{$existing->name}' already exists for {$validated['client_tier']} tier and {$validated['priority']} priority. Confirm overwrite to update it.");
        }

        $policy = SlaPolicy::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'client_tier' => $validated['client_tier'],
                'priority' => $validated['priority'],
            ],
            [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'response_time_hours' => (int) $validated['response_time_hours'],
                'resolution_time_hours' => (int) $validated['resolution_time_hours'],
                'is_active' => $request->boolean('is_active', true),
            ]
        );

        $routePrefix = request()->routeIs('admin.*') ? 'admin.' : '';
        $redirectRoute = Route::has($routePrefix.'sla.index') ? $routePrefix.'sla.index' : 'sla.index';

        if ($request->wantsJson()) {
            return response()->json(['message' => 'SLA Policy created successfully.', 'policy' => $policy]);
        }

        return redirect()->route($redirectRoute)->with('success', "SLA Policy '{$policy->name}' created successfully.");
    }

    /**
     * Update an existing SLA Policy.
     */
    public function update(Request $request, SlaPolicy $policy): RedirectResponse|JsonResponse
    {
        $this->checkPermission('update sla policies');

        $tenantId = session('current_tenant_id');

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sla_policies')->where(fn ($q) => $q->where('tenant_id', $tenantId))->ignore($policy->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'client_tier' => ['required', 'string', Rule::in(SlaPolicy::TIERS)],
            'priority' => ['required', 'string', Rule::in(SlaPolicy::PRIORITIES)],
            'response_time_hours' => ['required', 'integer', 'min:0', 'lte:resolution_time_hours'],
            'resolution_time_hours' => ['required', 'integer', 'min:1', 'gte:response_time_hours'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'response_time_hours.lte' => 'Response time target cannot exceed resolution time target.',
            'resolution_time_hours.gte' => 'Resolution time target must be greater than or equal to response time.',
            'resolution_time_hours.min' => 'Resolution time target must be at least 1 hour.',
        ]);

        // Duplicate combination check (excluding current policy)
        $existing = SlaPolicy::query()
            ->where('client_tier', $validated['client_tier'])
            ->where('priority', $validated['priority'])
            ->where('id', '!=', $policy->id)
            ->first();

        if ($existing && ! $request->boolean('overwrite')) {
            if ($request->wantsJson()) {
                return response()->json([
                    'warning' => 'duplicate',
                    'message' => "Another SLA policy '{$existing->name}' already exists for {$validated['client_tier']} tier and {$validated['priority']} priority.",
                ], 409);
            }

            return redirect()->back()
                ->withInput()
                ->with('duplicate_warning', "Another SLA policy '{$existing->name}' already exists for {$validated['client_tier']} tier and {$validated['priority']} priority.");
        }

        $policy->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'client_tier' => $validated['client_tier'],
            'priority' => $validated['priority'],
            'response_time_hours' => (int) $validated['response_time_hours'],
            'resolution_time_hours' => (int) $validated['resolution_time_hours'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        $routePrefix = request()->routeIs('admin.*') ? 'admin.' : '';
        $redirectRoute = Route::has($routePrefix.'sla.index') ? $routePrefix.'sla.index' : 'sla.index';

        if ($request->wantsJson()) {
            return response()->json(['message' => 'SLA Policy updated successfully.', 'policy' => $policy]);
        }

        return redirect()->route($redirectRoute)->with('success', "SLA Policy '{$policy->name}' updated successfully.");
    }

    /**
     * Toggle active/archived status of an SLA Policy.
     */
    public function toggle(Request $request, SlaPolicy $policy): RedirectResponse|JsonResponse
    {
        $this->checkPermission('update sla policies');

        $policy->update([
            'is_active' => ! $policy->is_active,
        ]);

        $statusText = $policy->is_active ? 'activated' : 'archived';

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "SLA Policy '{$policy->name}' has been {$statusText}.",
                'is_active' => $policy->is_active,
            ]);
        }

        return redirect()->back()->with('success', "SLA Policy '{$policy->name}' has been {$statusText}.");
    }

    /**
     * Delete an SLA policy safely (only if no tickets assigned).
     */
    public function destroy(Request $request, SlaPolicy $policy): RedirectResponse|JsonResponse
    {
        $this->checkPermission('delete sla policies');

        $assignedTicketsCount = $policy->tickets()->count();

        if ($assignedTicketsCount > 0) {
            $msg = "This SLA Policy is currently assigned to {$assignedTicketsCount} ticket".($assignedTicketsCount === 1 ? '' : 's').'. Deletion is prevented to preserve historical SLA tracking. You can Archive or Deactivate this policy instead.';

            if ($request->wantsJson()) {
                return response()->json([
                    'error' => 'policy_in_use',
                    'message' => $msg,
                    'assigned_count' => $assignedTicketsCount,
                ], 422);
            }

            return redirect()->back()->with('error', $msg);
        }

        $name = $policy->name;
        $policy->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => "SLA Policy '{$name}' deleted successfully."]);
        }

        return redirect()->back()->with('success', "SLA Policy '{$name}' deleted successfully.");
    }

    /**
     * Perform bulk actions on selected policies.
     */
    public function bulkAction(Request $request): RedirectResponse|JsonResponse
    {
        $this->checkPermission('update sla policies');

        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in(['activate', 'deactivate', 'archive', 'delete', 'export'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:sla_policies,id'],
        ]);

        $action = $validated['action'];
        $ids = $validated['ids'];

        if ($action === 'activate') {
            SlaPolicy::whereIn('id', $ids)->update(['is_active' => true]);
            $msg = 'Selected SLA policies activated successfully.';
        } elseif (in_array($action, ['deactivate', 'archive'], true)) {
            SlaPolicy::whereIn('id', $ids)->update(['is_active' => false]);
            $msg = 'Selected SLA policies archived/deactivated successfully.';
        } elseif ($action === 'delete') {
            $policies = SlaPolicy::whereIn('id', $ids)->withCount('tickets')->get();
            $deletedCount = 0;
            $skippedCount = 0;

            foreach ($policies as $p) {
                if ($p->tickets_count === 0) {
                    $p->delete();
                    $deletedCount++;
                } else {
                    $skippedCount++;
                }
            }

            $msg = "Deleted {$deletedCount} unused policy/policies.";
            if ($skippedCount > 0) {
                $msg .= " Skipped {$skippedCount} policy/policies because they are assigned to existing tickets.";
            }
        } elseif ($action === 'export') {
            return $this->exportCSV($ids);
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => $msg]);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Export SLA policies to CSV format.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->checkPermission('view sla policies');

        $ids = $request->input('ids');
        $idArray = is_array($ids) ? $ids : (is_string($ids) && strlen($ids) > 0 ? explode(',', $ids) : null);

        return $this->exportCSV($idArray, $request->routeIs('admin.*'));
    }

    /**
     * Helper to generate streamed CSV.
     *
     * Scope is chosen the same way index() chooses it. This used to build from
     * SlaPolicy::query() and so was cross-tenant only while no tenant happened to
     * be in session — the download changed with the admin's browsing history.
     */
    private function exportCSV(?array $ids = null, bool $isAdmin = false): StreamedResponse
    {
        $query = ($isAdmin ? SlaPolicy::withoutGlobalScopes() : SlaPolicy::query())
            ->withCount($this->ticketUsageCounts($isAdmin));

        if ($isAdmin) {
            $query->with(['tenant:id,name,slug']);
        }

        if ($ids) {
            $query->whereIn('id', $ids);
        }

        $policies = $query->orderBy('client_tier')->orderBy('priority')->get();

        $fileName = 'sla_policies_export_'.now()->format('Y_m_d_His').'.csv';

        $headers = [
            'Content-type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$fileName}",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = [
            'ID',
            ...($isAdmin ? ['Workspace'] : []),
            'Policy Name',
            'Client Tier',
            'Ticket Priority',
            'Response Target (Hours)',
            'Resolution Target (Hours)',
            'Status',
            'Total Tickets',
            'Active Tickets',
            'Closed Tickets',
            'Breached Tickets',
            'Description',
            'Created Date',
        ];

        $callback = function () use ($policies, $columns, $isAdmin) {
            $file = fopen('php://output', 'w');
            // Write BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, $columns);

            foreach ($policies as $p) {
                fputcsv($file, [
                    $p->id,
                    ...($isAdmin ? [$p->tenant?->name ?? '—'] : []),
                    $p->name,
                    strtoupper($p->client_tier),
                    strtoupper($p->priority),
                    $p->response_time_hours,
                    $p->resolution_time_hours,
                    $p->is_active ? 'ACTIVE' : 'PAUSED',
                    $p->total_tickets_count ?? 0,
                    $p->active_tickets_count ?? 0,
                    $p->closed_tickets_count ?? 0,
                    $p->breached_tickets_count ?? 0,
                    $p->description ?? '',
                    $p->created_at ? $p->created_at->format('Y-m-d H:i') : '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Normalize tier name to standard SLA tiers (basic, premium, enterprise).
     */
    private function normalizeTier(string $tier): string
    {
        $tier = strtolower($tier);
        $map = [
            'starter' => 'basic',
            'standard' => 'basic',
            'business' => 'premium',
            'pro' => 'premium',
            'growth' => 'premium',
        ];

        return $map[$tier] ?? $tier;
    }

    /**
     * Create/Update all 4 priority rows for a single tier.
     */
    public function editTier(string $tier): View
    {
        $this->checkPermission('update sla policies');

        $tier = $this->normalizeTier($tier);
        abort_unless(in_array($tier, SlaPolicy::TIERS, true), 404);

        $policies = SlaPolicy::query()
            ->where('client_tier', $tier)
            ->get()
            ->keyBy('priority');

        $defaults = SlaPolicy::STANDARD_DEFAULTS[$tier] ?? [];
        $rows = [];
        foreach (SlaPolicy::PRIORITIES as $priority) {
            $existing = $policies->get($priority);
            $rows[$priority] = [
                'response' => $existing?->response_time_hours ?? $defaults[$priority][0] ?? null,
                'resolution' => $existing?->resolution_time_hours ?? $defaults[$priority][1] ?? null,
                'is_active' => $existing ? $existing->is_active : true,
                // Default 'calendar' for a row that does not exist yet, matching the
                // column default. Nothing drifts onto business hours by accident.
                'response_mode' => $existing?->response_hours_mode ?? 'calendar',
                'resolution_mode' => $existing?->resolution_hours_mode ?? 'calendar',
            ];
        }

        $calendarUsable = $this->usableCalendar() !== null;

        // Tenant surface only: the admin group no longer registers sla.edit-tier,
        // because its form posted to a route that is gone.
        return view('sla.edit-tier', compact('tier', 'rows', 'calendarUsable'));
    }

    /**
     * Upsert all 4 priority rows for a tier.
     */
    public function updateTier(Request $request, string $tier): RedirectResponse
    {
        $this->checkPermission('update sla policies');

        $tier = $this->normalizeTier($tier);
        abort_unless(in_array($tier, SlaPolicy::TIERS, true), 404);

        $rules = [];
        $modeRule = ['required', Rule::in(SlaPolicy::HOURS_MODES)];
        foreach (SlaPolicy::PRIORITIES as $priority) {
            $rules["rows.{$priority}.response"] = ['required', 'integer', 'min:0', 'lte:rows.'.$priority.'.resolution'];
            $rules["rows.{$priority}.resolution"] = ['required', 'integer', 'min:1'];
            $rules["rows.{$priority}.response_mode"] = $modeRule;
            $rules["rows.{$priority}.resolution_mode"] = $modeRule;
        }

        $validated = $request->validate($rules, [
            'rows.*.response.lte' => 'Response time target cannot exceed resolution time target.',
            'rows.*.resolution.min' => 'Resolution time target must be at least 1 hour.',
        ]);

        // The disabled attribute on the option is a hint to a browser, not a guard. This
        // POST is the guard, and it is the same configuration-time block the calendar
        // editor applies in the other direction: business hours never becomes live
        // without working hours to measure it against.
        $wantsBusiness = collect($validated['rows'])
            ->contains(fn ($r) => ($r['response_mode'] ?? 'calendar') === 'business'
                               || ($r['resolution_mode'] ?? 'calendar') === 'business');

        if ($wantsBusiness && $this->usableCalendar() === null) {
            return back()->withInput()->withErrors([
                'rows' => __('Set up a business-hours calendar before using business hours.'),
            ]);
        }

        $tenantId = session('current_tenant_id');

        foreach (SlaPolicy::PRIORITIES as $priority) {
            $response = (int) $validated['rows'][$priority]['response'];
            $resolution = (int) $validated['rows'][$priority]['resolution'];
            $active = $request->boolean("rows.{$priority}.is_active", true);

            SlaPolicy::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'client_tier' => $tier,
                    'priority' => $priority,
                ],
                [
                    'name' => ucfirst($tier).' - '.ucfirst($priority),
                    'response_time_hours' => $response,
                    'resolution_time_hours' => $resolution,
                    'response_hours_mode' => $validated['rows'][$priority]['response_mode'],
                    'resolution_hours_mode' => $validated['rows'][$priority]['resolution_mode'],
                    'is_active' => $active,
                ]
            );
        }

        $routePrefix = request()->routeIs('admin.*') ? 'admin.' : '';
        $redirectRoute = Route::has($routePrefix.'sla.index') ? $routePrefix.'sla.index' : 'sla.index';

        $redirect = redirect()->route($redirectRoute)->with('success', ucfirst($tier).' SLA policies saved.');

        // Warn, do not block. Enterprise tier is a 24x7 promise by product decision, but
        // a tenant whose enterprise clients are genuinely weekday-only is entitled to say
        // so — encoding pricing policy in a validator ages badly. The save succeeds.
        if ($tier === 'enterprise' && $wantsBusiness) {
            $redirect->with('warning', __('Enterprise-tier clients are promised round-the-clock support. Business hours pause the clock at night and at weekends.'));
        }

        return $redirect;
    }

    /**
     * The workspace's business-hours calendar, or null when there is nothing usable to
     * measure a business-hours target against.
     *
     * One calendar per workspace for now; the tables allow more and a later commit can
     * add a picker additively. An ordinary scoped query, so no id is ever accepted from
     * a request and cross-tenant reads are impossible by construction.
     */
    private function usableCalendar(): ?SlaCalendar
    {
        $calendar = SlaCalendar::query()->default()->first();

        return $calendar && $calendar->hasOpenWindows() ? $calendar : null;
    }

    /**
     * Edit the workspace's business-hours calendar.
     *
     * Deliberately not under /settings: the calendar is SLA configuration, it is
     * Business+ like the rest of sla.*, and separating it from the only numbers it
     * affects is how an admin ends up believing one clock and getting the other.
     */
    public function editCalendar(): View
    {
        $this->checkPermission('update sla policies');

        $calendar = SlaCalendar::query()->default()->with(['hours', 'holidays'])->first();

        // Keyed by ISO weekday so the view renders seven rows whether or not the calendar
        // exists. A day with no windows is a closed day.
        $windows = [];
        foreach (range(1, 7) as $day) {
            $windows[$day] = ($calendar?->hours ?? collect())
                ->where('day_of_week', $day)
                ->sortBy('start_minute')
                ->map(fn (SlaCalendarHour $h) => [
                    'start' => $this->formatMinute($h->start_minute),
                    'end' => $this->formatMinute($h->end_minute),
                ])
                ->values()
                ->all();
        }

        // Never an empty grid on first visit. A workspace opening this page is trying to
        // answer "which days are we available, and between what times" — presenting the
        // ordinary answer pre-filled makes it one click, not seven rows of typing. It is
        // only a prefill: nothing is written until they save, so a GET has no side effect.
        if (! $calendar) {
            foreach (range(1, 5) as $day) {
                $windows[$day] = [['start' => '09:00', 'end' => '17:00']];
            }
        }

        // Open/closed is its own visible state, not something inferred from two blank
        // time fields. Unticking Saturday and Sunday is the obvious action, so it has to
        // look like one.
        $dayOpen = [];
        foreach (range(1, 7) as $day) {
            $dayOpen[$day] = $windows[$day] !== [];
        }

        $holidays = ($calendar?->holidays ?? collect())
            ->sortBy(fn ($h) => sprintf('%02d%02d', $h->month, $h->day))
            ->map(fn (SlaCalendarHoliday $h) => [
                'name' => $h->name,
                'month' => $h->month,
                'day' => $h->day,
                'year' => $h->year,
                'recurring' => $h->year === null,
            ])
            ->values()
            ->all();

        $timezone = $calendar?->timezone ?? TenantTime::timezone();

        $businessTargetCount = SlaPolicy::query()->where('response_hours_mode', 'business')->count()
            + SlaPolicy::query()->where('resolution_hours_mode', 'business')->count();

        $consequence = $this->describeTarget($timezone, $windows, $holidays, 48);

        return view('sla.calendar', compact(
            'calendar', 'windows', 'dayOpen', 'holidays', 'timezone', 'businessTargetCount', 'consequence'
        ));
    }

    /**
     * Replace the whole week and the whole holiday list in one transaction.
     *
     * Replace-all, not per-row CRUD across three tables to edit one logical record —
     * the same shape the tier editor already uses for its four policies.
     */
    public function updateCalendar(UpdateSlaCalendarRequest $request): RedirectResponse
    {
        $this->checkPermission('update sla policies');

        $tenantId = session('current_tenant_id');
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated, $tenantId) {
            $calendar = SlaCalendar::query()->default()->first()
                ?? new SlaCalendar(['tenant_id' => $tenantId, 'name' => 'Business hours', 'is_default' => true]);

            $calendar->timezone = $validated['timezone'];
            $calendar->save();

            $originalHours = $calendar->exists ? $calendar->hours()->count() : 0;
            $originalHolidays = $calendar->exists ? $calendar->holidays()->count() : 0;

            $calendar->hours()->delete();
            $calendar->holidays()->delete();

            $openDays = (array) $request->input('days', []);

            foreach ((array) $request->input('windows', []) as $day => $rows) {
                $day = (int) $day;
                // A day switched off is closed whatever its time fields still say. The
                // toggle is the answer to "do we work weekends"; the times only qualify it.
                if ($day < 1 || $day > 7 || ! is_array($rows) || empty($openDays[$day])) {
                    continue;
                }

                foreach ($rows as $row) {
                    $start = $this->parseMinute($row['start'] ?? null);
                    $end = $this->parseMinute($row['end'] ?? null);

                    if ($start === null || $end === null || $end <= $start) {
                        continue;
                    }

                    $calendar->hours()->create([
                        'tenant_id' => $tenantId,
                        'day_of_week' => $day,
                        'start_minute' => $start,
                        'end_minute' => $end,
                    ]);
                }
            }

            foreach ((array) $request->input('holidays', []) as $row) {
                if (blank($row['name'] ?? null)) {
                    continue;
                }

                $calendar->holidays()->create([
                    'tenant_id' => $tenantId,
                    'name' => $row['name'],
                    'month' => (int) $row['month'],
                    'day' => (int) $row['day'],
                    // Recurring is the default because rotting must be the deliberate
                    // act: the failure mode here is a calendar nobody repopulates in
                    // January and agents flagged as breaching on days nobody worked.
                    'year' => ($row['recurring'] ?? false) ? null : (int) $row['year'],
                ]);
            }

            // The audit answer to "who deleted our Saturday hours". Child rows carry no
            // LogsActivity by design, and a week whose shifts all moved leaves the
            // parent's own attributes untouched — LogsActivity skips an update with no
            // field diff, and touch() only moves updated_at, which it always ignores. So
            // the entry is written explicitly, which is what ActivityLogger is for.
            if (! $calendar->wasRecentlyCreated) {
                ActivityLogger::log($calendar, 'updated', 'SLA business hours updated.', [
                    'hours' => ['old' => $originalHours, 'new' => $calendar->hours()->count()],
                    'holidays' => ['old' => $originalHolidays, 'new' => $calendar->holidays()->count()],
                ]);
            }
        });

        return redirect()->route('sla.calendar.edit')
            ->with('success', __('Business hours saved. Tickets already open keep the deadline they were given.'));
    }

    /**
     * "A 48 business-hour target on this calendar is about 6 working days."
     *
     * The sentence the whole feature exists to answer, computed rather than asserted —
     * SlaClock is the same arithmetic the deadlines use, so the page cannot claim one
     * thing while the tickets do another.
     *
     * @param  array<int, list<array{start:string,end:string}>>  $windows
     * @param  list<array<string, mixed>>  $holidays
     * @return array{working_days: float, calendar_days: int}|null
     */
    private function describeTarget(string $timezone, array $windows, array $holidays, int $hours): ?array
    {
        $flat = [];
        $weeklyMinutes = 0;
        $openDayCount = 0;

        foreach ($windows as $day => $rows) {
            if ($rows === []) {
                continue;
            }

            $openDayCount++;

            foreach ($rows as $row) {
                $start = $this->parseMinute($row['start'] ?? null);
                $end = $this->parseMinute($row['end'] ?? null);

                if ($start === null || $end === null || $end <= $start) {
                    continue;
                }

                $flat[] = ['day' => (int) $day, 'start' => $start, 'end' => $end];
                $weeklyMinutes += $end - $start;
            }
        }

        if ($flat === [] || $weeklyMinutes === 0 || $openDayCount === 0) {
            return null;
        }

        $clock = new SlaClock($timezone, $flat, array_map(fn ($h) => [
            'month' => (int) ($h['month'] ?? 0),
            'day' => (int) ($h['day'] ?? 0),
            'year' => ($h['recurring'] ?? false) ? null : (($h['year'] ?? null) !== null ? (int) $h['year'] : null),
        ], $holidays));

        // Measured from the next open minute, not from "now": starting the example in the
        // middle of a closed Sunday would quietly add a weekend to the answer.
        $start = $clock->nextOpenAt(now());

        if (! $start) {
            return null;
        }

        $due = $clock->addBusinessHours($start, $hours);

        return [
            'working_days' => round($hours * 60 / ($weeklyMinutes / $openDayCount), 1),
            'calendar_days' => (int) ceil($start->diffInDays($due, false)),
        ];
    }

    private function formatMinute(int $minute): string
    {
        return sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
    }

    private function parseMinute(?string $time): ?int
    {
        if (! $time || ! preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m)) {
            return null;
        }

        $minute = ((int) $m[1]) * 60 + (int) $m[2];

        return ($minute < 0 || $minute > 1440) ? null : $minute;
    }

    /**
     * Seed industry-standard defaults for any missing (tier, priority) pairs.
     */
    public function seedDefaults(): RedirectResponse
    {
        $this->checkPermission('create sla policies');

        $tenantId = session('current_tenant_id');
        $count = SlaPolicy::seedStandardDefaults($tenantId);

        $routePrefix = request()->routeIs('admin.*') ? 'admin.' : '';
        $redirectRoute = Route::has($routePrefix.'sla.index') ? $routePrefix.'sla.index' : 'sla.index';

        return redirect()->route($redirectRoute)
            ->with('success', $count === 0
                ? 'Standard policies already in place.'
                : "Seeded {$count} standard SLA policies.");
    }

    /**
     * Delete all policies for a tier.
     */
    public function destroyTier(string $tier): RedirectResponse
    {
        $this->checkPermission('delete sla policies');

        $tier = $this->normalizeTier($tier);
        abort_unless(in_array($tier, SlaPolicy::TIERS, true), 404);

        $policies = SlaPolicy::where('client_tier', $tier)->get();
        $deleted = 0;
        $skipped = 0;

        foreach ($policies as $p) {
            if ($p->tickets()->count() === 0) {
                $p->delete();
                $deleted++;
            } else {
                $skipped++;
            }
        }

        $msg = "{$deleted} SLA policies for ".ucfirst($tier).' tier removed.';
        if ($skipped > 0) {
            $msg .= " Skipped {$skipped} policy/policies because they are assigned to existing tickets.";
        }

        $routePrefix = request()->routeIs('admin.*') ? 'admin.' : '';
        $redirectRoute = Route::has($routePrefix.'sla.index') ? $routePrefix.'sla.index' : 'sla.index';

        return redirect()->route($redirectRoute)->with('success', $msg);
    }
}
