<?php

namespace App\Notifications;

use App\Models\Tenant;
use App\Models\Ticket;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientTicketStatusChangedNotification extends Notification
{
    public function __construct(
        public Ticket $ticket,
        public string $oldStatus,
        public string $newStatus,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = Tenant::find($this->ticket->tenant_id);

        return (new MailMessage)
            ->subject("Ticket Update: {$this->ticket->ticket_number}")
            ->view('emails.client-ticket-status-changed', [
                'ticket' => $this->ticket->load('client'),
                'tenant' => $tenant,
                'oldStatus' => $this->oldStatus,
                'newStatus' => $this->newStatus,
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
            'action' => 'status_changed',
            'new_status' => $this->newStatus,
        ];
    }
}
