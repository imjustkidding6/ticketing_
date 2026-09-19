<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\ApiToken;
use App\Models\Client;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * tickets.status is a varchar now, so the database will accept any string.
 * Ticket::STATUSES is the only enumeration left and these tests pin that every
 * door a status can come through refuses an unknown one — and, crucially, that
 * nothing is persisted when it does.
 */
class UnknownTicketStatusTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->create(['slug' => 'business', 'features' => PlanFeature::forPlan('business')]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $this->tenant = Tenant::factory()->create(['license_id' => $license->id]);

        SlaPolicy::factory()->create([
            'tenant_id' => $this->tenant->id,
            'priority' => null,
            'client_tier' => null,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
        $this->tenant->addUser($this->user, 'owner');
        $this->actingAs($this->user)->withTenant($this->tenant)
            ->withSession(['current_tenant_id' => $this->tenant->id]);
    }

    private function ticket(): Ticket
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        return app(TicketService::class)->createTicket([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'subject' => 'status guard',
            'description' => 'status guard',
            'priority' => 'medium',
        ]);
    }

    public function test_change_status_rejects_an_unknown_status_and_persists_nothing(): void
    {
        $ticket = $this->ticket();

        try {
            app(TicketService::class)->changeStatus($ticket, 'resolved');
            $this->fail('changeStatus() accepted a status that is not in Ticket::STATUSES.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Unknown ticket status', $e->getMessage());
        }

        // The guard is worth nothing if the row was written before it fired.
        $this->assertSame('open', $ticket->fresh()->status);
    }

    public function test_the_update_form_rejects_an_unknown_status(): void
    {
        $ticket = $this->ticket();

        $this->put($this->tenantUrl("tickets/{$ticket->id}"), [
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'priority' => 'medium',
            'status' => 'resolved',
            'client_id' => $ticket->client_id,
        ])->assertSessionHasErrors('status');

        $this->assertSame('open', $ticket->fresh()->status);
    }

    public function test_the_status_endpoint_rejects_an_unknown_status(): void
    {
        $ticket = $this->ticket();

        $this->post($this->tenantUrl("tickets/{$ticket->id}/status"), ['status' => 'resolved'])
            ->assertSessionHasErrors('status');

        $this->assertSame('open', $ticket->fresh()->status);
    }

    private function apiToken(): string
    {
        $plain = 'tk_'.str()->random(40);

        ApiToken::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'test',
            'token' => hash('sha256', $plain),
        ]);

        return $plain;
    }

    public function test_the_api_rejects_an_unknown_status_filter(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->apiToken())
            ->getJson('/api/v1/tickets?status=resolved')
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_pending_is_an_accepted_api_status_filter(): void
    {
        $ticket = $this->ticket();
        app(TicketService::class)->changeStatus($ticket, 'pending');

        $this->withHeader('Authorization', 'Bearer '.$this->apiToken())
            ->getJson('/api/v1/tickets?status=pending')
            ->assertOk()
            ->assertJsonPath('data.0.ticket_number', $ticket->ticket_number);
    }

    public function test_a_known_status_still_passes_every_door(): void
    {
        // The guards must refuse the unknown without narrowing the known set —
        // 'pending' is the newest member and the one most likely to be missed.
        $ticket = $this->ticket();

        $this->post($this->tenantUrl("tickets/{$ticket->id}/status"), ['status' => 'pending'])
            ->assertSessionHasNoErrors();

        $this->assertSame('pending', $ticket->fresh()->status);
    }
}
