<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The tour endpoint. Content-level scoping lives in HelpCentreTest; this covers
 * what the endpoint itself promises — that the gate is on the server, and that
 * refusing looks like a refusal rather than a broken feature.
 */
class GuidedTourTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOnPlan(string $slug = 'enterprise'): Tenant
    {
        $plan = Plan::where('slug', $slug)->first()
            ?? Plan::factory()->create(['slug' => $slug, 'features' => PlanFeature::forPlan($slug)]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);
        SlaPolicy::factory()->create([
            'tenant_id' => $tenant->id, 'priority' => null,
            'client_tier' => null, 'is_active' => true,
        ]);

        return $tenant;
    }

    private function actor(Tenant $tenant, string $role): User
    {
        $user = User::factory()->create();
        $tenant->addUser($user, 'member');
        $rs = app(TenantRoleService::class);
        $rs->setTenantContext($tenant);
        $rs->setupDefaultRoles($tenant);
        $rs->syncRole($user, $role, $tenant);
        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);

        return $user;
    }

    public function test_a_tour_is_served_to_a_user_who_can_complete_it(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant, 'admin');

        $this->getJson($this->tenantUrl('/tours/managing-tickets'))
            ->assertOk()
            ->assertJsonPath('tour.key', 'managing-tickets')
            ->assertJsonStructure(['tour' => ['key', 'title', 'minutes', 'start_url', 'steps']]);
    }

    public function test_step_pages_are_resolved_to_real_urls(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant, 'admin');

        $steps = $this->getJson($this->tenantUrl('/tours/managing-tickets'))->json('tour.steps');

        // Without this the runner can only announce that the next step is
        // elsewhere; it cannot take the reader there, and a tour started from
        // the help centre dead-ends on its first step.
        $this->assertNotNull($steps[0]['url']);
        $this->assertStringContainsString('/tickets', $steps[0]['url']);
    }

    public function test_the_endpoint_returns_403_not_an_empty_tour(): void
    {
        $tenant = $this->tenantOnPlan();
        // A viewer holds view-only permissions, so being taught to raise a
        // ticket is a dead end.
        $this->actor($tenant, 'viewer');

        $this->getJson($this->tenantUrl('/tours/managing-tickets'))
            ->assertForbidden()
            ->assertJsonMissingPath('tour');
    }

    public function test_an_unknown_tour_key_is_a_404(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant, 'admin');

        $this->getJson($this->tenantUrl('/tours/no-such-tour'))->assertNotFound();
    }

    public function test_the_launcher_only_lists_tours_the_user_can_take(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant, 'admin');
        $adminKeys = collect($this->getJson($this->tenantUrl('/tours'))->json('tours'))->pluck('key');
        $this->assertContains('managing-tickets', $adminKeys);

        $this->actor($tenant, 'viewer');
        $viewerKeys = collect($this->getJson($this->tenantUrl('/tours'))->json('tours'))->pluck('key');
        $this->assertNotContains('managing-tickets', $viewerKeys);
    }

    public function test_a_topic_is_hidden_when_the_plan_lacks_its_feature(): void
    {
        // The AI topic is documented only where the plan actually has AI —
        // permission and plan are different questions.
        $business = $this->tenantOnPlan('business');
        $this->actor($business, 'admin');

        // Assert on the topic itself: "AI Assistant" also names the floating
        // widget in the layout, so a bare string check proves nothing.
        $this->get($this->tenantUrl('/tutorials'))
            ->assertOk()
            ->assertSee('data-help-topic="managing-tickets"', false)
            ->assertDontSee('data-help-topic="ai-assistant"', false);
    }

    public function test_a_tour_can_be_started_twice_in_a_row(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant, 'admin');

        // No first-run flag, nothing to reset.
        $this->getJson($this->tenantUrl('/tours/managing-tickets?start=1'))->assertOk();
        $this->getJson($this->tenantUrl('/tours/managing-tickets?start=1'))->assertOk();
    }
}
