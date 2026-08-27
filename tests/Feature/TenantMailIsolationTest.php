<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Tenant;
use App\Services\TenantMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TenantMailService mutates global runtime config. These tests pin the rule that
 * makes that safe: configuration must never survive past the tenant it was set for.
 */
class TenantMailIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantMailService::forgetBaseline();
        config([
            'mail.default' => 'array',
            'mail.from.address' => 'app@example.com',
            'mail.from.name' => 'App Default',
        ]);
    }

    private function tenantWithSmtp(string $host, string $from): Tenant
    {
        $tenant = Tenant::factory()->create();

        foreach ([
            'mail_host' => $host,
            'mail_port' => '587',
            'mail_username' => 'user',
            'mail_password' => 'secret',
            'mail_from_address' => $from,
            'mail_from_name' => 'Tenant Sender',
        ] as $key => $value) {
            AppSetting::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'group' => 'notifications',
                'key' => $key,
                'value' => $value,
            ]);
        }

        return $tenant;
    }

    public function test_tenant_without_smtp_does_not_inherit_the_previous_tenants_mailer(): void
    {
        $configured = $this->tenantWithSmtp('smtp.tenant-a.test', 'a@tenant-a.test');
        $unconfigured = Tenant::factory()->create();

        TenantMailService::configure($configured->id);
        $this->assertSame('tenant_smtp', config('mail.default'));
        $this->assertSame('smtp.tenant-a.test', config('mail.mailers.tenant_smtp.host'));

        // The critical case: a tenant with no SMTP of its own must fall back to the
        // application defaults, never to whatever the previous tenant left behind.
        TenantMailService::configure($unconfigured->id);

        $this->assertSame('array', config('mail.default'));
        $this->assertSame('app@example.com', config('mail.from.address'));
        $this->assertNull(config('mail.mailers.tenant_smtp'));
    }

    public function test_one_tenants_smtp_never_carries_into_another(): void
    {
        $a = $this->tenantWithSmtp('smtp.tenant-a.test', 'a@tenant-a.test');
        $b = $this->tenantWithSmtp('smtp.tenant-b.test', 'b@tenant-b.test');

        TenantMailService::configure($a->id);
        TenantMailService::configure($b->id);

        $this->assertSame('smtp.tenant-b.test', config('mail.mailers.tenant_smtp.host'));
        $this->assertSame('b@tenant-b.test', config('mail.from.address'));
    }

    public function test_reset_restores_application_defaults(): void
    {
        $a = $this->tenantWithSmtp('smtp.tenant-a.test', 'a@tenant-a.test');

        TenantMailService::configure($a->id);
        TenantMailService::reset();

        $this->assertSame('array', config('mail.default'));
        $this->assertSame('app@example.com', config('mail.from.address'));
        $this->assertSame('App Default', config('mail.from.name'));
        $this->assertNull(config('mail.mailers.tenant_smtp'));
    }

    public function test_using_tenant_restores_config_even_when_the_callback_throws(): void
    {
        $a = $this->tenantWithSmtp('smtp.tenant-a.test', 'a@tenant-a.test');

        try {
            TenantMailService::usingTenant($a->id, function () {
                throw new \RuntimeException('send failed');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame('array', config('mail.default'),
            'A failed send must not leave the tenant mailer configured for the next job');
    }

    public function test_configure_with_no_tenant_falls_back_to_defaults(): void
    {
        $a = $this->tenantWithSmtp('smtp.tenant-a.test', 'a@tenant-a.test');

        TenantMailService::configure($a->id);
        TenantMailService::configure(null);

        $this->assertSame('array', config('mail.default'));
    }
}
