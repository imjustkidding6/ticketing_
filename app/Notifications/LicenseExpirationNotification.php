<?php

namespace App\Notifications;

use App\Models\License;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LicenseExpirationNotification extends Notification
{
    public const STAGE_APPROACHING = 'approaching';   // 7+ days before expiry

    public const STAGE_IMMINENT = 'imminent';      // 3 days or less before expiry

    public const STAGE_GRACE = 'grace';         // expired but in grace period

    public const STAGE_EXPIRED = 'expired';       // fully expired, access blocked

    public function __construct(
        public License $license,
        public string $stage,
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
        $tenant = $this->license->tenant;
        $expiresAt = $this->license->expires_at?->format('F j, Y');
        $graceEndAt = $this->license->gracePeriodEndsAt()->format('F j, Y');

        return match ($this->stage) {
            self::STAGE_APPROACHING => (new MailMessage)
                ->subject("Your subscription expires in {$this->license->daysUntilExpiry()} days")
                ->greeting('Subscription Renewal Reminder')
                ->line("Your subscription for **{$tenant?->name}** is set to expire on **{$expiresAt}**.")
                ->line('Please contact your administrator or distributor to renew before this date to avoid any service interruption.')
                ->line("Days remaining: **{$this->license->daysUntilExpiry()}**"),

            self::STAGE_IMMINENT => (new MailMessage)
                ->subject("Action required: subscription expires in {$this->license->daysUntilExpiry()} days")
                ->error()
                ->greeting('Subscription Expiring Soon')
                ->line("Your subscription for **{$tenant?->name}** expires on **{$expiresAt}**.")
                ->line("After expiration, your account will enter a {$this->license->grace_days}-day grace period before access is suspended.")
                ->line('Please renew immediately to avoid disruption.'),

            self::STAGE_GRACE => (new MailMessage)
                ->subject('Subscription expired — grace period active')
                ->error()
                ->greeting('Subscription Has Expired')
                ->line("Your subscription for **{$tenant?->name}** expired on **{$expiresAt}**.")
                ->line("You are currently in a grace period until **{$graceEndAt}**. Service will be suspended if not renewed by then.")
                ->line('Please contact your administrator or distributor immediately to restore your subscription.'),

            self::STAGE_EXPIRED => (new MailMessage)
                ->subject('Subscription expired — service suspended')
                ->error()
                ->greeting('Service Suspended')
                ->line("Your subscription for **{$tenant?->name}** has fully expired and access has been suspended.")
                ->line('To restore service, please contact your administrator or distributor to reactivate your subscription.')
                ->line('Your data is preserved and will be available immediately upon reactivation.'),

            default => new MailMessage,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'license_id' => $this->license->id,
            'tenant_id' => $this->license->tenant_id,
            'tenant_name' => $this->license->tenant?->name,
            'stage' => $this->stage,
            'expires_at' => $this->license->expires_at?->toIso8601String(),
            'days_left' => $this->license->daysUntilExpiry(),
            'action' => 'license_expiration',
        ];
    }
}
