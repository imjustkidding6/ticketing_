@extends('emails.layout')

@section('content')
<h2 style="color: {{ $tenant?->primary_color ?? '#4f46e5' }}; margin: 0 0 15px;">New Ticket Created</h2>

<p>Hello,</p>
<p>A new support ticket has been created:</p>

<div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px; margin: 15px 0;">
    <table style="width: 100%; border-collapse: collapse;">
        <tr><td style="padding: 6px 0; font-weight: bold; width: 120px; color: #6b7280;">Ticket #:</td><td style="padding: 6px 0;">{{ $ticket->ticket_number }}</td></tr>
        <tr><td style="padding: 6px 0; font-weight: bold; color: #6b7280;">Subject:</td><td style="padding: 6px 0;">{{ $ticket->subject }}</td></tr>
        <tr><td style="padding: 6px 0; font-weight: bold; color: #6b7280;">Client:</td><td style="padding: 6px 0;">{{ $ticket->client?->name ?? '-' }}</td></tr>
        <tr><td style="padding: 6px 0; font-weight: bold; color: #6b7280;">Priority:</td><td style="padding: 6px 0;">{{ ucfirst($ticket->priority) }}</td></tr>
        <tr><td style="padding: 6px 0; font-weight: bold; color: #6b7280;">Created:</td><td style="padding: 6px 0;">{{ $ticket->created_at->format('M d, Y g:i A') }}</td></tr>
    </table>
</div>

@if($ticket->description)
<div style="background: #f9fafb; border-radius: 8px; padding: 15px; margin: 15px 0;">
    <p style="margin: 0 0 5px; font-weight: bold; color: #6b7280;">Description:</p>
    <p style="margin: 0; white-space: pre-wrap;">{{ Str::limit($ticket->description, 300) }}</p>
</div>
@endif

<div style="text-align: center; margin: 25px 0;">
    <a href="{{ $actionUrl ?? '#' }}" style="display: inline-block; background-color: {{ $tenant?->primary_color ?? '#4f46e5' }}; color: #ffffff; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 14px;">View Ticket</a>
</div>
@endsection
