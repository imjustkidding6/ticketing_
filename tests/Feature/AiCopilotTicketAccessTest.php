<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\AppSetting;
use App\Models\Client;
use App\Models\Department;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The four ticket-bound agent-copilot endpoints must honour department scoping, not
 * only the ticket pages.
 *
 * Before this, `AiAssistantController` never imported `ScopesTicketsToDepartments`: a
 * department-restricted agent who is refused `/tickets/{id}` with a 403 could still POST
 * to that ticket's draft-reply, summarize and task-polish endpoints and read the ticket's
 * contents back out of the JSON response. Same tenant, so `TenantScope` never saw it —
 * which is precisely why it survived. Same family as follow-up 10 on TicketTaskController.
 *
 * REACHING THE GATE IS THE WHOLE DIFFICULTY. `checkPermission('use ai assistant')` runs
 * first and a user lacking it 403s for the wrong reason, which would make these tests pass
 * against an ungated controller. So every test here grants `use ai assistant`, ASSERTS the
 * user holds it, and only then asserts the 403 — and `Http::preventStrayRequests()` proves
 * no OpenAI call was made, since a leak that 403s after spending money is still a leak.
 */
class AiCopilotTicketAccessTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $plan = Plan::factory()->create([
            'slug' => 'enterprise',
            'features' => PlanFeature::forPlan('enterprise'),
        ]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $this->tenant = Tenant::factory()->create(['license_id' => $license->id]);
        SlaPolicy::factory()->create([
            'tenant_id' => $this->tenant->id,
            'priority' => null,
            'client_tier' => null,
            'is_active' => true,
        ]);
        app(TenantRoleService::class)->setupDefaultRoles($this->tenant);

        // The ticket sits in a department the restricted agent is NOT a member of.
        $secret = Department::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Technical Software',
        ]);
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->ticket = Ticket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $secret->id,
            'client_id' => $client->id,
            'subject' => 'SENTINEL do not disclose this subject',
            'assigned_to' => null,
            'created_by' => null,
        ]);
    }

    /**
     * A member of this workspace, holding `use ai assistant`, restricted to a DIFFERENT
     * department, and neither assigned to nor the creator of the ticket.
     */
    private function restrictedAgent(): User
    {
        $user = User::factory()->create();
        $this->tenant->addUser($user, 'member');

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
        $user->givePermissionTo('use ai assistant');

        $other = Department::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Human Resource',
        ]);
        $user->departments()->attach($other->id);

        // The gate is only MEANINGFUL if the permission check in front of it passes.
        $this->assertTrue(
            $user->can('use ai assistant'),
            'fixture broken: the user must hold use ai assistant, or the 403 proves nothing',
        );
        $this->assertFalse(
            $user->isOwnerOf($this->tenant),
            'fixture broken: an owner bypasses every permission check',
        );

        $this->actingAs($user)
            ->withTenant($this->tenant)
            ->withSession(['current_tenant_id' => $this->tenant->id]);
        session(['current_tenant_id' => $this->tenant->id]);

        // AppSetting::set() resolves the tenant from the session, so it only lands on the
        // right workspace once the context above is established.
        AppSetting::set('ai_enabled', true, 'boolean', 'ai');
        AppSetting::set('ai_agent_copilot_enabled', true, 'boolean', 'ai');

        return $user;
    }

    public function test_draft_reply_refuses_a_ticket_outside_the_agents_departments(): void
    {
        $this->restrictedAgent();

        $this->postJson($this->tenantUrl("tickets/{$this->ticket->id}/ai/draft-reply"))
            ->assertStatus(403)
            ->assertDontSee('SENTINEL do not disclose this subject');
    }

    public function test_summarize_refuses_a_ticket_outside_the_agents_departments(): void
    {
        $this->restrictedAgent();

        $this->postJson($this->tenantUrl("tickets/{$this->ticket->id}/ai/summarize"))
            ->assertStatus(403)
            ->assertDontSee('SENTINEL do not disclose this subject');
    }

    public function test_polish_task_refuses_a_ticket_outside_the_agents_departments(): void
    {
        $this->restrictedAgent();

        $this->postJson(
            $this->tenantUrl("tickets/{$this->ticket->id}/ai/polish-task"),
            ['text' => 'check the thing']
        )->assertStatus(403);
    }

    public function test_polish_task_list_refuses_a_ticket_outside_the_agents_departments(): void
    {
        $this->restrictedAgent();

        $this->postJson($this->tenantUrl("tickets/{$this->ticket->id}/ai/polish-tasks"))
            ->assertStatus(403);
    }

    /**
     * The positive contrast, without which every assertion above could pass for the wrong
     * reason: the SAME user, with the ticket's own department attached, is let through the
     * gate. A 503 here is the stubbed OpenAI failure, i.e. past the gate — what matters is
     * that it is not a 403.
     */
    public function test_the_same_agent_is_admitted_once_the_department_matches(): void
    {
        $user = $this->restrictedAgent();
        $user->departments()->attach($this->ticket->department_id);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'A drafted reply.']]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ]),
        ]);

        $this->postJson($this->tenantUrl("tickets/{$this->ticket->id}/ai/summarize"))
            ->assertStatus(200);
    }
}
