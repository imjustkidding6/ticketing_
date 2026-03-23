<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Services\TenantUrlHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SlaBreachWarningNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public string $breachType,
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
        $dueAt = $this->breachType === 'response'
            ? $this->ticket->response_due_at
            : $this->ticket->resolution_due_at;

        $breachLabel = ucfirst($this->breachType);

        return (new MailMessage)
            ->subject("SLA {$breachLabel} Breach: {$this->ticket->ticket_number}")
            ->greeting("SLA {$breachLabel} Time Exceeded")
            ->line("**{$this->ticket->subject}**")
            ->line("Ticket Number: {$this->ticket->ticket_number}")
            ->line("{$breachLabel} was due: ".($dueAt?->format('M d, Y H:i') ?? 'N/A'))
            ->action('View Ticket', app(TenantUrlHelper::class)->tenantUrl($this->ticket->tenant, '/tickets/'.$this->ticket->id))
            ->line('Please take immediate action on this ticket.');
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
            'action' => 'sla_breach',
            'breach_type' => $this->breachType,
        ];
    }
}
