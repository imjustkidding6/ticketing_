<?php

namespace App\Notifications;

use App\Models\BugReport;
use Illuminate\Notifications\Notification;

/**
 * Sent to internal staff (is_admin) when a user reports a product bug through the
 * AI Assistant, so they can review it at /admin/bugs and escalate to the AI Programmer.
 */
class BugReportFiled extends Notification
{
    public function __construct(
        public BugReport $bug,
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
            'bug_report_id' => $this->bug->id,
            'reference' => $this->bug->reference(),
            'title' => $this->bug->title,
            'severity' => $this->bug->severity,
            'action' => 'bug_report_filed',
        ];
    }
}
