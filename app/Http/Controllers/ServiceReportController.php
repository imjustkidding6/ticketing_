<?php

namespace App\Http\Controllers;

use App\Models\ServiceReport;
use App\Models\Ticket;
use App\Services\ServiceReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServiceReportController extends Controller
{
    public function __construct(
        private ServiceReportService $serviceReportService,
    ) {}

    /**
     * List service reports.
     */
    public function index(): View
    {
        $reports = ServiceReport::query()
            ->with(['ticket', 'client'])
            ->latest()
            ->paginate(20);

        return view('reports.service-reports', compact('reports'));
    }

    /**
     * Generate a service report for a ticket.
     */
    public function generate(Ticket $ticket): RedirectResponse
    {
        $this->serviceReportService->generate($ticket);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', 'Service report generated successfully.');
    }

    /**
     * Download a service report PDF.
     */
    public function download(ServiceReport $report): StreamedResponse|Response
    {
        return $this->serviceReportService->download($report);
    }
}
