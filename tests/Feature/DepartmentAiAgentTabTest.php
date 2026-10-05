<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\ActivityLog;
use App\Models\AiAgent;
use App\Models\AppSetting;
use App\Models\Department;
use App\Models\DepartmentAiAgent;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PlanService;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * P4: the AI Agents tab on the Department edit page (spec §8).
 *
 * The tests that matter most are the two the shape of this feature invites getting wrong.
 * `department_ai_agent` is a Pivot with no BelongsToTenant, so a route keyed on the pivot's
 * own id would be a cross-tenant IDOR; and `Department::assignAiAgent()` is an
 * updateOrCreate, so an update route that does not first prove the assignment exists
 * silently creates one.
 */
class DepartmentAiAgentTabTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Configuration only. Nothing in this package may reach an AI provider.
        Http::fake();
    }

    private function createEnterpriseTenant(string $planSlug = 'enterprise'): Tenant
    {
        // first()-or-create, not firstOrCreate() with a factory's getAttributes(): that
        // double-encodes the JSON `features` cast and the plan ends up with no features,
        // which surfaces as a 403 from the feature middleware for the wrong reason.
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

        // Tenant ids repeat across tests in one process, and PlanService caches by id.
        app(PlanService::class)->clearCache($tenant);

        return $tenant;
    }

    private function actAs(Tenant $tenant, string $role = 'admin', bool $enabled = true): User
    {
        $user = User::factory()->create();
        $tenant->addUser($user, 'member');

        $roleService = app(TenantRoleService::class);
        $roleService->setupDefaultRoles($tenant);
        $roleService->syncRole($user, $role, $tenant);

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);

        // AppSetting::set() needs the tenant in the live session, not just the request's.
        session(['current_tenant_id' => $tenant->id]);
        AppSetting::set('ai_agents_enabled', $enabled, 'boolean', 'ai');

        return $user;
    }

    private function department(Tenant $tenant, string $name = 'Technical Software'): Department
    {
        return Department::factory()->create(['tenant_id' => $tenant->id, 'name' => $name, 'code' => 'SOFTWARE']);
    }

    private function agent(Tenant $tenant, string $name, string $roleType = 'specialist'): AiAgent
    {
        return AiAgent::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'slug' => str()->slug($name),
            'role_type' => $roleType,
        ]);
    }

    private function url(Department $department, string $suffix = ''): string
    {
        return $this->tenantUrl("/departments/{$department->id}/ai-agents{$suffix}");
    }

    // ------------------------------------------------------------------ reading the tab

    public function test_the_department_edit_page_offers_the_ai_agents_tab(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->actAs($tenant);
        $department = $this->department($tenant);

        $this->get($this->tenantUrl("/departments/{$department->id}/edit"))->assertOk()
            ->assertSee('AI Agents')
            ->assertSee($this->url($department), false);
    }

    /**
     * §8's worked example, in its own order: Software Triage primary/1/auto-invoke,
     * Debugger specialist/20, QA Engineer validator/90.
     */
    public function test_the_tab_lists_the_assignments_in_ranked_order_with_their_configuration(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->actAs($tenant);
        $department = $this->department($tenant);

        $qa = $this->agent($tenant, 'QA Engineer Agent', 'validator');
        $debugger = $this->agent($tenant, 'Debugger Agent');
        $triage = $this->agent($tenant, 'Software Triage Agent', 'triage');

        // Assigned out of order on purpose: the list must come back by priority, not by id.
        $department->assignAiAgent($qa, 'validator', 90);
        $department->assignAiAgent($debugger, 'specialist', 20);
        $department->assignAiAgent($triage, 'primary', 1, true, true);

        $response = $this->get($this->url($department))->assertOk()
            ->assertSee('Software Triage Agent')
            ->assertSee('Debugger Agent')
            ->assertSee('QA Engineer Agent')
            ->assertSee('3 agents');

        $body = $response->getContent();
        $this->assertTrue(
            strpos($body, 'Software Triage Agent') < strpos($body, 'Debugger Agent')
            && strpos($body, 'Debugger Agent') < strpos($body, 'QA Engineer Agent'),
            'Assignments must render by ascending priority: 1, 20, 90.',
        );

        // Auto invoke is the only flag that differs between the three, so it is the one
        // worth pinning: the triage row is checked and the other two are not.
        $this->assertSame(1, substr_count($body, 'name="auto_invoke" id="auto_invoke-'.$triage->id.'" value="1" checked'));
        $this->assertStringNotContainsString('id="auto_invoke-'.$debugger->id.'" value="1" checked', $body);
    }

    public function test_an_unassigned_department_shows_a_real_empty_state(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->actAs($tenant);

        $this->get($this->url($this->department($tenant)))->assertOk()
            ->assertSee('No AI agents are assigned to this department yet');
    }

    public function test_the_tab_links_through_to_the_surviving_agent_and_skill_screens(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->actAs($tenant);
        $this->agent($tenant, 'Debugger Agent');

        // The sidebar entry is gone, so these links are the only route to the definition
        // screens and to the opt-in switch.
        $this->get($this->url($this->department($tenant)))->assertOk()
            ->assertSee($this->tenantUrl('/ai-agents').'"', false)
            ->assertSee($this->tenantUrl('/skills').'"', false)
            ->assertSee($this->tenantUrl('/ai-agents/setup'), false);
    }

    // ------------------------------------------------------------------ writing

    public function test_an_agent_can_be_assigned_with_a_role_priority_and_both_flags(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->actAs($tenant);
        $department = $this->department($tenant);
        $agent = $this->agent($tenant, 'Software Triage Agent', 'triage');

        $this->post($this->url($department), [
            'ai_agent_id' => $agent->id,
            'role' => 'primary',
            'priority' => 1,
            'is_active' => '1',
            'auto_invoke' => '1',
        ])->assertRedirect($this->url($department))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('department_ai_agent', [
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
            'ai_agent_id' => $agent->id,
            'role' => 'primary',
            'priority' => 1,
            'is_active' => true,
            'auto_invoke' => true,
        ]);
    }

    public function test_assigning_the_same_agent_twice_is_rejected_rather_than_overwriting_the_assignment(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->actAs($tenant);
        $department = $this->department($tenant);
        $agent = $this->agent($tenant, 'Debugger Agent');

        $department->assignAiAgent($agent, 'specialist', 20);

        // assignAiAgent() is an updateOrCreate, so without the validation rule this would
        // silently reset the role and priority instead of saying no.
        $this->post($this->url($department), ['ai_agent_id' => $agent->id, 'role' => 'fallback', 'priority' => 5])
            ->assertSessionHasErrors('ai_agent_id');

        $this->assertSame(1, DB::table('department_ai_agent')->where('ai_agent_id', $agent->id)->count());
        $this->assertDatabaseHas('department_ai_agent', [
            'ai_agent_id' => $agent->id,
            'role' => 'specialist',
            'priority' => 20,
        ]);
    }

    public function test_an_assignment_can_be_edited_without_touching_the_same_agents_other_department(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->actAs($tenant);
        $software = $this->department($tenant);
        $hardware = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Technical Hardware', 'code' => 'HARDWARE']);
        $agent = $this->agent($tenant, 'Documentation Agent', 'documentation');

        $software->assignAiAgent($agent, 'support', 50);
        $hardware->assignAiAgent($agent, 'validator', 90, true, true);

        $this->put($this->url($software, "/{$agent->id}"), [
            'role' => 'primary',
            'priority' => 1,
            'is_active' => '1',
            'auto_invoke' => '1',
        ])->assertRedirect($this->url($software))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('department_ai_agent', [
            'department_id' => $software->id, 'ai_agent_id' => $agent->id,
            'role' => 'primary', 'priority' => 1, 'auto_invoke' => true,
        ]);
        $this->assertDatabaseHas('department_ai_agent', [
            'department_id' => $hardware->id, 'ai_agent_id' => $agent->id,
            'role' => 'validator', 'priority' => 90,
        ]);
    }

    public function test_unchecked_flags_are_saved_as_false_rather_than_left_alone(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->actAs($tenant);
        $department = $this->department($tenant);
        $agent = $this->agent($tenant, 'Security Agent');

        $department->assignAiAgent($agent, 'primary', 1, true, true);

        // A browser sends nothing for an unchecked box; the hidden 0 companions are what
        // make "switch auto invoke off" expressible at all.
        $this->put($this->url($department, "/{$agent->id}"), ['role' => 'primary', 'priority' => 1, 'is_active' => '0', 'auto_invoke' => '0'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('department_ai_agent', [
            'department_id' => $department->id, 'ai_agent_id' => $agent->id,
            'is_active' => false, 'auto_invoke' => false,
        ]);
    }

    public function test_an_unknown_department_role_is_rejected(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->actAs($tenant);
        $department = $this->department($tenant);
        $agent = $this->agent($tenant, 'DevOps Agent');

        $this->post($this->url($department), ['ai_agent_id' => $agent->id, 'role' => 'overlord'])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseCount('department_ai_agent', 0);
    }

    public function test_updating_an_agent_that_is_not_assigned_404s_instead_of_creating_the_assignment(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->actAs($tenant);
        $department = $this->department($tenant);
        $agent = $this->agent($tenant, 'Database Engineer Agent');

        // The updateOrCreate hazard: an update that skips the "is it assigned" check would
        // quietly create a brand new assignment from an edit form nobody opened.
        $this->put($this->url($department, "/{$agent->id}"), ['role' => 'primary', 'priority' => 1])
            ->assertNotFound();

        $this->assertDatabaseCount('department_ai_agent', 0);
    }

    public function test_removing_an_assignment_leaves_the_agent_intact_and_writes_a_readable_audit_row(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->actAs($tenant);
        $department = $this->department($tenant);
        $agent = $this->agent($tenant, 'Database Engineer Agent');

        $department->assignAiAgent($agent, 'specialist', 30);

        $this->delete($this->url($department, "/{$agent->id}"))
            ->assertRedirect($this->url($department))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('department_ai_agent', 0);
        $this->assertDatabaseHas('ai_agents', ['id' => $agent->id]);

        // removeAiAgent() goes through the pivot model, which is the only reason the
        // deleted event fires and the label resolves; a raw detach() writes nothing.
        $this->assertTrue(
            ActivityLog::query()->where('subject_type', DepartmentAiAgent::class)
                ->where('action', 'deleted')
                ->where('subject_label', 'Technical Software → Database Engineer Agent')
                ->exists(),
            'The removal must be audited with the readable department → agent label.',
        );
    }

    public function test_assigning_and_editing_are_audited(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->actAs($tenant);
        $department = $this->department($tenant);
        $agent = $this->agent($tenant, 'Debugger Agent');

        $this->post($this->url($department), ['ai_agent_id' => $agent->id, 'role' => 'specialist', 'priority' => 20]);
        $this->put($this->url($department, "/{$agent->id}"), ['role' => 'primary', 'priority' => 1]);

        $actions = ActivityLog::query()
            ->where('subject_type', DepartmentAiAgent::class)
            ->pluck('action')->all();

        $this->assertContains('created', $actions);
        $this->assertContains('updated', $actions);
    }

    // ------------------------------------------------------------------ tenant isolation

    public function test_another_workspaces_agent_cannot_be_assigned_and_fails_validation(): void
    {
        $tenantA = $this->createEnterpriseTenant();
        $tenantB = $this->createEnterpriseTenant();
        $foreignAgent = $this->agent($tenantB, 'Foreign Agent');

        $this->actAs($tenantA);
        $department = $this->department($tenantA);

        // tenantExists(), not exists:ai_agents,id. The plain rule queries the raw table and
        // would accept this id; assignAiAgent() would then throw a 500 where the
        // administrator should see a validation message.
        $this->post($this->url($department), ['ai_agent_id' => $foreignAgent->id, 'role' => 'primary', 'priority' => 1])
            ->assertSessionHasErrors('ai_agent_id');

        $this->assertDatabaseCount('department_ai_agent', 0);
    }

    public function test_another_workspaces_agent_and_department_are_not_addressable_by_route(): void
    {
        $tenantA = $this->createEnterpriseTenant();
        $tenantB = $this->createEnterpriseTenant();
        $foreignDepartment = $this->department($tenantB, 'Foreign Department');
        $foreignAgent = $this->agent($tenantB, 'Foreign Agent');
        $foreignDepartment->assignAiAgent($foreignAgent, 'primary', 1);

        $this->actAs($tenantA);
        $ownDepartment = $this->department($tenantA);

        // Both bound models carry BelongsToTenant, which is what makes this a 404 in the
        // router rather than a cross-tenant write. No route in this feature resolves a
        // department_ai_agent row by its own id, which has no such scope.
        $this->get($this->url($foreignDepartment))->assertNotFound();
        $this->put($this->url($foreignDepartment, "/{$foreignAgent->id}"), ['role' => 'support'])->assertNotFound();
        $this->delete($this->url($foreignDepartment, "/{$foreignAgent->id}"))->assertNotFound();
        $this->delete($this->url($ownDepartment, "/{$foreignAgent->id}"))->assertNotFound();

        $this->assertDatabaseHas('department_ai_agent', [
            'department_id' => $foreignDepartment->id,
            'ai_agent_id' => $foreignAgent->id,
        ]);
    }

    public function test_the_picker_offers_only_this_workspaces_unassigned_agents(): void
    {
        $tenantA = $this->createEnterpriseTenant();
        $tenantB = $this->createEnterpriseTenant();
        $this->agent($tenantB, 'Foreign Agent');

        $this->actAs($tenantA);
        $department = $this->department($tenantA);
        $assigned = $this->agent($tenantA, 'Already Assigned Agent');
        $this->agent($tenantA, 'Assignable Agent');
        $department->assignAiAgent($assigned, 'support', 10);

        $this->get($this->url($department))->assertOk()
            ->assertSee('Assignable Agent')
            ->assertDontSee('Foreign Agent')
            // The assigned agent is in the list above but must not be in the picker, or
            // choosing it only produces a duplicate-assignment error.
            ->assertDontSee('<option value="'.$assigned->id.'">Already Assigned Agent</option>', false);
    }

    public function test_the_pivot_row_carries_the_tenant_id(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->actAs($tenant);
        $department = $this->department($tenant);
        $agent = $this->agent($tenant, 'DevOps Agent');

        $this->post($this->url($department), ['ai_agent_id' => $agent->id, 'role' => 'support']);

        // By query builder, which is the whole reason the column exists on an unscoped pivot.
        $this->assertSame(
            $tenant->id,
            (int) DB::table('department_ai_agent')->where('ai_agent_id', $agent->id)->value('tenant_id'),
        );
    }

    // ------------------------------------------------------------------ gating

    public function test_a_workspace_without_the_ai_agents_feature_gets_403_and_no_tab(): void
    {
        // Not a Business plan: department_management is itself Enterprise, so a Business
        // workspace cannot reach the department edit page at all and the tab's own gate
        // would never be the thing under test. An Enterprise plan with ai_agents removed
        // isolates it.
        $tenant = $this->createEnterpriseTenant();
        $plan = $tenant->license->plan;
        $plan->update(['features' => array_values(array_diff($plan->features, [PlanFeature::AiAgents->value]))]);
        $this->actAs($tenant);
        app(PlanService::class)->clearCache($tenant);
        $department = $this->department($tenant);

        $this->get($this->url($department))->assertForbidden();
        $this->post($this->url($department), ['ai_agent_id' => 1, 'role' => 'support'])->assertForbidden();

        $this->get($this->tenantUrl("/departments/{$department->id}/edit"))->assertOk()
            ->assertDontSee($this->url($department), false);
    }

    public function test_a_role_without_the_permission_reads_the_tab_but_cannot_change_it(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->actAs($tenant, 'manager');
        $department = $this->department($tenant);
        $agent = $this->agent($tenant, 'Security Agent');
        $department->assignAiAgent($agent, 'support', 10);

        // manager holds update departments but not manage ai agents (it arrives only
        // through FEATURE_PERMISSIONS for admin), so the tab is read-only.
        $this->assertFalse($this->app['auth']->user()->can('manage ai agents'));

        $this->get($this->url($department))->assertOk()->assertDontSee('Assign an agent');
        // A second, unassigned agent: the payload has to be valid for the permission check
        // to be what refuses it. FormRequest validation runs before a controller's
        // checkPermission() throughout this repo, so a duplicate id would be answered with
        // a validation error instead — pre-existing ordering, recorded, not changed here.
        $other = $this->agent($tenant, 'Documentation Agent', 'documentation');
        $this->post($this->url($department), ['ai_agent_id' => $other->id, 'role' => 'support'])->assertForbidden();
        $this->put($this->url($department, "/{$agent->id}"), ['role' => 'primary'])->assertForbidden();
        $this->delete($this->url($department, "/{$agent->id}"))->assertForbidden();
    }

    public function test_a_role_without_update_departments_cannot_read_the_tab(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->actAs($tenant, 'viewer');

        $this->get($this->url($this->department($tenant)))->assertForbidden();
    }

    public function test_with_the_opt_in_off_the_tab_is_readable_and_every_write_is_refused(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->actAs($tenant, 'admin', enabled: false);
        $department = $this->department($tenant);
        $agent = $this->agent($tenant, 'Security Agent');
        $department->assignAiAgent($agent, 'support', 10);

        $this->get($this->url($department))->assertOk()
            ->assertSee('AI agents are switched off for this workspace.')
            // The notice carries the only remaining route to the switch.
            ->assertSee($this->tenantUrl('/ai-agents/setup'), false)
            ->assertDontSee('Assign an agent');

        $other = $this->agent($tenant, 'Documentation Agent', 'documentation');
        $this->post($this->url($department), ['ai_agent_id' => $other->id, 'role' => 'support'])->assertForbidden();
        $this->put($this->url($department, "/{$agent->id}"), ['role' => 'primary'])->assertForbidden();
        $this->delete($this->url($department, "/{$agent->id}"))->assertForbidden();
    }

    public function test_deleting_a_department_removes_its_assignments_and_leaves_the_agents(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->actAs($tenant);
        $department = $this->department($tenant);
        $agent = $this->agent($tenant, 'Debugger Agent');
        $department->assignAiAgent($agent, 'specialist', 20);

        $this->delete($this->tenantUrl("/departments/{$department->id}"))->assertRedirect();

        $this->assertDatabaseCount('department_ai_agent', 0);
        $this->assertDatabaseHas('ai_agents', ['id' => $agent->id]);
    }

    public function test_no_outbound_http_request_is_made_by_the_tab(): void
    {
        $tenant = $this->createEnterpriseTenant();
        $this->actAs($tenant);
        $department = $this->department($tenant);
        $agent = $this->agent($tenant, 'Debugger Agent');

        $this->get($this->url($department));
        $this->post($this->url($department), ['ai_agent_id' => $agent->id, 'role' => 'specialist', 'priority' => 20]);
        $this->put($this->url($department, "/{$agent->id}"), ['role' => 'primary', 'priority' => 1]);
        $this->delete($this->url($department, "/{$agent->id}"));

        // The line §4 draws: configuration, not execution.
        Http::assertNothingSent();
    }
}
