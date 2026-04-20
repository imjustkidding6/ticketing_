@php
    $tenant = $tenant ?? null;
    $tenantId = $tenant?->id;

    // Fetch settings using tenant ID directly (works in queued jobs without session)
    $generalSettings = $tenantId
        ? \App\Models\AppSetting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('group', 'general')->get()->mapWithKeys(fn ($s) => [$s->key => $s->getTypedValue()])->toArray()
        : [];

    $companyName = $generalSettings['company_name'] ?? $tenant?->name ?? config('app.name');
    $companyEmail = $generalSettings['company_email'] ?? '';
    $companyPhone = $generalSettings['company_phone'] ?? '';
    $companyAddress = $generalSettings['company_address'] ?? '';
    $companyWebsite = $generalSettings['company_website'] ?? '';
    $primaryColor = $tenant?->primary_color ?? '#4f46e5';
    $logoUrl = $tenant?->logo_path ? rtrim(config('app.url'), '/') . '/storage/' . $tenant->logo_path : null;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 0; background-color: #f3f4f6;">
    {{-- Header --}}
    <div style="background-color: {{ $primaryColor }}; padding: 20px 30px; text-align: center;">
        @if($logoUrl)
            <img src="{{ $logoUrl }}" alt="{{ $companyName }}" style="max-height: 40px; width: auto;">
        @else
            <h1 style="color: #ffffff; margin: 0; font-size: 20px;">{{ $companyName }}</h1>
        @endif
    </div>

    {{-- Body --}}
    <div style="background-color: #ffffff; padding: 30px; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">
        @yield('content')
    </div>

    {{-- Footer --}}
    <div style="background-color: #f9fafb; padding: 20px 30px; border: 1px solid #e5e7eb; border-top: none; text-align: center; font-size: 12px; color: #6b7280;">
        <p style="margin: 0 0 5px;">{{ $companyName }}</p>
        @if($companyAddress)
            <p style="margin: 0 0 5px;">{{ $companyAddress }}</p>
        @endif
        @if($companyEmail || $companyPhone)
            <p style="margin: 0 0 5px;">
                @if($companyEmail){{ $companyEmail }}@endif
                @if($companyEmail && $companyPhone) &middot; @endif
                @if($companyPhone){{ $companyPhone }}@endif
            </p>
        @endif
        @if($companyWebsite)
            <p style="margin: 0;"><a href="{{ $companyWebsite }}" style="color: {{ $primaryColor }}; text-decoration: none;">{{ $companyWebsite }}</a></p>
        @endif
    </div>
</body>
</html>
