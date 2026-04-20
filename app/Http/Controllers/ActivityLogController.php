<?php

namespace App\Http\Controllers;

use App\Enums\PlanFeature;
use App\Models\TicketHistory;
use App\Services\PlanService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function __construct(private PlanService $planService) {}

    /**
     * Display activity logs for the current tenant.
     */
    public function index(Request $request): View
    {
        $this->checkPermission('view activity logs');

        // Build the list of action types the tenant's plan does NOT cover,
        // so those rows + filter options stay hidden.
        $hiddenActions = [];
        if (! $this->planService->currentTenantHasFeature(PlanFeature::TicketReopening)) {
            $hiddenActions[] = 'reopened';
        }
        if (! $this->planService->currentTenantHasFeature(PlanFeature::TicketMerging)) {
            $hiddenActions[] = 'merged';
            $hiddenActions[] = 'unmerged';
        }

        $query = TicketHistory::query()
            ->with(['ticket', 'user'])
            ->whereHas('ticket')
            ->when(! empty($hiddenActions), fn ($q) => $q->whereNotIn('action', $hiddenActions))
            ->latest();

        if ($request->filled('action') && ! in_array($request->input('action'), $hiddenActions, true)) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        $logs = $query->paginate(50)->withQueryString();

        $actionTypes = TicketHistory::query()
            ->whereHas('ticket')
            ->when(! empty($hiddenActions), fn ($q) => $q->whereNotIn('action', $hiddenActions))
            ->distinct()
            ->pluck('action')
            ->sort()
            ->values();

        $users = \App\Models\User::query()
            ->whereHas('tenants', fn ($q) => $q->where('tenant_id', session('current_tenant_id')))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('activity-logs.index', compact('logs', 'actionTypes', 'users'));
    }
}
