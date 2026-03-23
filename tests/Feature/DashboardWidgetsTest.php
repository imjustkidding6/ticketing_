<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
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

    private function setupTenantContext(Tenant $tenant, string $role = 'owner'): User
    {
        $user = User::factory()->create();
        $tenant->addUser($user, $role);

        $this->actingAs($user)
            ->withTenant($tenant)
            ->withSession(['current_tenant_id' => $tenant->id]);

        return $user;
    }

    public function test_dashboard_loads_with_personalized_data(): void
    {
        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant);

        $this->get($this->tenantUrl('/dashboard'))
            ->assertOk()
            ->assertViewHas('myTicketStats')
            ->assertViewHas('myPerformance')
            ->assertViewHas('myTickets')
            ->assertViewHas('myTasks')
            ->assertViewHas('myActivity')
            ->assertViewHas('profileSummary');
    }

    public function test_dashboard_stats_endpoint_returns_json(): void
    {
        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant);

        $this->getJson($this->tenantUrl('/dashboard/stats'))
            ->assertOk()
            ->assertJsonStructure([
                'myTicketStats' => ['open', 'in_progress', 'closed_this_month', 'total_closed'],
                'myPerformance' => ['resolved_today', 'avg_resolution_hours', 'avg_work_hours'],
                'myTrend',
                'myTicketsByStatus',
                'myTicketsByPriority',
                'profileSummary',
            ]);
    }

    public function test_stats_endpoint_requires_auth(): void
    {
        $tenant = Tenant::factory()->create();

        $this->getJson(app(\App\Services\TenantUrlHelper::class)->tenantUrl($tenant, '/dashboard/stats'))
            ->assertUnauthorized();
    }

    public function test_my_trend_fills_14_days(): void
    {
        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant);

        $response = $this->getJson($this->tenantUrl('/dashboard/stats'));
        $trends = $response->json('myTrend');

        $this->assertCount(14, $trends);
    }

    public function test_notification_recent_returns_json(): void
    {
        $tenant = $this->createBusinessTenant();
        $user = $this->setupTenantContext($tenant);

        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id]);
        $user->notify(new TicketCreatedNotification($ticket));

        $this->getJson($this->tenantUrl('/notifications/recent'))
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_mark_notification_as_read(): void
    {
        $tenant = $this->createBusinessTenant();
        $user = $this->setupTenantContext($tenant);

        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id]);
        $user->notify(new TicketCreatedNotification($ticket));

        $notification = $user->notifications()->first();
        $this->assertNull($notification->read_at);

        $this->postJson($this->tenantUrl("/notifications/{$notification->id}/read"))
            ->assertOk();

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    public function test_mark_all_notifications_read(): void
    {
        $tenant = $this->createBusinessTenant();
        $user = $this->setupTenantContext($tenant);

        $tickets = Ticket::factory()->count(3)->create(['tenant_id' => $tenant->id]);
        foreach ($tickets as $ticket) {
            $user->notify(new TicketCreatedNotification($ticket));
        }

        $this->assertEquals(3, $user->unreadNotifications()->count());

        $this->postJson($this->tenantUrl('/notifications/mark-all-read'))
            ->assertOk();

        $this->assertEquals(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_unread_count_endpoint(): void
    {
        $tenant = $this->createBusinessTenant();
        $user = $this->setupTenantContext($tenant);

        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id]);
        $user->notify(new TicketCreatedNotification($ticket));
        $user->notify(new TicketCreatedNotification($ticket));

        $this->getJson($this->tenantUrl('/notifications/unread-count'))
            ->assertOk()
            ->assertJson(['count' => 2]);
    }

    public function test_my_tickets_only_shows_assigned_to_user(): void
    {
        $tenant = $this->createBusinessTenant();
        $user = $this->setupTenantContext($tenant);

        Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'assigned_to' => $user->id,
            'status' => 'open',
        ]);

        Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'assigned_to' => User::factory()->create()->id,
            'status' => 'open',
        ]);

        $response = $this->getJson($this->tenantUrl('/dashboard/stats'));
        $this->assertEquals(1, $response->json('myTicketStats.open'));
    }

    public function test_dashboard_shows_admin_flag_for_owner(): void
    {
        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant, 'owner');

        $this->get($this->tenantUrl('/dashboard'))
            ->assertOk()
            ->assertViewHas('isAdminOrOwner', true);
    }

    public function test_dashboard_hides_admin_flag_for_member(): void
    {
        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant, 'member');

        $this->get($this->tenantUrl('/dashboard'))
            ->assertOk()
            ->assertViewHas('isAdminOrOwner', false);
    }
}
