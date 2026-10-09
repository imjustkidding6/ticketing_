<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\AppSetting;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PlanService;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Follow-up 40: the create form never told the engineer what leaving Department empty
 * costs them. Two wordings, chosen by the AI gating — a Business workspace must not be
 * told about expertise it has not bought, but it does still lose the engineer suggestion.
 */
class TicketCreateDepartmentHintTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOnPlan(string $planSlug): Tenant
    {
        $plan = Plan::query()->where('slug', $planSlug)->first()
            ?? Plan::factory()->create(['slug' => $planSlug, 'features' => PlanFeature::forPlan($planSlug)]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);

        SlaPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'priority' => null,
            'client_tier' => null,
            'is_active' => true,
        ]);

        app(PlanService::class)->clearCache($tenant);

        return $tenant;
    }

    private function actor(Tenant $tenant): User
    {
        $user = User::factory()->create();
        $tenant->addUser($user, 'member');

        $roles = app(TenantRoleService::class);
        $roles->setupDefaultRoles($tenant);
        $roles->syncRole($user, 'admin', $tenant);

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);
        session(['current_tenant_id' => $tenant->id]);

        return $user;
    }

    public function test_the_create_form_warns_what_leaving_department_empty_costs(): void
    {
        $tenant = $this->tenantOnPlan('enterprise');
        $this->actor($tenant);
        AppSetting::set('ai_enabled', true, 'boolean', 'ai');
        AppSetting::set('ai_agent_copilot_enabled', true, 'boolean', 'ai');

        $this->get($this->tenantUrl('/tickets/create'))
            ->assertOk()
            ->assertSee('Without a department we can\'t suggest an engineer, and the AI has none of this department\'s expertise behind its priority suggestion.');
    }

    public function test_the_warning_omits_the_ai_expertise_clause_on_a_workspace_without_ai(): void
    {
        $tenant = $this->tenantOnPlan('business');
        $this->actor($tenant);

        $response = $this->get($this->tenantUrl('/tickets/create'));

        $response->assertOk()
            ->assertSee('Without a department we can\'t suggest an engineer for this ticket.');

        $this->assertStringNotContainsString('expertise behind its priority suggestion', $response->getContent());
    }
}
