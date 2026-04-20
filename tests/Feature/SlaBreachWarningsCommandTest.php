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
use App\Notifications\SlaBreachWarningNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SlaBreachWarningsCommandTest extends TestCase
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

    private function createOverdueTicket(Tenant $tenant, ?User $agent = null): Ticket
    {
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $category = TicketCategory::factory()->create(['tenant_id' => $tenant->id]);
        $creator = User::factory()->create();

        return Ticket::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'ticket_number' => Ticket::generateTicketNumber(),
            'subject' => 'Overdue ticket',
            'description' => 'Test',
            'priority' => 'high',
            'status' => 'open',
            'client_id' => $client->id,
            'department_id' => $department->id,
            'category_id' => $category->id,
            'created_by' => $creator->id,
            'assigned_to' => $agent?->id,
            'resolution_due_at' => now()->subHours(2),
            'response_due_at' => now()->subHours(4),
        ]);
    }

    public function test_command_sends_notification_to_assigned_agent_for_overdue_ticket(): void
    {
        Notification::fake();

        $tenant = $this->createBusinessTenant();
        $agent = User::factory()->create();
        $tenant->addUser($agent, 'member');

        $ticket = $this->createOverdueTicket($tenant, $agent);

        $this->artisan('sla:send-breach-warnings')->assertExitCode(0);

        Notification::assertSentTo($agent, SlaBreachWarningNotification::class);

        $ticket->refresh();
        $this->assertNotNull($ticket->sla_breach_notified_at);
    }

    public function test_command_skips_tickets_with_no_assigned_agent(): void
    {
        Notification::fake();

        $tenant = $this->createBusinessTenant();
        $this->createOverdueTicket($tenant, null);

        $this->artisan('sla:send-breach-warnings')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_command_skips_tenants_without_email_notification_feature(): void
    {
        Notification::fake();

        $plan = Plan::factory()->start()->create([
            'features' => PlanFeature::forPlan('start'),
        ]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);

        $agent = User::factory()->create();
        $tenant->addUser($agent, 'member');

        $this->createOverdueTicket($tenant, $agent);

        $this->artisan('sla:send-breach-warnings')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_command_does_not_resend_after_sla_breach_notified_at_is_set(): void
    {
        Notification::fake();

        $tenant = $this->createBusinessTenant();
        $agent = User::factory()->create();
        $tenant->addUser($agent, 'member');

        $ticket = $this->createOverdueTicket($tenant, $agent);
        $ticket->update(['sla_breach_notified_at' => now()]);

        $this->artisan('sla:send-breach-warnings')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_command_identifies_response_breach_type(): void
    {
        Notification::fake();

        $tenant = $this->createBusinessTenant();
        $agent = User::factory()->create();
        $tenant->addUser($agent, 'member');

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $category = TicketCategory::factory()->create(['tenant_id' => $tenant->id]);
        $creator = User::factory()->create();

        Ticket::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'ticket_number' => Ticket::generateTicketNumber(),
            'subject' => 'Response overdue',
            'description' => 'Test',
            'priority' => 'high',
            'status' => 'open',
            'client_id' => $client->id,
            'department_id' => $department->id,
            'category_id' => $category->id,
            'created_by' => $creator->id,
            'assigned_to' => $agent->id,
            'response_due_at' => now()->subHours(1),
            'first_response_at' => null,
            'resolution_due_at' => now()->addHours(24),
        ]);

        $this->artisan('sla:send-breach-warnings')->assertExitCode(0);

        Notification::assertSentTo($agent, SlaBreachWarningNotification::class, function ($notification) {
            return $notification->breachType === 'response';
        });
    }
}
