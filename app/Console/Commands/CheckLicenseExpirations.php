<?php

namespace App\Console\Commands;

use App\Models\License;
use App\Notifications\LicenseExpirationNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class CheckLicenseExpirations extends Command
{
    protected $signature = 'licenses:check-expirations';

    protected $description = 'Notify tenants of expiring licenses, mark fully-expired licenses as EXPIRED';

    public function handle(): int
    {
        $approaching = 0;
        $imminent = 0;
        $grace = 0;
        $expired = 0;

        // 1. Approaching: expires in 4–7 days, no warning sent yet
        License::query()
            ->where('status', License::STATUS_ACTIVE)
            ->whereNull('expiry_warning_sent_at')
            ->whereBetween('expires_at', [now()->addDays(4), now()->addDays(7)->endOfDay()])
            ->with('tenant.owners')
            ->each(function (License $license) use (&$approaching) {
                $this->notifyOwners($license, LicenseExpirationNotification::STAGE_APPROACHING);
                $license->update(['expiry_warning_sent_at' => now()]);
                $approaching++;
            });

        // 2. Imminent: expires in 0–3 days, hasn't been re-warned at the imminent stage
        License::query()
            ->where('status', License::STATUS_ACTIVE)
            ->whereBetween('expires_at', [now(), now()->addDays(3)->endOfDay()])
            ->with('tenant.owners')
            ->each(function (License $license) use (&$imminent) {
                // Always notify for imminent stage (daily reminder), but only once per day
                $lastSent = $license->expiry_warning_sent_at;
                if ($lastSent && $lastSent->isToday()) {
                    return;
                }
                $this->notifyOwners($license, LicenseExpirationNotification::STAGE_IMMINENT);
                $license->update(['expiry_warning_sent_at' => now()]);
                $imminent++;
            });

        // 3. In grace period: expired but grace not yet over, grace warning not sent
        License::query()
            ->where('status', License::STATUS_ACTIVE)
            ->where('expires_at', '<', now())
            ->whereRaw('DATE_ADD(expires_at, INTERVAL grace_days DAY) > ?', [now()])
            ->whereNull('grace_warning_sent_at')
            ->with('tenant.owners')
            ->each(function (License $license) use (&$grace) {
                $this->notifyOwners($license, LicenseExpirationNotification::STAGE_GRACE);
                $license->update(['grace_warning_sent_at' => now()]);
                $grace++;
            });

        // 4. Fully expired: past grace period — flip status to EXPIRED, send final notice
        License::query()
            ->where('status', License::STATUS_ACTIVE)
            ->whereRaw('DATE_ADD(expires_at, INTERVAL grace_days DAY) <= ?', [now()])
            ->with('tenant.owners')
            ->each(function (License $license) use (&$expired) {
                $license->update([
                    'status' => License::STATUS_EXPIRED,
                    'expired_notification_sent_at' => now(),
                ]);
                $this->notifyOwners($license, LicenseExpirationNotification::STAGE_EXPIRED);
                $expired++;
            });

        $this->info("Approaching: {$approaching}, Imminent: {$imminent}, Grace: {$grace}, Expired: {$expired}");

        return self::SUCCESS;
    }

    private function notifyOwners(License $license, string $stage): void
    {
        $tenant = $license->tenant;
        if (! $tenant) {
            return;
        }

        $owners = $tenant->owners()->get();
        if ($owners->isEmpty()) {
            return;
        }

        Notification::send($owners, new LicenseExpirationNotification($license, $stage));
    }
}
