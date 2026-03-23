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
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketStatusChangedNotification;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TicketNotificationTest extends TestCase
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

    /**
     * @return array{Client, Department, TicketCategory}
     */
    private function createTicketDependencies(Tenant $tenant, ?int $userId = null): array
    {
        $client = Client::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $userId,
        ]);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $category = TicketCategory::factory()->create(['tenant_id' => $tenant->id]);

        return [$client, $department, $category];
    }

    public function test_ticket_created_notification_sent_to_client_user_on_business_plan(): void
    {
        Notification::fake();

        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant);

        $clientUser = User::factory()->create();
        [$client, $department, $category] = $this->createTicketDependencies($tenant, $clientUser->id);

        $service = app(TicketService::class);
        $service->createTicket([
            'tenant_id' => $tenant->id,
            'subject' => 'Test ticket',
            'description' => 'Test description',
            'priority' => 'medium',
            'department_id' => $department->id,
            'category_id' => $category->id,
            'client_id' => $client->id,
        ]);

        Notification::assertSentTo($clientUser, TicketCreatedNotification::class);
    }

    public function test_ticket_created_notification_sent_on_demand_for_guest_client(): void
    {
        Notification::fake();

        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant);

        [$client, $department, $category] = $this->createTicketDependencies($tenant);

        $service = app(TicketService::class);
        $service->createTicket([
            'tenant_id' => $tenant->id,
            'subject' => 'Guest ticket',
            'description' => 'Test description',
            'priority' => 'low',
            'department_id' => $department->id,
            'category_id' => $category->id,
            'client_id' => $client->id,
        ]);

        Notification::assertSentOnDemand(TicketCreatedNotification::class, function ($notification, $channels, $notifiable) use ($client) {
            return $notifiable->routes['mail'] === $client->email;
        });
    }

    public function test_ticket_created_notification_not_sent_on_starter_plan(): void
    {
        Notification::fake();

        $tenant = $this->createStarterTenant();
        $this->setupTenantContext($tenant);

        [$client, $department, $category] = $this->createTicketDependencies($tenant);

        $service = app(TicketService::class);
        $service->createTicket([
            'tenant_id' => $tenant->id,
            'subject' => 'Starter ticket',
            'description' => 'Test description',
            'priority' => 'medium',
            'department_id' => $department->id,
            'category_id' => $category->id,
            'client_id' => $client->id,
        ]);

        Notification::assertNothingSent();
    }

    public function test_ticket_assigned_notification_sent_to_agent_on_business_plan(): void
    {
        Notification::fake();

        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant);

        $agent = User::factory()->create();
        $tenant->addUser($agent, 'member');

        [$client, $department, $category] = $this->createTicketDependencies($tenant);

        $service = app(TicketService::class);
        $ticket = $service->createTicket([
            'tenant_id' => $tenant->id,
            'subject' => 'Assign test',
            'description' => 'Test description',
            'priority' => 'high',
            'department_id' => $department->id,
            'category_id' => $category->id,
            'client_id' => $client->id,
        ]);

        Notification::fake();

        $service->assignTicket($ticket, $agent);

        Notification::assertSentTo($agent, TicketAssignedNotification::class);
    }

    public function test_ticket_assigned_notification_not_sent_on_starter_plan(): void
    {
        Notification::fake();

        $tenant = $this->createStarterTenant();
        $this->setupTenantContext($tenant);

        $agent = User::factory()->create();
        $tenant->addUser($agent, 'member');

        [$client, $department, $category] = $this->createTicketDependencies($tenant);

        $service = app(TicketService::class);
        $ticket = $service->createTicket([
            'tenant_id' => $tenant->id,
            'subject' => 'No notification test',
            'description' => 'Test description',
            'priority' => 'medium',
            'department_id' => $department->id,
            'category_id' => $category->id,
            'client_id' => $client->id,
        ]);

        $service->assignTicket($ticket, $agent);

        Notification::assertNothingSent();
    }

    public function test_status_change_notification_sent_to_client_user(): void
    {
        Notification::fake();

        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant);

        $clientUser = User::factory()->create();
        [$client, $department, $category] = $this->createTicketDependencies($tenant, $clientUser->id);

        $service = app(TicketService::class);
        $ticket = $service->createTicket([
            'tenant_id' => $tenant->id,
            'subject' => 'Status change test',
            'description' => 'Test description',
            'priority' => 'medium',
            'department_id' => $department->id,
            'category_id' => $category->id,
            'client_id' => $client->id,
        ]);

        Notification::fake();

        $service->changeStatus($ticket, 'in_progress');

        Notification::assertSentTo($clientUser, TicketStatusChangedNotification::class, function ($notification) {
            return $notification->oldStatus === 'open' && $notification->newStatus === 'in_progress';
        });
    }

    public function test_status_change_notification_sent_on_demand_for_guest_client(): void
    {
        Notification::fake();

        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant);

        [$client, $department, $category] = $this->createTicketDependencies($tenant);

        $service = app(TicketService::class);
        $ticket = $service->createTicket([
            'tenant_id' => $tenant->id,
            'subject' => 'Guest status change',
            'description' => 'Test description',
            'priority' => 'medium',
            'department_id' => $department->id,
            'category_id' => $category->id,
            'client_id' => $client->id,
        ]);

        $ticket->tasks()->create([
            'description' => 'Test task',
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        Notification::fake();

        $service->changeStatus($ticket, 'closed');

        Notification::assertSentOnDemand(TicketStatusChangedNotification::class, function ($notification, $channels, $notifiable) use ($client) {
            return $notifiable->routes['mail'] === $client->email;
        });
    }

    public function test_via_returns_only_mail_for_anonymous_notifiable(): void
    {
        $notification = new TicketCreatedNotification(
            Ticket::factory()->make()
        );

        $anonymous = new AnonymousNotifiable;
        $this->assertEquals(['mail'], $notification->via($anonymous));

        $user = User::factory()->make();
        $this->assertEquals(['mail', 'database'], $notification->via($user));
    }
}
