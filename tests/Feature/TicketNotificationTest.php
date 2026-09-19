<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\Client;
use App\Models\Department;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Notifications\ClientTicketCreatedNotification;
use App\Notifications\ClientTicketStatusChangedNotification;
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketCreatedNotification;
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

        $tenant = Tenant::factory()->create(['license_id' => $license->id]);

        // Business+ tenants enforce the SLA-policy guard when a ticket priority is set;
        // seed a catch-all policy (any tier, any priority) so ticket creation succeeds.
        SlaPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'priority' => null,
            'client_tier' => null,
            'is_active' => true,
        ]);

        return $tenant;
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

        Notification::assertSentTo($clientUser, ClientTicketCreatedNotification::class);
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

        Notification::assertSentOnDemand(ClientTicketCreatedNotification::class, function ($notification, $channels, $notifiable) use ($client) {
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

        Notification::assertSentTo($clientUser, ClientTicketStatusChangedNotification::class, function ($notification) {
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

        Notification::assertSentOnDemand(ClientTicketStatusChangedNotification::class, function ($notification, $channels, $notifiable) use ($client) {
            return $notifiable->routes['mail'] === $client->email;
        });
    }

    /**
     * Renders the status-change email body for a given status.
     */
    private function renderStatusEmail(Ticket $ticket, string $newStatus): string
    {
        $notification = new ClientTicketStatusChangedNotification($ticket, 'in_progress', $newStatus);

        return (string) $notification->toMail(new AnonymousNotifiable)->render();
    }

    public function test_pending_email_asks_the_customer_to_reply(): void
    {
        // This email is the channel that reaches the customer at the moment they
        // have to act. Falling through to "our team continues to work on your
        // request" is how a pending ticket sits unanswered until the auto-close
        // sweep closes it — the customer was told nothing was needed from them.
        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant);

        [$client, $department, $category] = $this->createTicketDependencies($tenant);

        $ticket = app(TicketService::class)->createTicket([
            'tenant_id' => $tenant->id,
            'subject' => 'Pending copy',
            'description' => 'Test description',
            'priority' => 'medium',
            'department_id' => $department->id,
            'category_id' => $category->id,
            'client_id' => $client->id,
        ]);

        $body = $this->renderStatusEmail($ticket, 'pending');

        $this->assertStringContainsString('waiting for your reply', $body);
        $this->assertStringContainsString('closed automatically', $body);
        $this->assertStringNotContainsString('Our team continues to work on your request', $body);
        $this->assertStringNotContainsString("We've made progress", $body);
    }

    public function test_every_status_has_its_own_email_copy(): void
    {
        // The generic fallback is only honest for 'open'. Any other status landing
        // on it means a status shipped without copy — which is exactly how the
        // pending arm was missed.
        $tenant = $this->createBusinessTenant();
        $this->setupTenantContext($tenant);

        [$client, $department, $category] = $this->createTicketDependencies($tenant);

        $ticket = app(TicketService::class)->createTicket([
            'tenant_id' => $tenant->id,
            'subject' => 'Status copy sweep',
            'description' => 'Test description',
            'priority' => 'medium',
            'department_id' => $department->id,
            'category_id' => $category->id,
            'client_id' => $client->id,
        ]);

        foreach (array_diff(Ticket::STATUSES, ['open']) as $status) {
            $this->assertStringNotContainsString(
                'Our team continues to work on your request',
                $this->renderStatusEmail($ticket, $status),
                "The status-change email has no copy for '{$status}' and falls through to the generic line.",
            );
        }
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
