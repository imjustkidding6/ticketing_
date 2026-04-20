@php
    $tenant = $tenant ?? null;
    $tenantId = $tenant?->id;
    $generalSettings = $tenantId
        ? \App\Models\AppSetting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('group', 'general')->get()->mapWithKeys(fn ($s) => [$s->key => $s->getTypedValue()])->toArray()
        : [];
    $companyName = $generalSettings['company_name'] ?? $tenant?->name ?? config('app.name');
    $companyEmail = $generalSettings['company_email'] ?? '';
    $companyPhone = $generalSettings['company_phone'] ?? '';
    $companyWebsite = $generalSettings['company_website'] ?? '';
    $primaryColor = $tenant?->primary_color ?? '#4f46e5';
    $logoUrl = $tenant?->logo_path ? rtrim(config('app.url'), '/') . '/storage/' . $tenant->logo_path : null;
    $clientName = $ticket->client?->contact_person ?? $ticket->client?->name ?? 'Valued Customer';
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket Update - {{ $ticket->ticket_number }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f0f4f8;font-family:Arial,sans-serif;line-height:1.6;color:#334155;">
<div style="max-width:600px;margin:20px auto;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.1);">

    <div style="background:{{ $primaryColor }};padding:30px;text-align:center;">
        @if($logoUrl)
            <img src="{{ $logoUrl }}" alt="{{ $companyName }}" style="max-height:40px;width:auto;">
        @else
            <h1 style="color:#ffffff;margin:0;font-size:22px;">{{ $companyName }}</h1>
        @endif
    </div>

    <div style="padding:30px;">
        <div style="background:#ecfdf5;border:2px solid #86efac;border-radius:8px;padding:20px;margin:0 0 25px;text-align:center;">
            <h2 style="color:#047857;font-size:22px;font-weight:700;margin:0 0 6px;">Ticket Status Updated</h2>
            <p style="color:#065f46;margin:0;font-size:14px;">Your ticket status has been changed</p>
        </div>

        <p style="color:#64748b;margin:0 0 20px;font-size:15px;">Dear <strong style="color:#1f2937;">{{ $clientName }}</strong>,</p>
        <p style="color:#64748b;margin:0 0 25px;font-size:15px;line-height:1.7;">
            We've made progress on your support ticket. The status has been updated.
        </p>

        {{-- Status Transition --}}
        <div style="text-align:center;margin:0 0 25px;padding:20px;background:#f8fafc;border-radius:8px;">
            <span style="padding:8px 16px;background:#e2e8f0;color:#64748b;border-radius:6px;font-weight:600;text-decoration:line-through;">{{ ucfirst(str_replace('_', ' ', $oldStatus)) }}</span>
            <span style="font-size:20px;color:{{ $primaryColor }};margin:0 10px;">→</span>
            <span style="padding:8px 16px;background:{{ $primaryColor }};color:white;border-radius:6px;font-weight:600;">{{ ucfirst(str_replace('_', ' ', $newStatus)) }}</span>
        </div>

        {{-- Ticket Details --}}
        <div style="background:linear-gradient(135deg,#eff6ff 0%,#dbeafe 100%);border:2px solid #93c5fd;border-radius:8px;padding:20px;margin:0 0 25px;position:relative;">
            <div style="position:absolute;top:-1px;left:-1px;right:-1px;height:4px;background:{{ $primaryColor }};border-radius:8px 8px 0 0;"></div>
            <table style="width:100%;border-collapse:collapse;">
                <tr>
                    <td style="padding:10px 0;color:#374151;font-weight:600;width:35%;">Ticket Number:</td>
                    <td style="padding:10px 0;"><span style="color:#1e40af;font-weight:700;font-size:16px;background:#ffffff;padding:4px 12px;border-radius:6px;border:2px solid {{ $primaryColor }};">{{ $ticket->ticket_number }}</span></td>
                </tr>
                <tr><td style="padding:10px 0;color:#374151;font-weight:600;">Subject:</td><td style="padding:10px 0;color:#1f2937;font-weight:500;">{{ $ticket->subject }}</td></tr>
                <tr><td style="padding:10px 0;color:#374151;font-weight:600;">New Status:</td><td style="padding:10px 0;"><span style="padding:4px 10px;border-radius:4px;font-size:12px;font-weight:600;text-transform:uppercase;background:{{ $primaryColor }};color:white;">{{ ucfirst(str_replace('_', ' ', $newStatus)) }}</span></td></tr>
                <tr><td style="padding:10px 0;color:#374151;font-weight:600;">Updated:</td><td style="padding:10px 0;color:#6b7280;">{{ now()->format('l, F j, Y \a\t g:i A') }}</td></tr>
            </table>
        </div>

        {{-- Status Meaning --}}
        <div style="background:#f0f9ff;border:1px solid #7dd3fc;border-radius:8px;padding:15px;margin:0 0 20px;">
            <h3 style="color:#0284c7;font-size:14px;font-weight:600;margin:0 0 8px;">What This Means</h3>
            <p style="margin:0;color:#075985;font-size:13px;line-height:1.6;">
                @if($newStatus === 'assigned')
                    Your ticket has been assigned to a specialist who will begin working on it shortly.
                @elseif($newStatus === 'in_progress')
                    Our team is actively working on resolving your issue. We'll keep you updated.
                @elseif($newStatus === 'on_hold')
                    Your ticket is temporarily on hold. We will resume work as soon as possible.
                @elseif($newStatus === 'closed')
                    Your ticket has been resolved and closed. If you need further assistance, please don't hesitate to reach out again.
                @elseif($newStatus === 'cancelled')
                    Your ticket has been cancelled. If you need further assistance, please create a new request.
                @else
                    Your ticket status has been updated. Our team continues to work on your request.
                @endif
            </p>
        </div>
    </div>

    <div style="background:#f1f5f9;padding:20px;text-align:center;border-top:1px solid #e2e8f0;">
        <p style="margin:0 0 5px;color:#374151;font-size:14px;font-weight:600;">{{ $companyName }}</p>
        @if($companyEmail || $companyPhone)
            <p style="margin:0 0 5px;color:#64748b;font-size:12px;">@if($companyEmail){{ $companyEmail }}@endif @if($companyEmail && $companyPhone) | @endif @if($companyPhone){{ $companyPhone }}@endif</p>
        @endif
        @if($companyWebsite)
            <p style="margin:0;font-size:12px;"><a href="{{ $companyWebsite }}" style="color:{{ $primaryColor }};text-decoration:none;">{{ $companyWebsite }}</a></p>
        @endif
    </div>
</div>
</body>
</html>
