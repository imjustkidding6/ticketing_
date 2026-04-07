<?php

namespace App\Notifications;

use App\Models\Tenant;
use App\Models\Ticket;
use App\Services\TenantUrlHelper;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCreatedNotification extends Notification
{
    public function __construct(
        public Ticket $ticket,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            return ['mail'];
        }

        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = Tenant::find($this->ticket->tenant_id);
        $actionUrl = app(TenantUrlHelper::class)->tenantUrl($tenant, '/tickets/'.$this->ticket->id);

        return (new MailMessage)
            ->subject("New Ticket: {$this->ticket->ticket_number}")
            ->view('emails.ticket-created', [
                'ticket' => $this->ticket->load(['client', 'assignee']),
                'tenant' => $tenant,
                'actionUrl' => $actionUrl,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'subject' => $this->ticket->subject,
            'action' => 'created',
        ];
    }
}
