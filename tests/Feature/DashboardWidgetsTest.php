<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\Client;
use App\Models\Department;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Notifications\TicketCreatedNotification;
use App\Services\TicketService;
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
        $user = $this->setupTenantContext($tenant, 'agent');

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

    public function test_manager_open_ticket_count_includes_all_open_tenant_tickets(): void
    {
        $tenant = $this->createBusinessTenant();
        $manager = $this->setupTenantContext($tenant, 'manager');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $category = TicketCategory::factory()->create(['tenant_id' => $tenant->id, 'department_id' => $department->id]);

        Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'assigned_to' => $manager->id,
            'status' => 'open',
            'client_id' => $client->id,
            'department_id' => $department->id,
            'category_id' => $category->id,
        ]);

        Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'assigned_to' => null,
            'status' => 'assigned',
            'client_id' => $client->id,
            'department_id' => $department->id,
            'category_id' => $category->id,
        ]);

        Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'assigned_to' => User::factory()->create()->id,
            'status' => 'in_progress',
            'client_id' => $client->id,
            'department_id' => $department->id,
            'category_id' => $category->id,
        ]);

        Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'assigned_to' => User::factory()->create()->id,
            'status' => 'closed',
            'client_id' => $client->id,
            'department_id' => $department->id,
            'category_id' => $category->id,
        ]);

        $response = $this->getJson($this->tenantUrl('/dashboard/stats'));
        $this->assertEquals(3, $response->json('myTicketStats.open'));
    }

    public function test_agent_sees_created_ticket_tasks_assigned_from_ticket_assignee(): void
    {
        $tenant = $this->createBusinessTenant();
        $agent = $this->setupTenantContext($tenant, 'agent');

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $category = TicketCategory::factory()->create(['tenant_id' => $tenant->id]);

        $service = app(TicketService::class);
        $service->createTicket([
            'tenant_id' => $tenant->id,
            'subject' => 'Agent task visibility ticket',
            'description' => 'Ticket created with tasks',
            'priority' => 'medium',
            'department_id' => $department->id,
            'category_id' => $category->id,
            'client_id' => $client->id,
            'assigned_to' => $agent->id,
            'tasks' => ['First follow-up task'],
        ]);

        $response = $this->get($this->tenantUrl('/dashboard'));
        $response->assertOk();

        $myTasks = $response->viewData('myTasks');
        $this->assertNotNull($myTasks);
        $this->assertCount(1, $myTasks);
        $this->assertEquals('First follow-up task', $myTasks->first()->description);
        $this->assertEquals($agent->id, $myTasks->first()->assigned_to);
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

    public function test_dashboard_shows_create_ticket_action_for_agents(): void
    {
        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant, 'agent');

        $this->get($this->tenantUrl('/dashboard'))
            ->assertOk()
            ->assertSee('Create Ticket');
    }
}
