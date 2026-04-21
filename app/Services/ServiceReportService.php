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
        $ticket->load(['client', 'category', 'department', 'products', 'creator', 'assignee', 'tasks', 'slaPolicy']);

        $general = \App\Models\AppSetting::getByGroup('general');

        $slaMet = $ticket->closed_at && $ticket->resolution_due_at
            ? $ticket->closed_at->lte($ticket->resolution_due_at)
            : null;

        $reportData = [
            'report_date' => now()->format('F j, Y'),
            'client_info' => [
                'company_name' => $ticket->client?->name ?? 'N/A',
                'contact_person' => $ticket->client?->contact_person ?? $ticket->client?->name ?? 'N/A',
                'address' => $ticket->client?->address ?? '',
                'phone' => $ticket->client?->phone ?? '',
                'email' => $ticket->client?->email ?? '',
            ],
            'staff_info' => [
                'name' => $ticket->assignee?->name ?? 'Unassigned',
                'phone' => $general['company_phone'] ?? '',
                'email' => $ticket->assignee?->email ?? ($general['company_email'] ?? ''),
            ],
            'ticket_info' => [
                'number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'priority' => $ticket->priority,
                'category' => $ticket->category?->name ?? '',
                'department' => $ticket->department?->name ?? '',
                'created_at' => $ticket->created_at->format('M j, Y g:i A'),
                'closed_at' => $ticket->closed_at?->format('M j, Y g:i A'),
            ],
            'tasks' => $ticket->tasks->values()->map(fn ($t, $i) => [
                'task_number' => $i + 1,
                'description' => $t->description,
                'status' => ucfirst(str_replace('_', ' ', $t->status)),
                'completed_at' => $t->completed_at?->format('M j, Y') ?? '',
            ])->toArray(),
            'additional_comments' => $ticket->closing_remarks ?? '',
            'resolution_time' => $ticket->closed_at
                ? round($ticket->created_at->diffInHours($ticket->closed_at), 1).' hours'
                : 'N/A',
            'sla_compliance' => $slaMet,
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
