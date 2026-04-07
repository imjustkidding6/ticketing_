<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantUrlHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantBrandingTest extends TestCase
{
    use RefreshDatabase;

    private function createBusinessTenant(): Tenant
    {
        $plan = Plan::factory()->business()->create([
            'features' => PlanFeature::forPlan('business'),
        ]);
        $license = License::factory()->active()->forPlan($plan)->create();

        return Tenant::factory()->create(['license_id' => $license->id]);
    }

    private function setupTenantContext(Tenant $tenant): User
    {
        $user = User::factory()->create();
        $tenant->addUser($user, 'member');

        $this->actingAs($user)
            ->withTenant($tenant)
            ->withSession(['current_tenant_id' => $tenant->id]);

        return $user;
    }

    private function brandingUrl(): string
    {
        return $this->tenantUrl('/settings/branding');
    }

    public function test_branding_page_requires_auth(): void
    {
        $tenant = $this->createBusinessTenant();

        $this->get(app(TenantUrlHelper::class)->tenantUrl($tenant, '/settings/branding'))
            ->assertRedirect('/login');
    }

    public function test_branding_page_loads(): void
    {
        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant);

        $this->get($this->brandingUrl())
            ->assertOk()
            ->assertViewIs('settings.branding')
            ->assertSee('Branding Settings');
    }

    public function test_can_upload_logo(): void
    {
        Storage::fake('public');

        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant);

        $this->post($this->brandingUrl(), [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 60),
        ])->assertRedirect();

        $tenant->refresh();
        $this->assertNotNull($tenant->logo_path);
        Storage::disk('public')->assertExists($tenant->logo_path);
    }

    public function test_can_save_colors(): void
    {
        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant);

        $this->post($this->brandingUrl(), [
            'primary_color' => '#ff6600',
            'accent_color' => '#cc5500',
        ])->assertRedirect();

        $tenant->refresh();
        $this->assertEquals('#ff6600', $tenant->primary_color);
        $this->assertEquals('#cc5500', $tenant->accent_color);
    }

    public function test_can_remove_logo(): void
    {
        Storage::fake('public');

        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant);

        $path = UploadedFile::fake()->image('logo.png')->store('tenant-logos', 'public');
        $tenant->update(['logo_path' => $path]);

        $this->post($this->brandingUrl(), [
            'remove_logo' => '1',
        ])->assertRedirect();

        $tenant->refresh();
        $this->assertNull($tenant->logo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_logo_url_returns_null_without_logo(): void
    {
        $tenant = Tenant::factory()->create();
        $this->assertNull($tenant->logoUrl());
    }

    public function test_invalid_color_rejected(): void
    {
        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant);

        $this->post($this->brandingUrl(), [
            'primary_color' => 'not-a-color',
        ])->assertSessionHasErrors('primary_color');
    }

    public function test_portal_shows_tenant_branding(): void
    {
        $tenant = $this->createBusinessTenant();
        $tenant->update([
            'primary_color' => '#e63946',
            'accent_color' => '#457b9d',
        ]);

        $this->get(route('portal.index', ['tenant' => $tenant->slug]))
            ->assertOk()
            ->assertSee('--portal-primary: #e63946')
            ->assertSee('--portal-accent: #457b9d');
    }

    public function test_portal_uses_defaults_without_branding(): void
    {
        $tenant = $this->createBusinessTenant();

        $this->get(route('portal.index', ['tenant' => $tenant->slug]))
            ->assertOk()
            ->assertSee('--portal-primary: #4f46e5')
            ->assertSee('--portal-accent: #4338ca');
    }

    public function test_replacing_logo_deletes_old_file(): void
    {
        Storage::fake('public');

        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant);

        $oldPath = UploadedFile::fake()->image('old-logo.png')->store('tenant-logos', 'public');
        $tenant->update(['logo_path' => $oldPath]);

        $this->post($this->brandingUrl(), [
            'logo' => UploadedFile::fake()->image('new-logo.png', 200, 60),
        ])->assertRedirect();

        $tenant->refresh();
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($tenant->logo_path);
    }
}
