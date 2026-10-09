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

        $this->post($this->tenantUrl('/tickets'), [
            'subject' => 'Reporting down',
            'description' => 'Nobody can open reports',
            'priority' => 'high',
            'client_id' => $client->id,
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
}
