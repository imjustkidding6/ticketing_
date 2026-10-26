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
        // P10: the gate is the CATEGORY. Technical on, impact on by default.
        $category = TicketCategory::factory()->create(['tenant_id' => $tenant->id, 'is_technical' => true]);

        $this->post($this->tenantUrl('/tickets'), [
            'subject' => 'Reporting down',
            'description' => 'Nobody can open reports',
            'priority' => 'high',
            'client_id' => $client->id,
            'category_id' => $category->id,
        ])->assertSessionHasErrors(['impact_scope', 'workaround', 'environment']);

        $this->assertSame(0, Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_the_internal_create_form_stores_all_five_declared_fields(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        // Both gates ON, so all five are asked for AND all five are stored.
        $category = TicketCategory::factory()->create(['tenant_id' => $tenant->id, 'is_technical' => true]);

        $this->post($this->tenantUrl('/tickets'), [
            'category_id' => $category->id,
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

    public function test_a_non_technical_category_is_not_asked_for_an_environment(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        // The default: a leave request, an invoice query. "Provision a laptop for a new
        // starter" has no environment, and requiring one trains the agent to pick a value
        // to clear the form.
        $category = TicketCategory::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Payroll']);

        $this->post($this->tenantUrl('/tickets'), [
            'subject' => 'Payroll portal will not accept my timesheet',
            'description' => 'The submit button does nothing.',
            'priority' => 'medium',
            'client_id' => $client->id,
            'category_id' => $category->id,
            // The impact pair is still required, and still answered: an HR fault hitting
            // everyone is more urgent than one hitting one person.
            'impact_scope' => 'team',
            'workaround' => 'partial',
        ])->assertSessionHasNoErrors();

        $ticket = Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();

        $this->assertSame('team', $ticket->impact_scope);
        $this->assertNull($ticket->environment);
    }

    /**
     * P10's deliberate reversal of P9's direction, and the behaviour a future reader is
     * most likely to try to "fix" back: these questions belong to a CATEGORY, so a ticket
     * nobody has put in one is asked NOTHING. The cost is real and accepted — the priority
     * rubric gets neither the breadth of impact nor the existence of a workaround, so
     * `critical` is out of reach for this ticket.
     */
    public function test_a_ticket_with_no_category_is_asked_nothing(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        // A configured category exists in the workspace; this ticket is simply not in it,
        // so the gate must read the TICKET's category rather than the workspace's.
        TicketCategory::factory()->create(['tenant_id' => $tenant->id, 'is_technical' => true]);

        $this->post($this->tenantUrl('/tickets'), [
            'subject' => 'Something is wrong',
            'description' => 'Not sure which team owns this.',
            'priority' => 'medium',
            'client_id' => $client->id,
        ])->assertSessionHasNoErrors();

        $ticket = Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();

        $this->assertNull($ticket->impact_scope);
        $this->assertNull($ticket->workaround);
        $this->assertNull($ticket->environment);
    }

    public function test_a_stray_environment_on_a_non_technical_ticket_is_still_validated(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $category = TicketCategory::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Quotes']);

        // Not applicable is not the same as unvalidated: an unvalidated string would reach
        // the column and the prompt.
        $this->post($this->tenantUrl('/tickets'), [
            'subject' => 'Quote request',
            'description' => 'Please send pricing.',
            'priority' => 'low',
            'client_id' => $client->id,
            'category_id' => $category->id,
            'impact_scope' => 'one_person',
            'workaround' => 'yes',
            'environment' => 'prod',
        ])->assertSessionHasErrors('environment');

        $this->assertSame(0, Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    /**
     * FOLLOW-UP 28, discharged directly rather than through a request.
     *
     * `TicketCategory::detailFlagsFor()` is the one new query this package adds, and it
     * carries an explicit `tenant_id` predicate because it is reached from a FormRequest AND
     * from the queued intake analysis and the draft service, where TenantScope applies no
     * predicate at all. An end-to-end test on a session-bearing surface cannot prove that
     * predicate: TicketCategory's own global scope would mask its absence. So the assertion
     * is on the resolver, with a hostile raw-inserted decoy — a category in the WRONG
     * workspace, configured so that BOTH of its answers differ from the fail-closed ones,
     * carrying the id the caller is about to ask about.
     *
     * MUTATION CHECKED by hand: deleting `->where('tenant_id', $tenantId)` from
     * detailFlagsFor() reds both tenant-A assertions below (tenant A reads tenant B's
     * `technical: true, asks_impact: false`), and the request-driven tests in this file stay
     * green — which is exactly the masking follow-up 28 describes.
     */
    public function test_a_foreign_categorys_detail_gates_cannot_decide_this_workspaces_fields(): void
    {
        $tenantA = $this->tenantOnPlan();
        $tenantB = $this->tenantOnPlan();
        $foreignDepartment = Department::factory()->create(['tenant_id' => $tenantB->id]);

        // Raw-inserted so no scope, factory default or observer can tidy it away. BOTH flags
        // are set away from the fail-closed answer: technical ON (which would demand an
        // environment) and impact OFF (which would drop the rubric's two inputs).
        $foreignId = DB::table('ticket_categories')->insertGetId([
            'tenant_id' => $tenantB->id,
            'department_id' => $foreignDepartment->id,
            'name' => 'Production outage',
            'color' => '#3b82f6',
            'is_active' => true,
            'is_technical' => true,
            'asks_impact' => false,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The decoy really is configured that way, in its own workspace.
        $this->assertSame(
            ['technical' => true, 'asks_impact' => false],
            TicketCategory::detailFlagsFor($tenantB->id, $foreignId),
        );

        // Asked for from tenant A it resolves to the NO-CATEGORY answer — never another
        // workspace's configuration. Without the predicate both halves come back wrong.
        $this->assertSame(
            ['technical' => false, 'asks_impact' => false],
            TicketCategory::detailFlagsFor($tenantA->id, $foreignId),
        );

        // And an id that exists nowhere, and no id at all: both fail closed to asking
        // nothing. This is the OPPOSITE direction from the deleted
        // Department::asksImpactFor(), deliberately — see detailFlagsFor()'s docblock.
        $this->assertSame(
            ['technical' => false, 'asks_impact' => false],
            TicketCategory::detailFlagsFor($tenantA->id, 999999),
        );
        $this->assertSame(
            ['technical' => false, 'asks_impact' => false],
            TicketCategory::detailFlagsFor($tenantA->id, null),
        );
    }

    public function test_a_category_that_switched_the_impact_questions_off_does_not_require_them(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $leave = TicketCategory::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Leave request',
            'asks_impact' => false,
        ]);
        $outage = TicketCategory::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Service down']);

        // P10: the gate is the CATEGORY ROW, so a leave request clears the form with no
        // scope and no workaround — which is the trade the category form spells out.
        $this->post($this->tenantUrl('/tickets'), [
            'subject' => 'Annual leave in March',
            'description' => 'Requesting the last week of March off.',
            'priority' => 'low',
            'client_id' => $client->id,
            'category_id' => $leave->id,
        ])->assertSessionHasNoErrors();

        $this->assertNull(
            Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole()->impact_scope,
        );

        // And the default still points the other way: the very next category, untouched,
        // keeps requiring both. Without this half the test would pass with the gate wired
        // to a constant false.
        $this->post($this->tenantUrl('/tickets'), [
            'subject' => 'Reporting down',
            'description' => 'Nobody can open reports.',
            'priority' => 'high',
            'client_id' => $client->id,
            'category_id' => $outage->id,
        ])->assertSessionHasErrors(['impact_scope', 'workaround']);
    }

    public function test_a_stray_impact_scope_on_a_category_that_does_not_ask_is_still_validated(): void
    {
        $tenant = $this->tenantOnPlan();
        $this->actor($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $leave = TicketCategory::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Leave request',
            'asks_impact' => false,
        ]);

        // The same mechanism that refuses a stray `environment=prod` on a quote: not
        // applicable is not the same as unvalidated, or an unvalidated string reaches the
        // column and the prompt.
        $this->post($this->tenantUrl('/tickets'), [
            'subject' => 'Annual leave',
            'description' => 'Requesting time off.',
            'priority' => 'low',
            'client_id' => $client->id,
            'category_id' => $leave->id,
            'impact_scope' => 'the entire planet',
        ])->assertSessionHasErrors('impact_scope');

        $this->assertSame(0, Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }
}
