<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Services\TenantUrlHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            return ['mail'];
        }

        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $readableOld = ucfirst(str_replace('_', ' ', $this->oldStatus));
        $readableNew = ucfirst(str_replace('_', ' ', $this->newStatus));

        return (new MailMessage)
            ->subject("Ticket Updated: {$this->ticket->ticket_number}")
            ->greeting('Ticket Status Changed')
            ->line("**{$this->ticket->subject}**")
            ->line("Status changed from **{$readableOld}** to **{$readableNew}**.")
            ->action('View Ticket', app(TenantUrlHelper::class)->tenantUrl($this->ticket->tenant, '/tickets/'.$this->ticket->id))
            ->line('Thank you.');
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
            'action' => 'status_changed',
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
        ];
    }
}
