<?php

namespace App\Http\Controllers;

use App\Models\TicketHistory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    /**
     * Display activity logs for the current tenant.
     */
    public function index(Request $request): View
    {
        $query = TicketHistory::query()
            ->with(['ticket', 'user'])
            ->whereHas('ticket')
            ->latest();

        if ($request->filled('action')) {
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
