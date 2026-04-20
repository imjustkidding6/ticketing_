@php
    $tenant = $tenant ?? null;
    $generalSettings = \App\Models\AppSetting::getByGroup('general');
    $companyName = $generalSettings['company_name'] ?? $tenant?->name ?? config('app.name');
    $companyEmail = $generalSettings['company_email'] ?? '';
    $companyPhone = $generalSettings['company_phone'] ?? '';
    $companyAddress = $generalSettings['company_address'] ?? '';
    $companyWebsite = $generalSettings['company_website'] ?? '';
    $primaryColor = $tenant?->primary_color ?? '#4f46e5';
    $logoUrl = $tenant?->logo_path ? public_path('storage/' . $tenant->logo_path) : null;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Service Report - {{ $data['ticket_number'] }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; margin: 30px; }
        .header { border-bottom: 3px solid {{ $primaryColor }}; padding-bottom: 15px; margin-bottom: 25px; }
        .header-top { overflow: hidden; margin-bottom: 10px; }
        .header-logo { float: left; }
        .header-logo img { max-height: 40px; width: auto; }
        .header-info { float: right; text-align: right; font-size: 10px; color: #6b7280; }
        .header h1 { font-size: 22px; color: {{ $primaryColor }}; margin: 0; }
        .header p { color: #666; margin: 5px 0 0; font-size: 11px; }
        .report-number { float: right; font-size: 11px; color: #666; }
        .section { margin-bottom: 20px; }
        .section h2 { font-size: 14px; color: {{ $primaryColor }}; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table th, table td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        table th { background: #f9fafb; font-weight: 600; font-size: 11px; text-transform: uppercase; color: #6b7280; }
        .info-grid { display: table; width: 100%; }
        .info-row { display: table-row; }
        .info-label { display: table-cell; width: 35%; padding: 5px 10px; font-weight: 600; color: #6b7280; font-size: 11px; }
        .info-value { display: table-cell; padding: 5px 10px; font-size: 12px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-red { background: #fef2f2; color: #991b1b; }
        .badge-gray { background: #f3f4f6; color: #374151; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 2px solid {{ $primaryColor }}; font-size: 10px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-top">
            <div class="header-logo">
                @if($logoUrl && file_exists($logoUrl))
                    <img src="{{ $logoUrl }}" alt="{{ $companyName }}">
                @else
                    <strong style="font-size: 16px; color: {{ $primaryColor }};">{{ $companyName }}</strong>
                @endif
            </div>
            <div class="header-info">
                @if($companyAddress){{ $companyAddress }}<br>@endif
                @if($companyPhone){{ $companyPhone }}<br>@endif
                @if($companyEmail){{ $companyEmail }}<br>@endif
                @if($companyWebsite){{ $companyWebsite }}@endif
            </div>
        </div>
        <span class="report-number">{{ $report->report_number }}</span>
        <h1>Service Report</h1>
        <p>Generated {{ $report->generated_at->format('F j, Y \a\t g:i A') }}</p>
    </div>

    <div class="section">
        <h2>Ticket Information</h2>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Ticket Number</div>
                <div class="info-value">{{ $data['ticket_number'] }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Subject</div>
                <div class="info-value">{{ $data['subject'] }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Priority</div>
                <div class="info-value">{{ ucfirst($data['priority']) }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Status</div>
                <div class="info-value">{{ ucfirst(str_replace('_', ' ', $data['status'])) }}</div>
            </div>
            @if($data['department'])
            <div class="info-row">
                <div class="info-label">Department</div>
                <div class="info-value">{{ $data['department'] }}</div>
            </div>
            @endif
            @if($data['category'])
            <div class="info-row">
                <div class="info-label">Category</div>
                <div class="info-value">{{ $data['category'] }}</div>
            </div>
            @endif
            @if($data['product'])
            <div class="info-row">
                <div class="info-label">Product</div>
                <div class="info-value">{{ $data['product'] }}</div>
            </div>
            @endif
        </div>
    </div>

    <div class="section">
        <h2>Client & Staff</h2>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Client</div>
                <div class="info-value">{{ $data['client_name'] ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Created By</div>
                <div class="info-value">{{ $data['created_by'] ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Assigned To</div>
                <div class="info-value">{{ $data['assigned_to'] ?? 'Unassigned' }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>Timeline</h2>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Created</div>
                <div class="info-value">{{ $data['created_at'] }}</div>
            </div>
            @if($data['closed_at'])
            <div class="info-row">
                <div class="info-label">Closed</div>
                <div class="info-value">{{ $data['closed_at'] }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Resolution Time</div>
                <div class="info-value">{{ $data['resolution_hours'] }} hours</div>
            </div>
            @endif
            @if($data['sla_policy'])
            <div class="info-row">
                <div class="info-label">SLA Policy</div>
                <div class="info-value">{{ $data['sla_policy'] }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Response SLA</div>
                <div class="info-value">
                    @if($data['response_met'] === true)
                        <span class="badge badge-green">Met</span>
                    @elseif($data['response_met'] === false)
                        <span class="badge badge-red">Missed</span>
                    @else
                        <span class="badge badge-gray">N/A</span>
                    @endif
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Resolution SLA</div>
                <div class="info-value">
                    @if($data['resolution_met'] === true)
                        <span class="badge badge-green">Met</span>
                    @elseif($data['resolution_met'] === false)
                        <span class="badge badge-red">Missed</span>
                    @else
                        <span class="badge badge-gray">N/A</span>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>

    <div class="section">
        <h2>Description</h2>
        <p>{{ $data['description'] }}</p>
    </div>

    @if(count($data['tasks'] ?? []) > 0)
    <div class="section">
        <h2>Tasks</h2>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Assignee</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['tasks'] as $task)
                <tr>
                    <td>{{ $task['description'] }}</td>
                    <td>{{ $task['assignee'] ?? '-' }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $task['status'])) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">
        <p style="margin: 0;">{{ $companyName }} &middot; Service Report #{{ $report->report_number }}</p>
        @if($companyAddress)<p style="margin: 3px 0 0;">{{ $companyAddress }}</p>@endif
        @if($companyEmail || $companyPhone)
            <p style="margin: 3px 0 0;">
                @if($companyEmail){{ $companyEmail }}@endif
                @if($companyEmail && $companyPhone) &middot; @endif
                @if($companyPhone){{ $companyPhone }}@endif
            </p>
        @endif
    </div>
</body>
</html>
