<?php

namespace App\Services;

use App\Models\AppSetting;

class TenantMailService
{
    /**
     * The mail config as it was before any tenant touched it, captured once.
     *
     * @var array<string, mixed>|null
     */
    private static ?array $baseline = null;

    /**
     * Configure the mailer using tenant SMTP settings.
     *
     * This mutates global runtime config, so it is only safe if every path that
     * sets it also unsets it. A worker processes many tenants in one process:
     * without the reset below, a tenant with no SMTP of its own would inherit
     * whatever the previous tenant left behind and send through their server
     * and From address.
     */
    public static function configure(?int $tenantId = null): void
    {
        self::captureBaseline();

        $tenantId = $tenantId ?? session('current_tenant_id');

        if (! $tenantId) {
            self::reset();

            return;
        }

        $settings = AppSetting::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('group', 'notifications')
            ->get()
            ->mapWithKeys(fn ($s) => [$s->key => $s->getTypedValue()])
            ->toArray();

        // No tenant SMTP configured — fall back to the application defaults,
        // never to the previously configured tenant.
        if (empty($settings['mail_host'])) {
            self::reset();

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
            'mail.from.address' => $settings['mail_from_address'] ?? self::$baseline['from_address'],
            'mail.from.name' => $settings['mail_from_name'] ?? self::$baseline['from_name'],
        ]);
    }

    /**
     * Restore the application's own mail configuration.
     *
     * Call this after sending on behalf of a tenant, or whenever no tenant
     * context applies. Safe to call repeatedly and before any configure().
     */
    public static function reset(): void
    {
        self::captureBaseline();

        config([
            'mail.default' => self::$baseline['default'],
            'mail.from.address' => self::$baseline['from_address'],
            'mail.from.name' => self::$baseline['from_name'],
        ]);

        // Drop the tenant mailer entirely so a stale definition can't be selected.
        $mailers = config('mail.mailers', []);
        unset($mailers['tenant_smtp']);
        config(['mail.mailers' => $mailers]);
    }

    /**
     * Run a callback with a tenant's mail configuration, restoring it afterwards
     * even if the callback throws.
     *
     * @template TReturn
     *
     * @param  \Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function usingTenant(?int $tenantId, \Closure $callback): mixed
    {
        self::configure($tenantId);

        try {
            return $callback();
        } finally {
            self::reset();
        }
    }

    /**
     * Remember the application's mail defaults the first time we touch config.
     */
    private static function captureBaseline(): void
    {
        if (self::$baseline !== null) {
            return;
        }

        self::$baseline = [
            'default' => config('mail.default'),
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
        ];
    }

    /**
     * Forget the captured baseline. Tests only — a fresh process re-captures.
     */
    public static function forgetBaseline(): void
    {
        self::$baseline = null;
    }
}
