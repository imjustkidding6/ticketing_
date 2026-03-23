<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\TicketTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the personalized tenant dashboard.
     */
    public function index(): View
    {
        return view('dashboard', $this->getDashboardData());
    }

    /**
     * Return dashboard data as JSON for polling.
     */
    public function stats(): JsonResponse
    {
        return response()->json($this->getDashboardData());
    }

    /**
     * @return array<string, mixed>
     */
    private function getDashboardData(): array
    {
        $user = Auth::user();
        $userId = $user->id;
        $tenantId = session('current_tenant_id');
        $tenant = $tenantId ? \App\Models\Tenant::find($tenantId) : null;
        $role = $tenant ? $user->roleInTenant($tenant) : null;
        $isAdminOrOwner = in_array($role, ['owner', 'admin']);

        // ── My Ticket Stats ──
        $myTicketStats = [
            'open' => Ticket::query()->where('assigned_to', $userId)->whereIn('status', ['open', 'assigned'])->count(),
            'in_progress' => Ticket::query()->where('assigned_to', $userId)->where('status', 'in_progress')->count(),
            'closed_this_month' => Ticket::query()
                ->where('assigned_to', $userId)
                ->where('status', 'closed')
                ->where('closed_at', '>=', now()->startOfMonth())
                ->count(),
            'total_closed' => Ticket::query()->where('assigned_to', $userId)->where('status', 'closed')->count(),
        ];

        // ── My Performance (last 30 days) ──
        $myClosedRecent = Ticket::query()
            ->where('assigned_to', $userId)
            ->where('status', 'closed')
            ->whereNotNull('closed_at')
            ->where('closed_at', '>=', now()->subDays(30))
            ->get();

        $myPerformance = [
            'resolved_today' => Ticket::query()
                ->where('assigned_to', $userId)
                ->where('status', 'closed')
                ->whereDate('closed_at', today())
                ->count(),
            'avg_resolution_hours' => round($myClosedRecent->avg(fn ($t) => $t->getEffectiveResolutionTimeHours()) ?? 0, 1),
            'avg_work_hours' => round(
                $myClosedRecent->filter(fn ($t) => $t->in_progress_at)->avg(fn ($t) => $t->getWorkResolutionTimeHours()) ?? 0,
                1
            ),
        ];

        // ── My Open Tickets ──
        $myTickets = Ticket::query()
            ->with(['client', 'department'])
            ->where('assigned_to', $userId)
            ->open()
            ->latest()
            ->take(10)
            ->get();

        // ── My Tasks ──
        $myTasks = TicketTask::query()
            ->with(['ticket:id,ticket_number,subject'])
            ->where('assigned_to', $userId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->ordered()
            ->take(10)
            ->get();

        // ── My Activity Feed (recent history entries by this user) ──
        $myActivity = TicketHistory::query()
            ->with(['ticket:id,ticket_number', 'user:id,name'])
            ->where('user_id', $userId)
            ->latest()
            ->take(10)
            ->get();

        // ── My Ticket Trend (last 14 days — tickets assigned to me) ──
        $myTrend = $this->getMyTicketTrend($userId);

        // ── My Tickets by Status (donut) ──
        $myTicketsByStatus = Ticket::query()
            ->where('assigned_to', $userId)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // ── My Tickets by Priority (donut) ──
        $myTicketsByPriority = Ticket::query()
            ->where('assigned_to', $userId)
            ->open()
            ->select('priority', DB::raw('COUNT(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->toArray();

        // ── Profile Summary ──
        $profileSummary = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role ? ucfirst($role) : '-',
            'departments' => $user->departments->pluck('name')->join(', ') ?: '-',
        ];

        $ticketUrlBase = route('tickets.show', ['ticket' => '__ID__']);

        return compact(
            'myTicketStats',
            'myPerformance',
            'myTickets',
            'myTasks',
            'myActivity',
            'myTrend',
            'myTicketsByStatus',
            'myTicketsByPriority',
            'profileSummary',
            'isAdminOrOwner',
            'ticketUrlBase'
        );
    }

    /**
     * Get personal ticket trend (last 14 days).
     *
     * @return array<int, array{date: string, count: int}>
     */
    private function getMyTicketTrend(int $userId): array
    {
        $counts = Ticket::query()
            ->where('assigned_to', $userId)
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('count', 'date');

        $trends = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->toDateString();
            $trends[] = [
                'date' => $date,
                'count' => $counts[$date] ?? 0,
            ];
        }

        return $trends;
    }
}
