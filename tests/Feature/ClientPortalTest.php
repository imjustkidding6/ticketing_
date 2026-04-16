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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClientPortalTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(string $planSlug): Tenant
    {
        $plan = Plan::factory()->create(['slug' => $planSlug.'_'.Str::random(4), 'features' => PlanFeature::forPlan($planSlug)]);
        $license = License::factory()->active()->forPlan($plan)->create();

        return Tenant::factory()->create(['license_id' => $license->id]);
    }

    public function test_landing_page_starter_gets_404(): void
    {
        $tenant = $this->createTenant('start');

        $this->get("/{$tenant->slug}/")->assertNotFound();
    }

    public function test_landing_page_business_accessible(): void
    {
        $tenant = $this->createTenant('business');

        $this->get("/{$tenant->slug}/")->assertOk();
    }

    public function test_submit_ticket_starter_gets_404(): void
    {
        $tenant = $this->createTenant('start');

        $this->get("/{$tenant->slug}/submit-ticket")->assertNotFound();
    }

    public function test_submit_ticket_form_loads(): void
    {
        $tenant = $this->createTenant('business');

        $this->get("/{$tenant->slug}/submit-ticket")->assertOk();
    }

    public function test_submit_ticket_stores_ticket_and_client(): void
    {
        $tenant = $this->createTenant('business');
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
        $cat = TicketCategory::factory()->create(['tenant_id' => $tenant->id, 'department_id' => $dept->id]);

        $this->post("/{$tenant->slug}/submit-ticket", [
            'name' => 'Portal User',
            'email' => 'portal@example.com',
            'subject' => 'Portal Test Ticket',
            'description' => 'Testing portal submission.',
            'priority' => 'medium',
            'department_id' => $dept->id,
            'category_id' => $cat->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('clients', ['email' => 'portal@example.com', 'tenant_id' => $tenant->id]);
        $this->assertDatabaseHas('tickets', ['subject' => 'Portal Test Ticket', 'tenant_id' => $tenant->id]);
    }

    public function test_track_ticket_not_found(): void
    {
        $tenant = $this->createTenant('business');

        $this->get("/{$tenant->slug}/track-ticket?ticket_number=FAKE&email=nobody@test.com")
            ->assertOk()
            ->assertSee('No ticket found');
    }

    public function test_track_ticket_found_redirects(): void
    {
        $tenant = $this->createTenant('business');
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'email' => 'track@test.com']);
        $ticket = Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'created_by' => null,
            'tracking_token' => 'test-token-abc',
        ]);

        $this->get("/{$tenant->slug}/track-ticket?ticket_number={$ticket->ticket_number}&email=track@test.com")
            ->assertRedirect("/{$tenant->slug}/track-ticket/test-token-abc");
    }

    public function test_track_by_token(): void
    {
        $tenant = $this->createTenant('business');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'created_by' => null,
            'tracking_token' => 'valid-token-xyz',
        ]);

        $this->get("/{$tenant->slug}/track-ticket/valid-token-xyz")->assertOk();
    }

    public function test_track_invalid_token_404(): void
    {
        $tenant = $this->createTenant('business');

        $this->get("/{$tenant->slug}/track-ticket/invalid-token")->assertNotFound();
    }

    public function test_client_reply_enterprise_only(): void
    {
        $tenant = $this->createTenant('enterprise');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $ticket = Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'created_by' => null,
            'tracking_token' => 'reply-token-123',
        ]);

        $this->post("/{$tenant->slug}/track-ticket/reply-token-123/reply", [
            'content' => 'Client reply test',
        ])->assertRedirect();

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'content' => 'Client reply test',
            'is_public' => true,
            'user_id' => null,
        ]);
    }

    public function test_public_api_categories(): void
    {
        $tenant = $this->createTenant('business');
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
        TicketCategory::factory()->create(['tenant_id' => $tenant->id, 'department_id' => $dept->id]);

        $this->get("/{$tenant->slug}/api/public/categories?department_id={$dept->id}")
            ->assertOk()
            ->assertJsonCount(1);
    }
}
