<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Department;
use App\Models\Product;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\ReportService;
use App\Services\SlaService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reportService,
        private SlaService $slaService,
    ) {}

    /**
     * Extract filters from request.
     *
     * @return array<string, mixed>
     */
    private function extractFilters(Request $request): array
    {
        $filters = array_filter([
            'from' => $request->input('from', now()->subDays(30)->toDateString()),
            'to' => $request->input('to', now()->toDateString()),
            'status' => $request->input('status'),
            'priority' => $request->input('priority'),
            'department_id' => $request->input('department_id'),
            'category_id' => $request->input('category_id'),
            'assigned_to' => $request->input('assigned_to'),
            'client_id' => $request->input('client_id'),
            'product_id' => $request->input('product_id'),
            'trend_group' => $request->input('trend_group'),
        ]);

        // Restrict to user's departments for agents/managers
        $user = \Illuminate\Support\Facades\Auth::user();
        $tenant = $user?->currentTenant();

        if ($tenant) {
            $role = $user->roleInTenant($tenant);

            if (! in_array($role, ['owner', 'admin'])) {
                $deptIds = $user->departments()->pluck('departments.id')->toArray();

                if (! empty($deptIds)) {
                    $filters['restrict_department_ids'] = $deptIds;
                }
            }
        }

        return $filters;
    }

    /**
     * Get filter lookup data for the view.
     *
     * @return array<string, mixed>
     */
    private function getFilterData(): array
    {
        return [
            'departments' => Department::active()->ordered()->get(['id', 'name']),
            'categories' => TicketCategory::active()->ordered()->get(['id', 'name']),
            'agents' => User::query()->orderBy('name')->get(['id', 'name']),
            'clients' => Client::active()->orderBy('name')->get(['id', 'name']),
            'products' => Product::active()->ordered()->get(['id', 'name']),
        ];
    }

    /**
     * Display report overview.
     */
    public function overview(Request $request): View
    {
        $filters = $this->extractFilters($request);

        $volume = $this->reportService->getTicketVolumeReport($filters);
        $resolution = $this->reportService->getResolutionReport($filters);
        $departments = $this->reportService->getDepartmentReport($filters);

        $trend = $this->reportService->getTrend($filters, $filters['trend_group'] ?? 'daily');
        $topDepartments = $this->reportService->getTopDepartments($filters);
        $topClients = $this->reportService->getTopClients($filters);
        $topAgents = $this->reportService->getTopAgents($filters);

        return view('reports.overview', [
            ...$this->getFilterData(),
            'filters' => $filters,
            'volume' => $volume,
            'resolution' => $resolution,
            'departmentReport' => $departments,
            'trend' => $trend,
            'topDepartments' => $topDepartments,
            'topClients' => $topClients,
            'topAgents' => $topAgents,
        ]);
    }

    /**
     * Export ticket volume as CSV.
     */
    public function exportVolume(Request $request): StreamedResponse
    {
        $filters = $this->extractFilters($request);
        $volume = $this->reportService->getTicketVolumeReport($filters);

        $data = [
            ['Total Tickets', $volume['total']],
            ['Open Tickets', $volume['open']],
            ['Closed Tickets', $volume['closed']],
            ['In Progress', $volume['in_progress']],
            ['On Hold', $volume['on_hold']],
            ['Critical', $volume['by_priority']['critical']],
            ['High', $volume['by_priority']['high']],
            ['Medium', $volume['by_priority']['medium']],
            ['Low', $volume['by_priority']['low']],
        ];

        $from = $filters['from'] ?? now()->subDays(30)->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        return $this->reportService->exportToCsv(
            $data,
            ['Metric', 'Count'],
            "ticket-volume-{$from}-to-{$to}.csv"
        );
    }

    /**
     * Export department report as CSV.
     */
    public function exportDepartments(Request $request): StreamedResponse
    {
        $filters = $this->extractFilters($request);
        $departments = $this->reportService->getDepartmentReport($filters);

        $from = $filters['from'] ?? now()->subDays(30)->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        return $this->reportService->exportToCsv(
            $departments->toArray(),
            ['Department', 'Total', 'Open', 'Closed'],
            "department-report-{$from}-to-{$to}.csv"
        );
    }

    /**
     * Display department report.
     */
    public function departments(Request $request): View
    {
        $filters = $this->extractFilters($request);
        $groupBy = $filters['trend_group'] ?? 'daily';
        $departmentReport = $this->reportService->getDepartmentReport($filters);
        $entityTrend = $this->reportService->getEntityTrend($filters, $groupBy, 'department');

        return view('reports.departments', [
            ...$this->getFilterData(),
            'filters' => $filters,
            'departmentReport' => $departmentReport,
            'entityTrend' => $entityTrend,
        ]);
    }

    /**
     * Display category report.
     */
    public function categories(Request $request): View
    {
        $filters = $this->extractFilters($request);
        $groupBy = $filters['trend_group'] ?? 'daily';
        $categoryReport = $this->reportService->getCategoryReport($filters);
        $entityTrend = $this->reportService->getEntityTrend($filters, $groupBy, 'category');

        return view('reports.categories', [
            ...$this->getFilterData(),
            'filters' => $filters,
            'categoryReport' => $categoryReport,
            'entityTrend' => $entityTrend,
        ]);
    }

    /**
     * Display client report.
     */
    public function clients(Request $request): View
    {
        $filters = $this->extractFilters($request);
        $groupBy = $filters['trend_group'] ?? 'daily';
        $clientReport = $this->reportService->getClientReport($filters);
        $entityTrend = $this->reportService->getEntityTrend($filters, $groupBy, 'client');

        return view('reports.clients', [
            ...$this->getFilterData(),
            'filters' => $filters,
            'clientReport' => $clientReport,
            'entityTrend' => $entityTrend,
        ]);
    }

    /**
     * Display product report.
     */
    public function products(Request $request): View
    {
        $filters = $this->extractFilters($request);
        $groupBy = $filters['trend_group'] ?? 'daily';
        $productReport = $this->reportService->getProductReport($filters);
        $entityTrend = $this->reportService->getEntityTrend($filters, $groupBy, 'product');

        return view('reports.products', [
            ...$this->getFilterData(),
            'filters' => $filters,
            'productReport' => $productReport,
            'entityTrend' => $entityTrend,
        ]);
    }

    /**
     * Display tickets report with individual resolution times.
     */
    public function tickets(Request $request): View
    {
        $filters = $this->extractFilters($request);
        $ticketReport = $this->reportService->getTicketReport($filters);
        $resolution = $this->reportService->getResolutionReport($filters);
        $trend = $this->reportService->getTrend($filters, $filters['trend_group'] ?? 'daily');

        return view('reports.tickets', [
            ...$this->getFilterData(),
            'filters' => $filters,
            'ticketReport' => $ticketReport,
            'resolution' => $resolution,
            'trend' => $trend,
        ]);
    }

    /**
     * Export tickets report as CSV.
     */
    public function exportTickets(Request $request): StreamedResponse
    {
        $filters = $this->extractFilters($request);
        $report = $this->reportService->getTicketReport($filters);

        return $this->reportService->exportToCsv(
            $report->map(fn ($t) => [
                $t['ticket_number'], $t['subject'], $t['client'], $t['department'],
                $t['category'], $t['priority'], $t['status'], $t['assigned_to'],
                $t['created_at'], $t['in_progress_at'], $t['closed_at'],
                $t['resolution_formatted'], $t['work_formatted'],
            ])->toArray(),
            ['Ticket #', 'Subject', 'Client', 'Department', 'Category', 'Priority', 'Status', 'Assigned To', 'Created', 'Started', 'Closed', 'Resolution Time', 'Work Time'],
            'tickets-report-'.($filters['from'] ?? 'all').'-to-'.($filters['to'] ?? 'now').'.csv'
        );
    }

    /**
     * Display SLA compliance report.
     */
    public function slaCompliance(Request $request): View
    {
        $from = $request->input('from') ? \Carbon\Carbon::parse($request->input('from')) : now()->subDays(30);
        $to = $request->input('to') ? \Carbon\Carbon::parse($request->input('to')) : now();

        $report = $this->slaService->getComplianceReport($from, $to);

        return view('reports.sla-compliance', compact('report'));
    }

    /**
     * Display agent performance report.
     */
    public function agents(Request $request): View
    {
        $filters = $this->extractFilters($request);
        $groupBy = $filters['trend_group'] ?? 'daily';
        $agentReport = $this->reportService->getAgentPerformanceReport($filters);
        $entityTrend = $this->reportService->getEntityTrend($filters, $groupBy, 'agent');

        return view('reports.agents', [
            ...$this->getFilterData(),
            'filters' => $filters,
            'agentReport' => $agentReport,
            'entityTrend' => $entityTrend,
        ]);
    }

    /**
     * Export department report as CSV.
     */
    public function exportDepartmentReport(Request $request): StreamedResponse
    {
        $filters = $this->extractFilters($request);
        $report = $this->reportService->getDepartmentReport($filters);

        return $this->reportService->exportToCsv(
            $report->toArray(),
            ['Department', 'Total', 'Open', 'Closed'],
            'department-report-'.($filters['from'] ?? 'all').'-to-'.($filters['to'] ?? 'now').'.csv'
        );
    }

    /**
     * Export category report as CSV.
     */
    public function exportCategoryReport(Request $request): StreamedResponse
    {
        $filters = $this->extractFilters($request);
        $report = $this->reportService->getCategoryReport($filters);

        return $this->reportService->exportToCsv(
            $report->toArray(),
            ['Category', 'Department', 'Total', 'Open', 'Closed'],
            'category-report-'.($filters['from'] ?? 'all').'-to-'.($filters['to'] ?? 'now').'.csv'
        );
    }

    /**
     * Export client report as CSV.
     */
    public function exportClientReport(Request $request): StreamedResponse
    {
        $filters = $this->extractFilters($request);
        $report = $this->reportService->getClientReport($filters);

        return $this->reportService->exportToCsv(
            $report->toArray(),
            ['Client', 'Email', 'Total', 'Open', 'Closed'],
            'client-report-'.($filters['from'] ?? 'all').'-to-'.($filters['to'] ?? 'now').'.csv'
        );
    }

    /**
     * Export product report as CSV.
     */
    public function exportProductReport(Request $request): StreamedResponse
    {
        $filters = $this->extractFilters($request);
        $report = $this->reportService->getProductReport($filters);

        return $this->reportService->exportToCsv(
            $report->toArray(),
            ['Product', 'Total', 'Open', 'Closed'],
            'product-report-'.($filters['from'] ?? 'all').'-to-'.($filters['to'] ?? 'now').'.csv'
        );
    }

    /**
     * Export agent performance as CSV.
     */
    public function exportAgents(Request $request): StreamedResponse
    {
        $filters = $this->extractFilters($request);
        $agentReport = $this->reportService->getAgentPerformanceReport($filters);

        $from = $filters['from'] ?? now()->subDays(30)->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        return $this->reportService->exportToCsv(
            $agentReport->map(fn ($a) => [$a['name'], $a['total'], $a['open'], $a['closed'], $a['avg_resolution_hours']])->toArray(),
            ['Agent', 'Total', 'Open', 'Closed', 'Avg Resolution (hrs)'],
            "agent-performance-{$from}-to-{$to}.csv"
        );
    }
}
