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

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function setupContext(string $planSlug = 'business'): array
    {
        $plan = Plan::factory()->create(['slug' => $planSlug, 'features' => PlanFeature::forPlan($planSlug)]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);
        $user = User::factory()->create();
        $tenant->addUser($user, 'admin');

        $roleService = app(TenantRoleService::class);
        $roleService->setTenantContext($tenant);
        $roleService->setupDefaultRoles($tenant);
        $roleService->syncRole($user, 'admin', $tenant);

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);

        return [$tenant, $user];
    }

    public function test_overview_report(): void
    {
        $this->setupContext();
        $this->get($this->tenantUrl('/reports'))->assertOk();
    }

    public function test_department_report(): void
    {
        $this->setupContext();
        $this->get($this->tenantUrl('/reports/departments'))->assertOk();
    }

    public function test_category_report(): void
    {
        $this->setupContext();
        $this->get($this->tenantUrl('/reports/categories'))->assertOk();
    }

    public function test_client_report(): void
    {
        $this->setupContext();
        $this->get($this->tenantUrl('/reports/clients'))->assertOk();
    }

    public function test_agent_report(): void
    {
        $this->setupContext();
        $this->get($this->tenantUrl('/reports/agents'))->assertOk();
    }

    public function test_product_report(): void
    {
        $this->setupContext();
        $this->get($this->tenantUrl('/reports/products'))->assertOk();
    }

    public function test_tickets_report(): void
    {
        $this->setupContext();
        $this->get($this->tenantUrl('/reports/tickets'))->assertOk();
    }

    public function test_billing_report_feature_gated(): void
    {
        $this->setupContext('business');
        $this->get($this->tenantUrl('/reports/billing'))->assertOk();
    }

    public function test_billing_report_blocked_for_starter(): void
    {
        $this->setupContext('start');
        $this->get($this->tenantUrl('/reports/billing'))->assertForbidden();
    }

    public function test_sla_compliance_report(): void
    {
        $this->setupContext('business');
        $this->get($this->tenantUrl('/reports/sla-compliance'))->assertOk();
    }

    public function test_export_available_for_starter(): void
    {
        $this->setupContext('start');
        $this->get($this->tenantUrl('/reports/export/volume'))->assertOk();
    }

    public function test_agent_can_view_reports(): void
    {
        $plan = Plan::factory()->create(['slug' => 'biz4', 'features' => PlanFeature::forPlan('business')]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);
        $user = User::factory()->create();
        $tenant->addUser($user, 'member');

        $roleService = app(TenantRoleService::class);
        $roleService->setTenantContext($tenant);
        $roleService->setupDefaultRoles($tenant);
        $roleService->syncRole($user, 'agent', $tenant);

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);

        $this->get($this->tenantUrl('/reports'))->assertOk();
    }
}
