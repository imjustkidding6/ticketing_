<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChatbotFallbackNotification extends Notification
{
    public function __construct(
        public Tenant $tenant,
        public string $reason,
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
            ->subject('Chatbot fallback triggered')
            ->line("The public chatbot for {$this->tenant->name} entered fallback mode.")
            ->line($this->reason)
            ->line('A support ticket should be created to ensure customer follow-up.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tenant_id' => $this->tenant->id,
            'action' => 'chatbot_fallback',
            'reason' => $this->reason,
        ];
    }
}
