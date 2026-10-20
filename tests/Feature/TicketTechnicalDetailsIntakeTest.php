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
use App\Models\User;
use App\Services\PlanService;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P8: intake of the declared technical details on both forms.
 *
 * The internal form requires the three closed-set selects; the public form asks for all
 * five and requires none of them. The highest-value test in the file is the last one —
 * `_ai-analyze`'s Apply posts the whole update form and carries none of these keys.
 */
class TicketTechnicalDetailsIntakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The public submit path dispatches the intake analysis; nothing here needs it run.
        Queue::fake();
    }

    private function tenantOnPlan(string $planSlug = 'business'): Tenant
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

    public function test_the_internal_create_form_refuses_a_ticket_with_no_impact_scope(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $department = Department::factory()->create(['tenant_id' => $tenant->id, 'is_technical' => true]);

        $this->post($this->tenantUrl('/tickets'), [
            'subject' => 'Reporting down',
            'description' => 'Nobody can open reports',
            'priority' => 'high',
            'client_id' => $client->id,
            'department_id' => $department->id,
        ])->assertSessionHasErrors(['impact_scope', 'workaround', 'environment']);

        $this->assertSame(0, Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_the_internal_create_form_stores_all_five_declared_fields(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->post($this->tenantUrl('/tickets'), [
            'subject' => 'Reporting down',
            'description' => 'Nobody can open reports',
            'priority' => 'high',
            'client_id' => $client->id,
            'impact_scope' => 'department',
            'workaround' => 'none',
            'environment' => 'production',
            'steps_to_reproduce' => 'Open the app, click Reports.',
            'error_text' => 'HTTP 500 from /reports',
        ])->assertSessionHasNoErrors();

        $ticket = Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();

        // Without the five keys in Ticket::$fillable, createTicket() drops them silently
        // and the form appears to work while storing nothing. This is the only thing
        // between the build and that.
        $this->assertSame('department', $ticket->impact_scope);
        $this->assertSame('none', $ticket->workaround);
        $this->assertSame('production', $ticket->environment);
        $this->assertSame('Open the app, click Reports.', $ticket->steps_to_reproduce);
        $this->assertSame('HTTP 500 from /reports', $ticket->error_text);
    }

    public function test_the_public_submit_form_accepts_a_submission_with_none_of_them(): void
    {
        $tenant = $this->tenantOnPlan();
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);

        $this->post("/{$tenant->slug}/submit-ticket", [
            'name' => 'Pat Visitor',
            'email' => 'pat@example.net',
            'subject' => 'Cannot log in',
            'description' => 'The login page rejects my password.',
            'department_id' => $department->id,
        ])->assertSessionHasNoErrors();

        $ticket = Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();

        foreach (['impact_scope', 'workaround', 'environment', 'steps_to_reproduce', 'error_text'] as $key) {
            $this->assertNull($ticket->{$key}, "{$key} is prompted, never required, on the public form");
        }
    }

    public function test_the_public_submit_form_stores_them_when_offered(): void
    {
        $tenant = $this->tenantOnPlan();
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);

        $this->post("/{$tenant->slug}/submit-ticket", [
            'name' => 'Pat Visitor',
            'email' => 'pat@example.net',
            'subject' => 'Cannot log in',
            'description' => 'The login page rejects my password.',
            'department_id' => $department->id,
            'impact_scope' => 'team',
            'workaround' => 'partial',
            'environment' => 'production',
            'steps_to_reproduce' => 'Go to the login page and enter a valid password.',
            'error_text' => 'Invalid credentials',
        ])->assertSessionHasNoErrors();

        $ticket = Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();

        $this->assertSame('team', $ticket->impact_scope);
        $this->assertSame('partial', $ticket->workaround);
        $this->assertSame('production', $ticket->environment);
        $this->assertSame('Invalid credentials', $ticket->error_text);
    }

    public function test_the_public_submit_form_refuses_a_select_value_outside_its_declared_options(): void
    {
        $tenant = $this->tenantOnPlan();
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);

        // An unsigned, unauthenticated surface: every value is attacker-controlled, and the
        // closed set is enforced in PHP rather than trusted from the browser. `prod` is a
        // plausible near-miss; `everybody` is another field's vocabulary.
        $this->post("/{$tenant->slug}/submit-ticket", [
            'name' => 'Pat Visitor',
            'email' => 'pat@example.net',
            'subject' => 'Cannot log in',
            'description' => 'The login page rejects my password.',
            'department_id' => $department->id,
            'impact_scope' => 'everybody',
            'environment' => 'prod',
        ])->assertSessionHasErrors(['impact_scope', 'environment']);

        $this->assertSame(0, Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_a_declared_text_field_longer_than_its_cap_is_refused(): void
    {
        $tenant = $this->tenantOnPlan();
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);

        $this->post("/{$tenant->slug}/submit-ticket", [
            'name' => 'Pat Visitor',
            'email' => 'pat@example.net',
            'subject' => 'Cannot log in',
            'description' => 'The login page rejects my password.',
            'department_id' => $department->id,
            'steps_to_reproduce' => str_repeat('a', 4001),
        ])->assertSessionHasErrors('steps_to_reproduce');

        $this->assertSame(0, Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_the_public_submit_form_adds_no_prefillable_or_lockable_field(): void
    {
        $tenant = $this->tenantOnPlan();
        Department::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->get("/{$tenant->slug}/submit-ticket?environment=production&impact_scope=everyone&workaround=none");

        $response->assertOk();

        // A locked field renders as a HIDDEN input, so a value baked into a QR-sticker link
        // would silently downgrade every ticket filed through it with no visitor able to see
        // it. None of the three may become prefillable or lockable without that being a
        // deliberate decision somewhere other than here.
        foreach (['environment', 'impact_scope', 'workaround'] as $key) {
            $response->assertDontSee('<input type="hidden" name="'.$key.'"', false);
        }

        $response->assertDontSee('filled in from your link');
    }

    public function test_applying_an_ai_suggestion_does_not_wipe_the_technical_details(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $ticket = Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'priority' => 'medium',
            'status' => 'open',
            'impact_scope' => 'department',
            'workaround' => 'none',
            'environment' => 'production',
            'steps_to_reproduce' => 'Open the app, click Reports.',
            'error_text' => 'HTTP 500 from /reports',
        ]);

        // EXACTLY the payload `_ai-analyze`'s hidden snapshot sends: the ticket's whole
        // update form as rendered, which carries none of the technical-detail keys, plus the
        // one field being applied. Every new rule is `nullable`, so an absent key is omitted
        // from validated() and therefore never written — Apply cannot wipe a stored value.
        $this->put($this->tenantUrl("/tickets/{$ticket->id}"), [
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'priority' => 'high',
            'status' => $ticket->status,
            'client_id' => $client->id,
        ])->assertSessionHasNoErrors();

        $ticket->refresh();

        $this->assertSame('high', $ticket->priority);
        $this->assertSame('department', $ticket->impact_scope);
        $this->assertSame('none', $ticket->workaround);
        $this->assertSame('production', $ticket->environment);
        $this->assertSame('Open the app, click Reports.', $ticket->steps_to_reproduce);
        $this->assertSame('HTTP 500 from /reports', $ticket->error_text);
    }

    public function test_a_non_technical_department_is_not_asked_for_an_environment(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        // The default: HR, Sales, Finance. "Provision a laptop for a new starter" has no
        // environment, and requiring one trains the agent to pick a value to clear the form.
        $department = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Human Resources']);

        $this->post($this->tenantUrl('/tickets'), [
            'subject' => 'Payroll portal will not accept my timesheet',
            'description' => 'The submit button does nothing.',
            'priority' => 'medium',
            'client_id' => $client->id,
            'department_id' => $department->id,
            // The universal pair is still required, and still answered: an HR fault hitting
            // everyone is more urgent than one hitting one person.
            'impact_scope' => 'team',
            'workaround' => 'partial',
        ])->assertSessionHasNoErrors();

        $ticket = Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();

        $this->assertSame('team', $ticket->impact_scope);
        $this->assertNull($ticket->environment);
    }

    public function test_the_universal_pair_is_required_even_with_no_department(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->post($this->tenantUrl('/tickets'), [
            'subject' => 'Something is wrong',
            'description' => 'Not sure which team owns this.',
            'priority' => 'medium',
            'client_id' => $client->id,
        ])
            ->assertSessionHasErrors(['impact_scope', 'workaround'])
            // No department is not technical: asking for less beats fabricating a
            // requirement nobody can answer.
            ->assertSessionDoesntHaveErrors('environment');
    }

    public function test_a_stray_environment_on_a_non_technical_ticket_is_still_validated(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $department = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Sales']);

        // Not applicable is not the same as unvalidated: an unvalidated string would reach
        // the column and the prompt.
        $this->post($this->tenantUrl('/tickets'), [
            'subject' => 'Quote request',
            'description' => 'Please send pricing.',
            'priority' => 'low',
            'client_id' => $client->id,
            'department_id' => $department->id,
            'impact_scope' => 'one_person',
            'workaround' => 'yes',
            'environment' => 'prod',
        ])->assertSessionHasErrors('environment');

        $this->assertSame(0, Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    /**
     * FOLLOW-UP 28, discharged directly rather than through a request.
     *
     * `Department::isTechnicalFor()` is the one new query this package adds, and it carries
     * an explicit `tenant_id` predicate because it is reached from a FormRequest AND from
     * the queued intake analysis, where TenantScope applies no predicate at all. An
     * end-to-end test on a session-bearing surface cannot prove that predicate: Department's
     * own global scope would mask its absence. So the assertion is on the resolver, with a
     * hostile raw-inserted decoy — a technical department in the WRONG workspace, carrying
     * the same id the caller is about to ask about.
     */
    public function test_a_foreign_departments_technical_flag_cannot_decide_this_workspaces_fields(): void
    {
        $tenantA = $this->tenantOnPlan();
        $tenantB = $this->tenantOnPlan();

        // Raw-inserted so no scope, factory default or observer can tidy it away.
        $foreignId = DB::table('departments')->insertGetId([
            'tenant_id' => $tenantB->id,
            'name' => 'Technical Software',
            'code' => 'SOFTWARE',
            'color' => '#3b82f6',
            'is_active' => true,
            'is_default' => true,
            'is_technical' => true,
            'sort_order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The decoy IS technical, in its own workspace.
        $this->assertTrue(Department::isTechnicalFor($tenantB->id, $foreignId));

        // Asked for from tenant A it resolves to FALSE — fewer fields demanded, never
        // another workspace's answer. Without the predicate this returns true.
        $this->assertFalse(Department::isTechnicalFor($tenantA->id, $foreignId));

        // And an id that exists nowhere, and no id at all.
        $this->assertFalse(Department::isTechnicalFor($tenantA->id, 999999));
        $this->assertFalse(Department::isTechnicalFor($tenantA->id, null));
    }

    public function test_a_department_that_switched_the_impact_questions_off_does_not_require_them(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $leave = Department::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'People',
            'asks_impact' => false,
        ]);
        $support = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Support']);

        // P9: the gate is the DEPARTMENT ROW, so a leave request clears the form with no
        // scope and no workaround — which is the trade the department form spells out.
        $this->post($this->tenantUrl('/tickets'), [
            'subject' => 'Annual leave in March',
            'description' => 'Requesting the last week of March off.',
            'priority' => 'low',
            'client_id' => $client->id,
            'department_id' => $leave->id,
        ])->assertSessionHasNoErrors();

        $this->assertNull(
            Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole()->impact_scope,
        );

        // And the default still points the other way: the very next department, untouched,
        // keeps requiring both. Without this half the test would pass with the gate wired
        // to a constant false.
        $this->post($this->tenantUrl('/tickets'), [
            'subject' => 'Reporting down',
            'description' => 'Nobody can open reports.',
            'priority' => 'high',
            'client_id' => $client->id,
            'department_id' => $support->id,
        ])->assertSessionHasErrors(['impact_scope', 'workaround']);
    }

    public function test_a_stray_impact_scope_on_a_department_that_does_not_ask_is_still_validated(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $leave = Department::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'People',
            'asks_impact' => false,
        ]);

        // The same mechanism that refuses a stray `environment=prod` on a Sales ticket: not
        // applicable is not the same as unvalidated, or an unvalidated string reaches the
        // column and the prompt.
        $this->post($this->tenantUrl('/tickets'), [
            'subject' => 'Annual leave',
            'description' => 'Requesting time off.',
            'priority' => 'low',
            'client_id' => $client->id,
            'department_id' => $leave->id,
            'impact_scope' => 'the entire planet',
        ])->assertSessionHasErrors('impact_scope');

        $this->assertSame(0, Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    /**
     * FOLLOW-UP 28 again, for P9's one new query, and discharged the same way.
     *
     * `Department::asksImpactFor()` states `tenant_id` explicitly because it is reached
     * from a FormRequest AND from the session-less intake analysis and draft service. An
     * end-to-end test on a session-bearing surface cannot prove that predicate — Department's
     * own global scope masks its absence — so the assertion is on the resolver, with a
     * hostile raw-inserted decoy in the WRONG workspace.
     *
     * MUTATION CHECKED by hand: deleting `->where('tenant_id', $tenantId)` from
     * asksImpactFor() reds `assertTrue(... $tenantA, $foreignId)`'s sibling below (tenant A
     * would read tenant B's `false` instead of the default), and this file's other
     * request-driven tests stay green — which is the masking follow-up 28 describes.
     */
    public function test_a_foreign_departments_impact_flag_cannot_decide_this_workspaces_fields(): void
    {
        $tenantA = $this->tenantOnPlan();
        $tenantB = $this->tenantOnPlan();

        // Raw-inserted so no scope, factory default or observer can tidy it away. It has
        // OPTED OUT in its own workspace, which is the state that could leak.
        $foreignId = DB::table('departments')->insertGetId([
            'tenant_id' => $tenantB->id,
            'name' => 'People',
            'code' => 'PEOPLE',
            'color' => '#3b82f6',
            'is_active' => true,
            'is_default' => false,
            'is_technical' => false,
            'asks_impact' => false,
            'sort_order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The decoy really does have the group off, in its own workspace.
        $this->assertFalse(Department::asksImpactFor($tenantB->id, $foreignId));

        // Asked for from tenant A it resolves to the DEFAULT — the group stays ASKED, never
        // another workspace's answer. Without the predicate this returns false and tenant
        // A silently loses the rubric's two decisive inputs.
        $this->assertTrue(Department::asksImpactFor($tenantA->id, $foreignId));

        // An id that exists nowhere, and no id at all: both fail closed to ASKED, the
        // opposite direction from isTechnicalFor() and deliberately so.
        $this->assertTrue(Department::asksImpactFor($tenantA->id, 999999));
        $this->assertTrue(Department::asksImpactFor($tenantA->id, null));
    }
}
