<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ __('Help & Tutorials') }}</title>
<style>
    /* DomPDF has limited CSS support — no flexbox, no grid, no backdrop-filter.
       This stylesheet is intentionally plain so the PDF stays readable
       regardless of what utility classes the tutorial partials use. */

    @page {
        margin: 90px 50px 70px 50px;
    }

    body {
        font-family: 'DejaVu Sans', 'Helvetica', 'Arial', sans-serif;
        font-size: 11px;
        color: #1f2937;
        line-height: 1.55;
        text-align: justify;
    }

    h1, h2, h3 { text-align: left; }

    h1 { font-size: 22px; margin: 0 0 6px 0; color: #111827; }
    h2 { font-size: 16px; margin: 0 0 10px 0; color: #4338ca; border-bottom: 1px solid #e5e7eb; padding-bottom: 6px; }
    h3 { font-size: 13px; margin: 14px 0 6px 0; color: #1f2937; }
    p { margin: 0 0 8px 0; }
    ul, ol { margin: 0 0 10px 20px; padding: 0; }
    li { margin-bottom: 4px; }
    a { color: #4f46e5; text-decoration: none; }
    strong, b { color: #111827; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; font-size: 10px; }
    th { background: #f3f4f6; }
    code { background: #f3f4f6; padding: 1px 4px; border-radius: 3px; font-family: 'Courier New', monospace; font-size: 10px; }
    blockquote { border-left: 3px solid #a5b4fc; margin: 10px 0; padding: 6px 12px; background: #eef2ff; color: #3730a3; }

    /* Neutralize common Tailwind layout utilities the partials may use,
       so nested content degrades to plain block flow instead of breaking. */
    .flex, .grid, .inline-flex, .sm\:flex, .md\:flex, .lg\:flex { display: block !important; }
    .sticky, .fixed { position: static !important; }
    .backdrop-blur, .shadow-sm, .shadow, .shadow-md, .shadow-lg { box-shadow: none !important; }
    .rounded, .rounded-md, .rounded-lg, .rounded-xl, .rounded-full { border-radius: 4px !important; }
    img, svg { max-width: 100% !important; }

    /* ── Cover ── */
    .cover {
        text-align: center;
        padding-top: 160px;
    }
    .cover h1,
    .cover h2,
    .cover h3 {
        text-align: center;
    }
    .cover img.logo {
        max-height: 70px;
        max-width: 220px;
        margin-bottom: 18px;
    }
    .cover .brand {
        font-size: 13px;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: #6366f1;
        margin-bottom: 14px;
    }
    .cover h1 {
        font-size: 30px;
        margin-bottom: 10px;
    }
    .cover p {
        color: #6b7280;
        font-size: 12px;
    }

    /* ── Table of contents ── */
    .toc { }
    .toc ol { list-style: decimal; margin-left: 22px; }
    .toc li { font-size: 12px; margin-bottom: 8px; color: #1f2937; }
    .toc li span.desc { display: block; font-size: 10px; color: #6b7280; margin-top: 2px; }

    /* ── Sections ── */
    .tutorial-section {
        page-break-before: always;
        padding-top: 8px;
    }

    /* Header / footer */
    header {
        position: fixed;
        top: -60px;
        left: 0;
        right: 0;
        height: 40px;
        font-size: 9px;
        color: #9ca3af;
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 6px;
    }
    footer {
        position: fixed;
        bottom: -50px;
        left: 0;
        right: 0;
        height: 30px;
        font-size: 9px;
        color: #9ca3af;
        text-align: center;
        border-top: 1px solid #e5e7eb;
        padding-top: 6px;
    }
</style>
</head>
<body>

<header>{{ $tenant->name ?? 'CliqueHA Nexus' }} — {{ __('Help & Tutorials') }}</header>
<footer>{{ __('Generated on') }} {{ now()->format('F j, Y') }} — {{ __('CliqueHA Nexus') }}</footer>

{{-- Cover page --}}
@php
    $logoPath = null;
    if (!empty($tenant?->logo_path)) {
        $candidate = storage_path('app/public/' . $tenant->logo_path);
        if (file_exists($candidate)) {
            $logoPath = $candidate;
        }
    }
    if (!$logoPath) {
        $fallback = public_path('cliqueha-logo.png');
        if (file_exists($fallback)) {
            $logoPath = $fallback;
        }
    }
@endphp
<div class="cover">
    @if($logoPath)
        <img class="logo" src="{{ $logoPath }}" alt="{{ $tenant->name ?? 'CliqueHA Nexus' }}">
    @endif
    <div class="brand">{{ $tenant->name ?? 'CliqueHA Nexus' }}</div>
    <h1>{{ __('Help & Tutorials') }}</h1>
    <p>{{ __('Everything you need to get the most out of CliqueHA Nexus, on one page.') }}</p>
    <p>{{ __('Generated on') }} {{ now()->format('F j, Y') }}</p>
</div>

{{-- Table of contents --}}
<div class="toc">
    <h2>{{ __('Contents') }}</h2>
    <ol>
        @foreach($tutorials as $slug => $tutorial)
            <li>
                {{ __($tutorial['title']) }}
                <span class="desc">{{ __($tutorial['description']) }}</span>
            </li>
        @endforeach
    </ol>
</div>

{{-- Sections --}}
@foreach($tutorials as $slug => $tutorial)
    <div class="tutorial-section">
        @include('tutorials.partials.' . $slug)
    </div>
@endforeach

</body>
</html>