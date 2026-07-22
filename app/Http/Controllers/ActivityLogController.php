<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->checkPermission('view activity logs');

        $query = ActivityLog::query()
            ->with('user')
            ->latest();

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->input('subject_type'));
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

        $actionTypes = ActivityLog::query()
            ->distinct()
            ->pluck('action')
            ->sort()
            ->values();

        $subjectTypes = ActivityLog::query()
            ->distinct()
            ->pluck('subject_type')
            ->sort()
            ->values();

        $users = User::query()
            ->whereHas('tenants', fn ($q) => $q->where('tenant_id', session('current_tenant_id')))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('activity-logs.index', compact('logs', 'actionTypes', 'subjectTypes', 'users'));
    }
}
