<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Services\TenantUrlHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Ticket Assigned: {$this->ticket->ticket_number}")
            ->greeting('Ticket Assigned to You')
            ->line("**{$this->ticket->subject}**")
            ->line("Ticket Number: {$this->ticket->ticket_number}")
            ->line('Priority: '.ucfirst($this->ticket->priority))
            ->action('View Ticket', app(TenantUrlHelper::class)->tenantUrl($this->ticket->tenant, '/tickets/'.$this->ticket->id))
            ->line('Please review and respond to this ticket.');
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
            'action' => 'assigned',
        ];
    }
}
