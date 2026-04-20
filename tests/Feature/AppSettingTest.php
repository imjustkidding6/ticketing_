<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppSettingTest extends TestCase
{
    use RefreshDatabase;

    private function setupContext(string $planSlug = 'business'): array
    {
        $plan = Plan::factory()->create(['slug' => $planSlug, 'features' => PlanFeature::forPlan($planSlug)]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);
        $user = User::factory()->create();
        $tenant->addUser($user, 'owner');

        $roleService = app(TenantRoleService::class);
        $roleService->setTenantContext($tenant);
        $roleService->setupDefaultRoles($tenant);
        $roleService->syncRole($user, 'admin', $tenant);

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);

        return [$tenant, $user];
    }

    public function test_general_settings_page(): void
    {
        $this->setupContext();
        $this->get($this->tenantUrl('/settings/general'))->assertOk();
    }

    public function test_save_general_settings(): void
    {
        $this->setupContext();

        $this->post($this->tenantUrl('/settings/general'), [
            'company_name' => 'Test Company',
            'company_email' => 'info@test.com',
            'company_phone' => '+1234567890',
            'timezone' => 'Asia/Manila',
        ])->assertRedirect();

        $this->assertDatabaseHas('app_settings', ['key' => 'company_name', 'value' => 'Test Company']);
    }

    public function test_ticket_settings_page(): void
    {
        $this->setupContext();
        $this->get($this->tenantUrl('/settings/ticket'))->assertOk();
    }

    public function test_notification_settings_feature_gated(): void
    {
        $this->setupContext('start');
        $this->get($this->tenantUrl('/settings/notifications'))->assertForbidden();
    }

    public function test_notification_settings_accessible_for_business(): void
    {
        $this->setupContext('business');
        $this->get($this->tenantUrl('/settings/notifications'))->assertOk();
    }

    public function test_save_notification_settings(): void
    {
        $this->setupContext('business');

        $this->post($this->tenantUrl('/settings/notifications'), [
            'notify_on_ticket_create' => '1',
            'mail_host' => 'smtp.example.com',
            'mail_port' => 587,
            'mail_from_address' => 'noreply@test.com',
            'mail_from_name' => 'Test Support',
        ])->assertRedirect();

        $this->assertDatabaseHas('app_settings', ['key' => 'mail_host', 'value' => 'smtp.example.com']);
    }

    public function test_branding_settings_page(): void
    {
        $this->setupContext();
        $this->get($this->tenantUrl('/settings/branding'))->assertOk();
    }

    public function test_save_branding_colors(): void
    {
        [$tenant] = $this->setupContext();

        $this->post($this->tenantUrl('/settings/branding'), [
            'primary_color' => '#ff5500',
            'accent_color' => '#0055ff',
        ])->assertRedirect();

        $tenant->refresh();
        $this->assertEquals('#ff5500', $tenant->primary_color);
    }
}
