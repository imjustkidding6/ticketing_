<?php

namespace App\Services;

use App\Models\ServiceReport;
use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServiceReportService
{
    /**
     * Generate a service report for a closed ticket.
     */
    public function generate(Ticket $ticket): ServiceReport
    {
        $ticket->load(['client', 'category', 'department', 'product', 'creator', 'assignee', 'tasks', 'slaPolicy']);

        $reportData = [
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'client_name' => $ticket->client?->name,
            'department' => $ticket->department?->name,
            'category' => $ticket->category?->name,
            'product' => $ticket->product?->name,
            'created_by' => $ticket->creator?->name,
            'assigned_to' => $ticket->assignee?->name,
            'created_at' => $ticket->created_at->toDateTimeString(),
            'closed_at' => $ticket->closed_at?->toDateTimeString(),
            'resolution_hours' => $ticket->closed_at
                ? round($ticket->created_at->diffInHours($ticket->closed_at), 1)
                : null,
            'tasks' => $ticket->tasks->map(fn ($t) => [
                'description' => $t->description,
                'status' => $t->status,
                'assignee' => $t->assignee?->name,
            ])->toArray(),
            'sla_policy' => $ticket->slaPolicy?->name,
            'response_met' => $ticket->first_response_at && $ticket->response_due_at
                ? $ticket->first_response_at->lte($ticket->response_due_at)
                : null,
            'resolution_met' => $ticket->closed_at && $ticket->resolution_due_at
                ? $ticket->closed_at->lte($ticket->resolution_due_at)
                : null,
        ];

        $report = ServiceReport::create([
            'ticket_id' => $ticket->id,
            'client_id' => $ticket->client_id,
            'report_data' => $reportData,
            'generated_at' => now(),
        ]);

        $tenant = \App\Models\Tenant::find($ticket->tenant_id);
        $pdf = Pdf::loadView('reports.service-report-pdf', ['data' => $reportData, 'report' => $report, 'tenant' => $tenant]);
        $path = "service-reports/{$report->report_number}.pdf";
        Storage::put($path, $pdf->output());

        $report->update(['file_path' => $path]);

        return $report;
    }

    /**
     * Download a service report PDF.
     */
    public function download(ServiceReport $report): StreamedResponse|Response
    {
        if ($report->file_path && Storage::exists($report->file_path)) {
            return Storage::download($report->file_path, "{$report->report_number}.pdf");
        }

        $tenant = $report->ticket ? \App\Models\Tenant::find($report->ticket->tenant_id) : null;
        $pdf = Pdf::loadView('reports.service-report-pdf', [
            'data' => $report->report_data,
            'report' => $report,
            'tenant' => $tenant,
        ]);

        return $pdf->download("{$report->report_number}.pdf");
    }
}
