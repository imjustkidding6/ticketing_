<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientCommentTest extends TestCase
{
    use RefreshDatabase;

    private function createEnterpriseTenant(): Tenant
    {
        $plan = Plan::factory()->create([
            'slug' => 'enterprise',
            'features' => PlanFeature::forPlan('enterprise'),
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

    public function test_comment_returns_403_for_starter_plan(): void
    {
        $tenant = $this->createStarterTenant();
        $user = $this->setupTenantContext($tenant);

        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/comments"), [
            'content' => 'Test comment',
            'type' => 'public',
        ])->assertForbidden();
    }

    public function test_can_add_public_comment(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $user = $this->setupTenantContext($tenant);

        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/comments"), [
            'content' => 'This is a public comment',
            'type' => 'public',
        ])->assertRedirect();

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'content' => 'This is a public comment',
            'type' => 'public',
            'is_public' => true,
        ]);
    }

    public function test_can_add_internal_comment(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $user = $this->setupTenantContext($tenant);

        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/comments"), [
            'content' => 'Internal note',
            'type' => 'internal',
        ])->assertRedirect();

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'content' => 'Internal note',
            'type' => 'internal',
            'is_public' => false,
        ]);
    }

    public function test_comment_validation_requires_content(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $user = $this->setupTenantContext($tenant);

        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

        $this->post($this->tenantUrl("/tickets/{$ticket->id}/comments"), [
            'content' => '',
            'type' => 'public',
        ])->assertSessionHasErrors('content');
    }

    public function test_can_delete_own_comment(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $user = $this->setupTenantContext($tenant);

        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);
        $comment = TicketComment::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'content' => 'To be deleted',
        ]);

        $this->delete($this->tenantUrl("/tickets/{$ticket->id}/comments/{$comment->id}"))
            ->assertRedirect();

        $this->assertDatabaseMissing('ticket_comments', ['id' => $comment->id]);
    }

    public function test_can_update_own_comment(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $user = $this->setupTenantContext($tenant);

        $ticket = Ticket::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);
        $comment = TicketComment::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'content' => 'Original content',
        ]);

        $this->put($this->tenantUrl("/tickets/{$ticket->id}/comments/{$comment->id}"), [
            'content' => 'Updated content',
        ])->assertRedirect();

        $comment->refresh();
        $this->assertEquals('Updated content', $comment->content);
        $this->assertNotNull($comment->edited_at);
    }
}
