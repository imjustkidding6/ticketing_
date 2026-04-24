<?php

namespace App\Notifications;

use App\Models\SystemAnnouncement;
use Illuminate\Notifications\Notification;

class SystemAnnouncementNotification extends Notification
{
    public function __construct(
        public SystemAnnouncement $announcement,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'announcement_id' => $this->announcement->id,
            'title' => $this->announcement->title,
            'body' => $this->announcement->body,
            'severity' => $this->announcement->severity,
            'action' => 'system_announcement',
            'published_at' => $this->announcement->published_at?->toIso8601String(),
        ];
    }
}
