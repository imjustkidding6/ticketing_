<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\Client;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ticket creation touches five tables. Without a transaction a failure part-way
 * through left a live ticket with no SLA and no `created` audit entry — a ticket
 * that exists but has no history of existing.
 */
class TicketTransactionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->create(['slug' => 'business', 'features' => PlanFeature::forPlan('business')]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $this->tenant = Tenant::factory()->create(['license_id' => $license->id]);
        SlaPolicy::factory()->create([
            'tenant_id' => $this->tenant->id, 'priority' => null,
            'client_tier' => null, 'is_active' => true,
        ]);

        $user = User::factory()->create();
        $this->tenant->addUser($user, 'owner');
        $this->actingAs($user)->withTenant($this->tenant)
            ->withSession(['current_tenant_id' => $this->tenant->id]);
    }

    public function test_a_failure_after_the_ticket_row_leaves_no_ticket_behind(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        // Break a write that happens after the ticket row is inserted.
        DB::listen(function ($query) {
            if (str_contains($query->sql, 'insert into `ticket_tasks`')) {
                throw new \RuntimeException('simulated failure mid-create');
            }
        });

        try {
            app(TicketService::class)->createTicket([
                'tenant_id' => $this->tenant->id,
                'client_id' => $client->id,
                'subject' => 'partial', 'description' => 'partial',
                'priority' => 'medium',
                'tasks' => ['a task'],
            ]);
            $this->fail('Expected the simulated failure to propagate');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated failure mid-create', $e->getMessage());
        }

        $this->assertSame(0, Ticket::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count(),
            'A half-created ticket must be rolled back, not left live without SLA or history');
    }

    public function test_a_successful_create_writes_every_table_together(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $ticket = app(TicketService::class)->createTicket([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'subject' => 'whole', 'description' => 'whole',
            'priority' => 'medium',
            'tasks' => ['first task', 'second task'],
        ]);

        $this->assertNotNull($ticket->sla_policy_id, 'SLA assigned');
        $this->assertNotNull($ticket->resolution_due_at, 'deadlines set');
        $this->assertSame(2, $ticket->tasks()->count(), 'tasks written');
        $this->assertSame(
            1,
            TicketHistory::withoutGlobalScopes()->where('ticket_id', $ticket->id)->where('action', 'created')->count(),
            'creation audit entry written'
        );
    }
}
