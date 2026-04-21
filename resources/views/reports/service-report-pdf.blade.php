@php
    $tenant = $tenant ?? null;
    $general = \App\Models\AppSetting::getByGroup('general');
    $companyName = $general['company_name'] ?? $tenant?->name ?? config('app.name');
    $companyAddress = $general['company_address'] ?? 'Makati';
    $companyPhone = $general['company_phone'] ?? '';
    $companyEmail = $general['company_email'] ?? '';
    $companyWebsite = $general['company_website'] ?? '';

    $logoPath = $tenant?->serviceReportLogoPath();
    $logoUrl = $logoPath ? public_path('storage/' . $logoPath) : public_path('cliqueha-logo.png');
    $hasLogo = $logoUrl && file_exists($logoUrl);

    $contactParts = array_filter([
        $companyAddress ? 'Address: ' . $companyAddress : null,
        $companyPhone ? 'Mobile: ' . $companyPhone : null,
        $companyEmail ? 'email: ' . $companyEmail : null,
        $companyWebsite ? 'website: ' . $companyWebsite : null,
    ]);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Report - {{ $report->report_number }}</title>
    <style>
        @page { margin: 15mm; size: A4; }

        body { font-family: Arial, sans-serif; font-size: 11px; line-height: 1.3; margin: 0; padding: 0; color: #000; }

        .header { text-align: center; margin-bottom: 15px; }
        .logo { max-height: 70px; max-width: 250px; margin-bottom: 8px; }
        .company-name { font-family: 'Arial Rounded', Arial, sans-serif; font-size: 18px; font-weight: bold; margin-bottom: 6px; }
        .contact-info { font-size: 10px; margin-bottom: 12px; }

        .report-title { font-size: 16px; font-weight: bold; text-align: center; margin: 12px 0 10px 0; }

        .section-title { font-weight: bold; font-size: 12px; margin-bottom: 6px; text-decoration: underline; }

        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .info-table td { padding: 4px 6px; border: 1px solid #ccc; vertical-align: top; font-size: 10px; }
        .label { font-weight: bold; background-color: #3498db; color: white; width: 25%; }

        .tasks-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .tasks-table th, .tasks-table td { border: 1px solid #000; padding: 5px; text-align: left; font-size: 10px; }
        .tasks-table th { background-color: #3498db; color: white; font-weight: bold; text-align: center; }
        .task-number { text-align: center; width: 8%; }
        .task-details { width: 57%; }
        .task-status { text-align: center; width: 15%; }
        .task-completed { width: 15%; text-align: center; }

        .comments-box { border: 1px solid #000; padding: 8px; min-height: 50px; font-size: 10px; }

        .signature-section { margin-top: 15px; display: table; width: 100%; }
        .signature-left, .signature-right { display: table-cell; width: 48%; text-align: center; border-bottom: 1px solid #000; padding-bottom: 5px; font-size: 10px; }
        .signature-gap { display: table-cell; width: 4%; }
        .signature-label { margin-top: 5px; font-size: 9px; }

        .date-report-section { display: table; width: 100%; margin-bottom: 10px; }
        .date-cell, .report-cell { display: table-cell; width: 48%; }
        .date-gap { display: table-cell; width: 4%; }
        .field-box { border: 1px solid #000; padding: 6px; min-height: 16px; background-color: #f9f9f9; font-size: 10px; }

        .compact-section { margin-bottom: 8px; }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        @if($hasLogo)
            <img src="{{ $logoUrl }}" alt="{{ $companyName }}" class="logo">
        @else
            <div class="company-name">{{ $companyName }}</div>
        @endif
        @if(count($contactParts) > 0)
            <div class="contact-info">{{ implode(' | ', $contactParts) }}</div>
        @endif
    </div>

    <!-- Report Title -->
    <div class="report-title">Service Report</div>

    <!-- Date and Report Number -->
    <div class="date-report-section">
        <div class="date-cell">
            <strong>DATE:</strong>
            <div class="field-box">{{ $data['report_date'] ?? $report->generated_at?->format('F j, Y') }}</div>
        </div>
        <div class="date-gap"></div>
        <div class="report-cell">
            <strong>SERVICE REPORT #</strong>
            <div class="field-box">{{ $report->report_number }}</div>
        </div>
    </div>

    <!-- Customer Information -->
    <div class="compact-section">
        <div class="section-title">CUSTOMER INFORMATION</div>
        <table class="info-table">
            <tr>
                <td class="label">COMPANY:</td>
                <td>{{ $data['client_info']['company_name'] ?? '' }}</td>
                <td class="label">CONTACT:</td>
                <td>{{ $data['client_info']['contact_person'] ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">ADDRESS:</td>
                <td>{{ $data['client_info']['address'] ?? '' }}</td>
                <td class="label">PHONE:</td>
                <td>{{ $data['client_info']['phone'] ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">EMAIL:</td>
                <td colspan="3">{{ $data['client_info']['email'] ?? '' }}</td>
            </tr>
        </table>
    </div>

    <!-- Company Staff -->
    <div class="compact-section">
        <div class="section-title">{{ strtoupper($companyName) }} STAFF</div>
        <table class="info-table">
            <tr>
                <td class="label">TECHNICIAN:</td>
                <td>{{ $data['staff_info']['name'] ?? '' }}</td>
                <td class="label">PHONE:</td>
                <td>{{ $data['staff_info']['phone'] ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">EMAIL:</td>
                <td colspan="3">{{ $data['staff_info']['email'] ?? '' }}</td>
            </tr>
        </table>
    </div>

    <!-- Tasks -->
    <div class="compact-section">
        <table class="tasks-table">
            <thead>
                <tr>
                    <th class="task-number">#</th>
                    <th class="task-details">TASK DETAILS</th>
                    <th class="task-status">STATUS</th>
                    <th class="task-completed">COMPLETED</th>
                </tr>
            </thead>
            <tbody>
                @forelse($data['tasks'] ?? [] as $task)
                    <tr>
                        <td class="task-number">{{ $task['task_number'] }}</td>
                        <td class="task-details">{{ Str::limit($task['description'], 80) }}</td>
                        <td class="task-status">{{ $task['status'] }}</td>
                        <td class="task-completed">{{ $task['completed_at'] ?? '' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="task-details" style="text-align:center; color:#6b7280;">No tasks recorded.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Service Summary -->
    <div class="compact-section">
        <div class="section-title">SERVICE SUMMARY</div>
        <div class="comments-box">
            <strong>Issue:</strong> {{ $data['ticket_info']['subject'] ?? '' }}<br>
            <strong>Technical Details:</strong> Ticket #{{ $data['ticket_info']['number'] ?? '' }}
                @if(! empty($data['ticket_info']['priority'])) | {{ ucfirst($data['ticket_info']['priority']) }} Priority @endif
                @if(! empty($data['ticket_info']['category'])) | {{ $data['ticket_info']['category'] }} @endif
                <br>
            <strong>Service Period:</strong> {{ $data['ticket_info']['created_at'] ?? '' }} - {{ $data['ticket_info']['closed_at'] ?? 'N/A' }} | Resolution Time: {{ $data['resolution_time'] ?? 'N/A' }}
                @if(isset($data['sla_compliance']) && $data['sla_compliance'] !== null)
                    | SLA: {{ $data['sla_compliance'] ? 'Met' : 'Not Met' }}
                @endif
        </div>
    </div>

    <!-- Final Remarks -->
    <div class="compact-section">
        <div class="section-title">FINAL REMARKS</div>
        <div class="comments-box" style="min-height: 60px; white-space: pre-wrap;">{{ $data['additional_comments'] ?: 'No final remarks provided.' }}</div>
    </div>

    <!-- Signatures -->
    <div class="signature-section">
        <div class="signature-left">
            <div style="height: 40px;"></div>
            <div class="signature-label">Customer Name and Signature</div>
        </div>
        <div class="signature-gap"></div>
        <div class="signature-right">
            <div style="height: 40px;"></div>
            <div class="signature-label">{{ $companyName }}</div>
        </div>
    </div>
</body>
</html>
