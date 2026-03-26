@extends('emails.layout')

@section('content')
<h2 style="color: {{ $tenant?->primary_color ?? '#4f46e5' }}; margin: 0 0 15px;">Ticket Status Updated</h2>

<p>Hello,</p>
<p>The status of your ticket has been updated:</p>

<div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px; margin: 15px 0;">
    <table style="width: 100%; border-collapse: collapse;">
        <tr><td style="padding: 6px 0; font-weight: bold; width: 120px; color: #6b7280;">Ticket #:</td><td style="padding: 6px 0;">{{ $ticket->ticket_number }}</td></tr>
        <tr><td style="padding: 6px 0; font-weight: bold; color: #6b7280;">Subject:</td><td style="padding: 6px 0;">{{ $ticket->subject }}</td></tr>
        <tr><td style="padding: 6px 0; font-weight: bold; color: #6b7280;">Previous Status:</td><td style="padding: 6px 0;">{{ ucfirst(str_replace('_', ' ', $oldStatus)) }}</td></tr>
        <tr><td style="padding: 6px 0; font-weight: bold; color: #6b7280;">New Status:</td><td style="padding: 6px 0; font-weight: bold; color: {{ $tenant?->primary_color ?? '#4f46e5' }};">{{ ucfirst(str_replace('_', ' ', $newStatus)) }}</td></tr>
    </table>
</div>

<div style="text-align: center; margin: 25px 0;">
    <a href="{{ $actionUrl ?? '#' }}" style="display: inline-block; background-color: {{ $tenant?->primary_color ?? '#4f46e5' }}; color: #ffffff; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 14px;">View Ticket</a>
</div>
@endsection
