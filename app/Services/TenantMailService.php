<?php

namespace App\Services;

use App\Models\AppSetting;

class TenantMailService
{
    /**
     * Configure the mailer using tenant SMTP settings.
     * Falls back to .env defaults if tenant settings are empty.
     */
    public static function configure(?int $tenantId = null): void
    {
        $tenantId = $tenantId ?? session('current_tenant_id');

        if (! $tenantId) {
            return;
        }

        $settings = AppSetting::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('group', 'notifications')
            ->get()
            ->mapWithKeys(fn ($s) => [$s->key => $s->getTypedValue()])
            ->toArray();

        if (empty($settings['mail_host'])) {
            return;
        }

        $encryption = ($settings['mail_encryption'] ?? 'tls') === 'none' ? null : ($settings['mail_encryption'] ?? 'tls');

        config([
            'mail.default' => 'tenant_smtp',
            'mail.mailers.tenant_smtp' => [
                'transport' => 'smtp',
                'host' => $settings['mail_host'],
                'port' => (int) ($settings['mail_port'] ?? 587),
                'username' => $settings['mail_username'] ?? null,
                'password' => $settings['mail_password'] ?? null,
                'encryption' => $encryption,
            ],
            'mail.from.address' => $settings['mail_from_address'] ?? config('mail.from.address'),
            'mail.from.name' => $settings['mail_from_name'] ?? config('mail.from.name'),
        ]);
    }
}
