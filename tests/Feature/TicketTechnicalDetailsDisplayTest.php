<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\AiTicketAnalysis;
use App\Models\AppSetting;
use App\Models\Client;
use App\Models\Department;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\PlanService;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P8: what the ticket page shows.
 *
 * The declared fields are ordinary ticket columns and render in the ALWAYS-VISIBLE Ticket
 * Details card — so the Business-tenant test below is the one that catches the likely
 * mistake of putting the customer's own reported facts behind the AI panel's gate.
 */
class TicketTechnicalDetailsDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOnPlan(string $planSlug): Tenant
    {
        $plan = Plan::query()->where('slug', $planSlug)->first()
            ?? Plan::factory()->create(['slug' => $planSlug, 'features' => PlanFeature::forPlan($planSlug)]);
        $license = License::factory()->active()->forPlan($plan)->create();
        $tenant = Tenant::factory()->create(['license_id' => $license->id]);

        SlaPolicy::factory()->create([
            'tenant_id' => $tenant->id,
            'priority' => null,
            'client_tier' => null,
            'is_active' => true,
        ]);

        app(PlanService::class)->clearCache($tenant);

        return $tenant;
    }

    private function actor(Tenant $tenant): User
    {
        $user = User::factory()->create();
        $tenant->addUser($user, 'member');

        $roles = app(TenantRoleService::class);
        $roles->setupDefaultRoles($tenant);
        $roles->syncRole($user, 'admin', $tenant);

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);
        session(['current_tenant_id' => $tenant->id]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function ticketFor(Tenant $tenant, array $overrides = []): Ticket
    {
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        return Ticket::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'priority' => 'medium',
            'status' => 'open',
        ], $overrides));
    }

    public function test_the_ticket_page_shows_the_captured_details_outside_the_ai_panel(): void
    {
        // BUSINESS, with no AI feature at all: the AI panel's include site is gated on two
        // plan features, two workspace switches and a permission, so a field rendered inside
        // it would be invisible to this entire workspace.
        $tenant = $this->tenantOnPlan('business');
        $this->actor($tenant);
        $ticket = $this->ticketFor($tenant, [
            'impact_scope' => 'one_person',
            'workaround' => 'yes',
            'environment' => 'staging',
            'error_text' => 'NullReferenceException at Reports.cs:42',
        ]);

        $response = $this->get($this->tenantUrl("/tickets/{$ticket->id}"));

        $response->assertOk()
            ->assertSee('Who is affected')
            ->assertSee('Is there a workaround')
            ->assertSee('Environment')
            // The human label from the declaration, never the raw key.
            ->assertSee('One person')
            ->assertSee('Yes, a workaround exists')
            ->assertSee('Staging / UAT')
            ->assertSee('NullReferenceException at Reports.cs:42');

        $this->assertStringNotContainsString('one_person', $response->getContent());
    }

    public function test_the_information_needed_list_names_the_absent_fields_and_why(): void
    {
        $tenant = $this->tenantOnPlan('business');
        $this->actor($tenant);
        $ticket = $this->ticketFor($tenant);

        $this->get($this->tenantUrl("/tickets/{$ticket->id}"))
            ->assertOk()
            ->assertSee('Information still needed')
            ->assertSee('Who is affected')
            ->assertSee('Critical needs the scope: the rubric separates critical from high on breadth of impact.')
            ->assertSee('Critical needs this: a severe fault that HAS a workaround is high, not critical.')
            ->assertSee('An environment nobody names is treated as NOT production, which rules critical out.')
            ->assertSee('Add these details');
    }

    public function test_a_fully_captured_ticket_shows_the_satisfied_line_rather_than_an_empty_block(): void
    {
        $tenant = $this->tenantOnPlan('business');
        $this->actor($tenant);
        $ticket = $this->ticketFor($tenant, [
            'impact_scope' => 'everyone',
            'workaround' => 'none',
            'environment' => 'production',
            'steps_to_reproduce' => 'Open the app.',
            'error_text' => 'HTTP 500',
        ]);

        // A block that disappears when satisfied is indistinguishable from one that broke.
        $this->get($this->tenantUrl("/tickets/{$ticket->id}"))
            ->assertOk()
            ->assertSee('All the technical details we use to judge priority are on file.')
            ->assertDontSee('Information still needed');
    }

    public function test_the_analysis_panel_shows_the_priority_reason(): void
    {
        $tenant = $this->tenantOnPlan('enterprise');
        $this->actor($tenant);
        AppSetting::set('ai_enabled', true, 'boolean', 'ai');
        AppSetting::set('ai_agents_enabled', true, 'boolean', 'ai');
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $ticket = $this->ticketFor($tenant, ['department_id' => $department->id]);

        AiTicketAnalysis::create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'trigger' => AiTicketAnalysis::TRIGGER_MANUAL,
            'status' => AiTicketAnalysis::STATUS_SUCCEEDED,
            'model' => 'gpt-4o-mini',
            'suggestions' => [
                'summary' => 'A site-wide reporting outage.',
                'priority' => 'critical',
                'priority_reason' => 'A whole department is affected and no workaround exists.',
            ],
        ]);

        $response = $this->get($this->tenantUrl("/tickets/{$ticket->id}"));

        $response->assertOk()
            ->assertSee('A whole department is affected and no workaround exists.', false);

        // Display only: it explains a suggestion, it is not one, so no Apply/Dismiss control
        // is bound to it and it is not an AiSuggestionVerdict type.
        $this->assertStringNotContainsString("applySuggested('priority_reason')", $response->getContent());
        $this->assertStringNotContainsString("dismiss('priority_reason')", $response->getContent());
    }

    public function test_the_analysis_panel_names_the_agent_that_judged_it_and_leaks_no_prompt(): void
    {
        $tenant = $this->tenantOnPlan('enterprise');
        $this->actor($tenant);
        AppSetting::set('ai_enabled', true, 'boolean', 'ai');
        AppSetting::set('ai_agents_enabled', true, 'boolean', 'ai');
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $ticket = $this->ticketFor($tenant, ['department_id' => $department->id]);

        AiTicketAnalysis::create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'department_id' => $department->id,
            'trigger' => AiTicketAnalysis::TRIGGER_MANUAL,
            'status' => AiTicketAnalysis::STATUS_SUCCEEDED,
            'model' => 'gpt-4o-mini',
            'suggestions' => ['summary' => 'A site-wide reporting outage.'],
            'agent_recommendations' => [[
                'agent_id' => 1,
                'name' => 'Software Triage Agent',
                'role_type' => 'triage',
                'score' => 0.87,
                'matched_skills' => ['NEVER-RENDER-THIS-SKILL'],
            ]],
        ]);

        $response = $this->get($this->tenantUrl("/tickets/{$ticket->id}"));

        $response->assertOk()
            ->assertSee('Judged by:')
            ->assertSee('Software Triage Agent');

        // Names only. AgentRecommendation::toArray() deliberately omits `system_prompt`, so
        // there is nothing to leak; `matched_skills` and `score` ARE in the payload and must
        // stay out of the rendered line.
        $content = $response->getContent();
        $this->assertStringNotContainsString('system_prompt', $content);
        // `matched_skills` and `score` are in the payload the panel hydrates (they were
        // before P8 too) but the rendered line maps over names and nothing else.
        $this->assertStringContainsString('result.agents.map((a) => a.name)', $content);
    }
}
