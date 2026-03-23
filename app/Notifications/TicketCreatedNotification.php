<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Services\TenantUrlHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCreatedNotification extends Notification implements ShouldQueue
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
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            return ['mail'];
        }

        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New Ticket: {$this->ticket->ticket_number}")
            ->greeting('New Ticket Created')
            ->line("**{$this->ticket->subject}**")
            ->line('Priority: '.ucfirst($this->ticket->priority))
            ->line('Client: '.($this->ticket->client?->name ?? 'N/A'))
            ->action('View Ticket', app(TenantUrlHelper::class)->tenantUrl($this->ticket->tenant, '/tickets/'.$this->ticket->id))
            ->line('This ticket requires attention.');
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
