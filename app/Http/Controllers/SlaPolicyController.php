<?php

namespace App\Http\Controllers;

use App\Models\SlaPolicy;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SlaPolicyController extends Controller
{
    /**
     * Display SLA dashboard, health panel, registry, and tier workstations.
     */
    public function index(Request $request): View
    {
        $tenantId = session('current_tenant_id');

        // 1. Query policies with ticket usage counts
        $query = SlaPolicy::query()
            ->withCount([
                'tickets as total_tickets_count',
                'tickets as active_tickets_count' => fn ($q) => $q->open(),
                'tickets as closed_tickets_count' => fn ($q) => $q->closed(),
                'tickets as breached_tickets_count' => fn ($q) => $q->where(function ($q2) {
                    $q2->where('resolution_due_at', '<', now())
                        ->orWhere(function ($q3) {
                            $q3->whereNull('first_response_at')
                                ->where('response_due_at', '<', now());
                        });
                }),
            ]);

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
        $allPolicies = SlaPolicy::query()
            ->withCount([
                'tickets as total_tickets_count',
                'tickets as breached_tickets_count' => fn ($q) => $q->where(function ($q2) {
                    $q2->where('resolution_due_at', '<', now())
                        ->orWhere(function ($q3) {
                            $q3->whereNull('first_response_at')
                                ->where('response_due_at', '<', now());
                        });
                }),
            ])->get();

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
        $alpinePolicies = $policies->map(function (SlaPolicy $p) {
            $routePrefix = request()->routeIs('admin.*') ? 'admin.' : '';

            return [
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
        })->values()->all();

        return view('sla.index', [
            'grouped' => $grouped,
            'tiers' => SlaPolicy::TIERS,
            'priorities' => SlaPolicy::PRIORITIES,
            'hasAny' => $allPolicies->isNotEmpty(),
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
     * Store a newly created SLA Policy.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
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
        $ids = $request->input('ids');
        $idArray = is_array($ids) ? $ids : (is_string($ids) && strlen($ids) > 0 ? explode(',', $ids) : null);

        return $this->exportCSV($idArray);
    }

    /**
     * Helper to generate streamed CSV.
     */
    private function exportCSV(?array $ids = null): StreamedResponse
    {
        $query = SlaPolicy::query()->withCount([
            'tickets as total_tickets_count',
            'tickets as active_tickets_count' => fn ($q) => $q->open(),
            'tickets as closed_tickets_count' => fn ($q) => $q->closed(),
            'tickets as breached_tickets_count' => fn ($q) => $q->where(function ($q2) {
                $q2->where('resolution_due_at', '<', now())
                    ->orWhere(function ($q3) {
                        $q3->whereNull('first_response_at')
                            ->where('response_due_at', '<', now());
                    });
            }),
        ]);

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

        $callback = function () use ($policies, $columns) {
            $file = fopen('php://output', 'w');
            // Write BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, $columns);

            foreach ($policies as $p) {
                fputcsv($file, [
                    $p->id,
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
            ];
        }

        return view('sla.edit-tier', compact('tier', 'rows'));
    }

    /**
     * Upsert all 4 priority rows for a tier.
     */
    public function updateTier(Request $request, string $tier): RedirectResponse
    {
        $tier = $this->normalizeTier($tier);
        abort_unless(in_array($tier, SlaPolicy::TIERS, true), 404);

        $rules = [];
        foreach (SlaPolicy::PRIORITIES as $priority) {
            $rules["rows.{$priority}.response"] = ['required', 'integer', 'min:0', 'lte:rows.'.$priority.'.resolution'];
            $rules["rows.{$priority}.resolution"] = ['required', 'integer', 'min:1'];
        }

        $validated = $request->validate($rules, [
            'rows.*.response.lte' => 'Response time target cannot exceed resolution time target.',
            'rows.*.resolution.min' => 'Resolution time target must be at least 1 hour.',
        ]);

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
                    'is_active' => $active,
                ]
            );
        }

        $routePrefix = request()->routeIs('admin.*') ? 'admin.' : '';
        $redirectRoute = Route::has($routePrefix.'sla.index') ? $routePrefix.'sla.index' : 'sla.index';

        return redirect()->route($redirectRoute)->with('success', ucfirst($tier).' SLA policies saved.');
    }

    /**
     * Seed industry-standard defaults for any missing (tier, priority) pairs.
     */
    public function seedDefaults(): RedirectResponse
    {
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
