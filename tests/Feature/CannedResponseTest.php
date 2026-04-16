<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\CannedResponse;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantUrlHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CannedResponseTest extends TestCase
{
    use RefreshDatabase;

    private function createBusinessTenant(): Tenant
    {
        $plan = Plan::factory()->create([
            'slug' => 'business',
            'features' => PlanFeature::forPlan('business'),
        ]);
        $license = License::factory()->active()->forPlan($plan)->create();

        return Tenant::factory()->create(['license_id' => $license->id]);
    }

    private function createStarterTenant(): Tenant
    {
        $plan = Plan::factory()->start()->create([
            'features' => PlanFeature::forPlan('start'),
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

    public function test_index_requires_auth(): void
    {
        $tenant = $this->createBusinessTenant();

        $this->get(app(TenantUrlHelper::class)->tenantUrl($tenant, '/canned-responses'))
            ->assertRedirect('/login');
    }

    public function test_index_returns_403_for_starter_plan(): void
    {
        $tenant = $this->createStarterTenant();
        $this->setupTenantContext($tenant);

        $this->get($this->tenantUrl('/canned-responses'))
            ->assertForbidden();
    }

    public function test_index_works_for_business_plan(): void
    {
        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant);

        $this->get($this->tenantUrl('/canned-responses'))
            ->assertOk()
            ->assertViewIs('canned-responses.index');
    }

    public function test_can_create_canned_response(): void
    {
        $tenant = $this->createBusinessTenant();
        $user = $this->setupTenantContext($tenant);

        $this->post($this->tenantUrl('/canned-responses'), [
            'name' => 'Greeting',
            'category' => 'General',
            'content' => 'Hello! Thank you for contacting us.',
            'shortcut' => '/greet',
            'sort_order' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('canned_responses', [
            'tenant_id' => $tenant->id,
            'name' => 'Greeting',
            'category' => 'General',
            'created_by' => $user->id,
        ]);
    }

    public function test_can_update_canned_response(): void
    {
        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant);

        $response = CannedResponse::factory()->create(['tenant_id' => $tenant->id]);

        $this->put($this->tenantUrl("/canned-responses/{$response->id}"), [
            'name' => 'Updated Name',
            'category' => 'Billing',
            'content' => 'Updated content here.',
        ])->assertRedirect();

        $response->refresh();
        $this->assertEquals('Updated Name', $response->name);
        $this->assertEquals('Billing', $response->category);
    }

    public function test_can_delete_canned_response(): void
    {
        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant);

        $response = CannedResponse::factory()->create(['tenant_id' => $tenant->id]);

        $this->delete($this->tenantUrl("/canned-responses/{$response->id}"))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('canned_responses', ['id' => $response->id]);
    }

    public function test_index_filters_by_category(): void
    {
        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant);

        CannedResponse::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Billing Response',
            'category' => 'Billing',
        ]);

        CannedResponse::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Technical Response',
            'category' => 'Technical',
        ]);

        $this->get($this->tenantUrl('/canned-responses?category=Billing'))
            ->assertOk()
            ->assertSee('Billing Response')
            ->assertDontSee('Technical Response');
    }

    public function test_index_filters_by_search(): void
    {
        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant);

        CannedResponse::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Password Reset',
            'content' => 'Please reset your password.',
        ]);

        CannedResponse::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Greeting',
            'content' => 'Hello there!',
        ]);

        $this->get($this->tenantUrl('/canned-responses?search=Password'))
            ->assertOk()
            ->assertSee('Password Reset')
            ->assertDontSee('Greeting');
    }

    public function test_list_endpoint_returns_json(): void
    {
        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant);

        CannedResponse::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Response',
            'category' => 'General',
            'content' => 'Test content',
        ]);

        $this->getJson($this->tenantUrl('/canned-responses/list'))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Test Response']);
    }

    public function test_list_endpoint_respects_tenant_scoping(): void
    {
        $plan = Plan::factory()->create([
            'slug' => 'business',
            'features' => PlanFeature::forPlan('business'),
        ]);

        $license1 = License::factory()->active()->forPlan($plan)->create();
        $license2 = License::factory()->active()->forPlan($plan)->create();
        $tenant1 = Tenant::factory()->create(['license_id' => $license1->id]);
        $tenant2 = Tenant::factory()->create(['license_id' => $license2->id]);

        CannedResponse::factory()->create([
            'tenant_id' => $tenant1->id,
            'name' => 'Tenant 1 Response',
        ]);

        CannedResponse::factory()->create([
            'tenant_id' => $tenant2->id,
            'name' => 'Tenant 2 Response',
        ]);

        $this->setupTenantContext($tenant1);

        $this->getJson($this->tenantUrl('/canned-responses/list'))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Tenant 1 Response']);
    }
}
