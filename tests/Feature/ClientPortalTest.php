<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Http\Controllers\ClientPortalController;
use App\Models\Client;
use App\Models\Department;
use App\Models\License;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Tests\TestCase;

class ClientPortalTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(string $planSlug): Tenant
    {
        // Plan gating (abortIfStarter / feature checks) matches on the exact
        // canonical plan slug ('start', 'business', 'enterprise'), so the slug
        // must not be suffixed. RefreshDatabase keeps each test's plans unique.
        $plan = Plan::factory()->create(['slug' => $planSlug, 'features' => PlanFeature::forPlan($planSlug)]);
        $license = License::factory()->active()->forPlan($plan)->create();

        return Tenant::factory()->create(['license_id' => $license->id]);
    }

    public function test_portal_open_count_includes_every_live_status(): void
    {
        // A paused ticket is still live work. Counting only open/assigned/
        // in_progress told the customer their on_hold — and later their pending —
        // ticket had vanished. Ticket::OPEN_STATUSES is the one list.
        $tenant = $this->createTenant('business');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        foreach (Ticket::STATUSES as $status) {
            Ticket::factory()->create([
                'tenant_id' => $tenant->id,
                'client_id' => $client->id,
                // The department factory has only a handful of unique names and
                // this loop would exhaust them; the count under test does not
                // look at the department.
                'department_id' => null,
                'category_id' => null,
                'status' => $status,
                'is_spam' => false,
            ]);
        }

        // dashboard() is not routed today, so it is exercised directly rather
        // than over HTTP.
        $request = Request::create("/{$tenant->slug}/portal");
        $request->merge(['portal_client' => $client]);

        $stats = app(ClientPortalController::class)->dashboard($request, $tenant)->getData()['stats'];

        $this->assertSame(count(Ticket::OPEN_STATUSES), $stats['open']);
        $this->assertSame(count(Ticket::STATUSES), $stats['total']);
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

    public function test_submit_ticket_prefills_and_locks_fields_from_query_params(): void
    {
        $tenant = $this->createTenant('business');
        $dept = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Technical Software']);
        $cat = TicketCategory::factory()->create(['tenant_id' => $tenant->id, 'department_id' => $dept->id, 'name' => 'Software Application']);
        $product = Product::factory()->create(['tenant_id' => $tenant->id, 'category_id' => $cat->id, 'name' => 'Inventory Management System', 'is_active' => true]);

        $response = $this->get("/{$tenant->slug}/submit-ticket?".http_build_query([
            'name' => 'Superior Packaging Corporation',
            'email' => 'misit1.superior@gmail.com',
            'department' => 'Technical Software',
            'category' => 'software application', // case-insensitive match
            'Product and services' => 'Inventory Management System',
        ]))->assertOk();

        // Each provided field is locked via a hidden input carrying the resolved id/value.
        $response->assertSee('value="Superior Packaging Corporation"', false);
        $response->assertSee('name="department_id" value="'.$dept->id.'"', false);
        $response->assertSee('name="category_id" value="'.$cat->id.'"', false);
        $response->assertSee('name="product_ids[]" value="'.$product->id.'"', false);
        $response->assertSee('Software Application');
        // This test used to put the category in a *different* department to exercise
        // resolveSubmitPrefill()'s cross-department name fallback, and asserted that
        // the mismatched category was locked. A locked field is a hidden input, and
        // the submit path now refuses a category from another department, so that
        // link locked a value the visitor could neither see nor get past. The
        // fallback now only applies when the link names no department (it then
        // adopts the category's own), which the two prefill round-trip tests below
        // cover at POST level — the shape this assertion could not see.
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

    /**
     * A second workspace on an existing plan.
     *
     * plans.slug is unique, so a cross-tenant test cannot call createTenant()
     * twice for the same plan — it reuses the plan and issues a second licence.
     */
    private function createSiblingTenant(string $planSlug): Tenant
    {
        $plan = Plan::where('slug', $planSlug)->firstOrFail();
        $license = License::factory()->active()->forPlan($plan)->create();

        return Tenant::factory()->create(['license_id' => $license->id]);
    }

    /**
     * The public submit form is unauthenticated and its prefill links are unsigned,
     * so every foreign key it accepts is attacker-controlled. The FKs on
     * tickets.department_id/category_id stop invented numbers but not another
     * workspace's real id, and product_ids goes through sync() with no FK of that
     * shape at all. These pin the tenant check.
     */
    private function submitPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Portal User',
            'email' => 'portal@example.com',
            'subject' => 'Portal Test Ticket',
            'description' => 'Testing portal submission.',
        ], $overrides);
    }

    public function test_submit_rejects_another_tenants_department(): void
    {
        $tenant = $this->createTenant('business');
        $other = $this->createSiblingTenant('business');
        $foreignDept = Department::factory()->create(['tenant_id' => $other->id]);

        $this->post("/{$tenant->slug}/submit-ticket", $this->submitPayload([
            'department_id' => $foreignDept->id,
        ]))->assertSessionHasErrors(['department_id' => 'The selected department is invalid.']);

        $this->assertDatabaseMissing('tickets', ['subject' => 'Portal Test Ticket']);
    }

    public function test_submit_rejects_another_tenants_category(): void
    {
        $tenant = $this->createTenant('business');
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
        $other = $this->createSiblingTenant('business');
        $foreignCat = TicketCategory::factory()->create([
            'tenant_id' => $other->id,
            'department_id' => Department::factory()->create(['tenant_id' => $other->id])->id,
        ]);

        $this->post("/{$tenant->slug}/submit-ticket", $this->submitPayload([
            'department_id' => $dept->id,
            'category_id' => $foreignCat->id,
        ]))->assertSessionHasErrors(['category_id' => 'The selected category is invalid.']);

        $this->assertDatabaseMissing('tickets', ['subject' => 'Portal Test Ticket']);
    }

    public function test_submit_rejects_another_tenants_product(): void
    {
        $tenant = $this->createTenant('business');
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
        $other = $this->createSiblingTenant('business');
        $foreignProduct = Product::factory()->create(['tenant_id' => $other->id, 'is_active' => true]);

        $this->post("/{$tenant->slug}/submit-ticket", $this->submitPayload([
            'department_id' => $dept->id,
            'product_ids' => [$foreignProduct->id],
        ]))->assertSessionHasErrors(['product_ids.0' => 'One of the selected products is invalid.']);

        $this->assertDatabaseMissing('tickets', ['subject' => 'Portal Test Ticket']);
    }

    public function test_submit_does_not_distinguish_a_foreign_id_from_a_nonexistent_one(): void
    {
        // Otherwise the route is an existence oracle: an anonymous visitor could
        // enumerate another workspace's department ids by the wording.
        $tenant = $this->createTenant('business');
        $other = $this->createSiblingTenant('business');
        $foreignDept = Department::factory()->create(['tenant_id' => $other->id]);

        $foreign = $this->post("/{$tenant->slug}/submit-ticket", $this->submitPayload([
            'department_id' => $foreignDept->id,
        ]))->assertSessionHasErrors('department_id');
        $missing = $this->post("/{$tenant->slug}/submit-ticket", $this->submitPayload([
            'department_id' => 987654,
        ]))->assertSessionHasErrors('department_id');

        $foreignMessages = $foreign->getSession()->get('errors')->get('department_id');
        $missingMessages = $missing->getSession()->get('errors')->get('department_id');

        $this->assertSame(['The selected department is invalid.'], $foreignMessages);
        $this->assertSame($foreignMessages, $missingMessages);
    }

    public function test_submit_rejects_an_inactive_department(): void
    {
        // The form only offers active departments (loadDepartmentsAndCategories
        // filters on active()), so accepting a disabled one from a hand-crafted
        // POST would route tickets into a department the workspace retired.
        $tenant = $this->createTenant('business');
        $dept = Department::factory()->create(['tenant_id' => $tenant->id, 'is_active' => false]);

        $this->post("/{$tenant->slug}/submit-ticket", $this->submitPayload([
            'department_id' => $dept->id,
        ]))->assertSessionHasErrors(['department_id' => 'The selected department is invalid.']);
    }

    public function test_submit_rejects_a_category_from_a_different_department(): void
    {
        // The form's own department/category linkage never offers this pair, so a
        // POST that carries it is hand-crafted.
        $tenant = $this->createTenant('business');
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
        $otherDept = Department::factory()->create(['tenant_id' => $tenant->id]);
        $cat = TicketCategory::factory()->create(['tenant_id' => $tenant->id, 'department_id' => $otherDept->id]);

        $this->post("/{$tenant->slug}/submit-ticket", $this->submitPayload([
            'department_id' => $dept->id,
            'category_id' => $cat->id,
        ]))->assertSessionHasErrors(['category_id' => 'The selected category is invalid.']);
    }

    public function test_submit_accepts_the_tenants_own_catalog_end_to_end(): void
    {
        $tenant = $this->createTenant('business');
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
        $cat = TicketCategory::factory()->create(['tenant_id' => $tenant->id, 'department_id' => $dept->id]);
        $product = Product::factory()->create(['tenant_id' => $tenant->id, 'category_id' => $cat->id, 'is_active' => true]);

        $this->post("/{$tenant->slug}/submit-ticket", $this->submitPayload([
            'department_id' => $dept->id,
            'category_id' => $cat->id,
            'product_ids' => [$product->id],
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $ticket = Ticket::withoutGlobalScopes()->where('subject', 'Portal Test Ticket')->sole();
        $this->assertSame($tenant->id, $ticket->tenant_id);
        $this->assertSame($dept->id, $ticket->department_id);
        $this->assertSame($cat->id, $ticket->category_id);
        $this->assertSame([$product->id], $ticket->products()->pluck('products.id')->all());
    }

    /**
     * Submit the form a prefill link renders, using only the values the visitor
     * cannot change.
     *
     * Locked prefill fields render as hidden inputs, so this is the round trip the
     * renders-correctly assertions could not see: whatever the link locks has to
     * survive validation, or the link is a dead end on an invisible control.
     *
     * @return array<string, mixed> the payload that was posted
     */
    private function submitRenderedPrefill(Tenant $tenant, string $query, array $extra = []): array
    {
        $html = $this->get("/{$tenant->slug}/submit-ticket?".$query)->assertOk()->getContent();

        $payload = $this->submitPayload($extra);
        if (preg_match('/name="department_id" value="(\d+)"/', $html, $m)) {
            $payload['department_id'] = (int) $m[1];
        }
        if (preg_match('/name="category_id" value="(\d+)"/', $html, $m)) {
            $payload['category_id'] = (int) $m[1];
        }
        if (preg_match_all('/name="product_ids\[\]" value="(\d+)"/', $html, $m)) {
            $payload['product_ids'] = array_map('intval', $m[1]);
        }

        $this->post("/{$tenant->slug}/submit-ticket", $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        return $payload;
    }

    public function test_a_category_only_prefill_link_adopts_and_locks_that_categorys_department(): void
    {
        // With no department named in the link there is nothing to contradict, so
        // the category's own department is adopted — which also means the pair the
        // hidden inputs post is consistent.
        $tenant = $this->createTenant('business');
        $dept = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Billing']);
        $cat = TicketCategory::factory()->create(['tenant_id' => $tenant->id, 'department_id' => $dept->id, 'name' => 'Invoice Query']);

        $payload = $this->submitRenderedPrefill($tenant, http_build_query(['category' => 'invoice query']));

        $this->assertSame($cat->id, $payload['category_id']);
        $this->assertSame($dept->id, $payload['department_id'], 'the category’s department must be adopted');

        $ticket = Ticket::withoutGlobalScopes()->where('subject', 'Portal Test Ticket')->sole();
        $this->assertSame($dept->id, $ticket->department_id);
        $this->assertSame($cat->id, $ticket->category_id);
    }

    public function test_a_category_only_prefill_link_ignores_a_category_under_an_inactive_department(): void
    {
        // Deactivating a department cascades nothing, so its categories stay active and
        // are still loaded. Adopting one would lock a hidden department_id that the
        // `is_active` rule then refuses — a link nobody could submit. Nothing is locked
        // instead, so the visitor picks from the (active) department select as usual.
        $tenant = $this->createTenant('business');
        $retired = Department::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Retired Team',
            'is_active' => false,
        ]);
        TicketCategory::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $retired->id,
            'name' => 'Legacy Request',
        ]);
        $live = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Support']);

        $payload = $this->submitRenderedPrefill(
            $tenant,
            http_build_query(['category' => 'legacy request']),
            ['department_id' => $live->id],
        );

        $this->assertArrayNotHasKey('category_id', $payload, 'the category must not be locked');
        $this->assertSame($live->id, $payload['department_id'], 'no inactive department may be adopted');

        $ticket = Ticket::withoutGlobalScopes()->where('subject', 'Portal Test Ticket')->sole();
        $this->assertSame($live->id, $ticket->department_id);
        $this->assertNull($ticket->category_id);
    }

    public function test_a_prefill_link_naming_a_category_outside_its_department_still_submits(): void
    {
        // The department named in the link wins and the mismatched category is not
        // locked, so the visitor gets the department's own category list instead of
        // a hidden value validation would refuse. Printed QR links keep working.
        $tenant = $this->createTenant('business');
        $dept = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Technical Software']);
        $otherDept = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Billing']);
        TicketCategory::factory()->create(['tenant_id' => $tenant->id, 'department_id' => $otherDept->id, 'name' => 'Software Application']);

        $payload = $this->submitRenderedPrefill($tenant, http_build_query([
            'department' => 'Technical Software',
            'category' => 'software application',
        ]));

        $this->assertSame($dept->id, $payload['department_id']);
        $this->assertArrayNotHasKey('category_id', $payload, 'a mismatched category must not be locked into a hidden input');
    }

    public function test_a_fully_prefilled_link_submits_without_error(): void
    {
        $tenant = $this->createTenant('business');
        $dept = Department::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Technical Software']);
        $cat = TicketCategory::factory()->create(['tenant_id' => $tenant->id, 'department_id' => $dept->id, 'name' => 'Software Application']);
        $product = Product::factory()->create(['tenant_id' => $tenant->id, 'category_id' => $cat->id, 'name' => 'Inventory Management System', 'is_active' => true]);

        $payload = $this->submitRenderedPrefill($tenant, http_build_query([
            'department' => 'Technical Software',
            'category' => 'Software Application',
            'Product and services' => 'Inventory Management System',
        ]));

        $this->assertSame([$dept->id, $cat->id, [$product->id]], [
            $payload['department_id'], $payload['category_id'], $payload['product_ids'],
        ]);
    }

    public function test_submit_stores_a_null_category_when_the_select_is_not_submitted(): void
    {
        // 'category_id' is nullable, so a form that omits the key entirely leaves it
        // out of $validated — reading it unconditionally was a 500 on a legitimate
        // public submission.
        $tenant = $this->createTenant('business');
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);

        $this->post("/{$tenant->slug}/submit-ticket", $this->submitPayload([
            'department_id' => $dept->id,
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $ticket = Ticket::withoutGlobalScopes()->where('subject', 'Portal Test Ticket')->sole();
        $this->assertNull($ticket->category_id);
    }

    public function test_submit_rejects_an_inactive_category(): void
    {
        $tenant = $this->createTenant('business');
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
        $cat = TicketCategory::factory()->create(['tenant_id' => $tenant->id, 'department_id' => $dept->id, 'is_active' => false]);

        $this->post("/{$tenant->slug}/submit-ticket", $this->submitPayload([
            'department_id' => $dept->id,
            'category_id' => $cat->id,
        ]))->assertSessionHasErrors(['category_id' => 'The selected category is invalid.']);
    }

    public function test_submit_rejects_an_inactive_product(): void
    {
        $tenant = $this->createTenant('business');
        $dept = Department::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $tenant->id, 'is_active' => false]);

        $this->post("/{$tenant->slug}/submit-ticket", $this->submitPayload([
            'department_id' => $dept->id,
            'product_ids' => [$product->id],
        ]))->assertSessionHasErrors(['product_ids.0' => 'One of the selected products is invalid.']);
    }

    public function test_portal_registration_without_a_phone_number_does_not_error(): void
    {
        // Same latent 500 as category_id, one method away: 'phone' is nullable and
        // was read unconditionally. register() is not routed today, so it is
        // exercised directly, as dashboard() is above.
        $tenant = $this->createTenant('business');

        $request = Request::create("/{$tenant->slug}/portal/register", 'POST', [
            'name' => 'Phoneless Person',
            'email' => 'phoneless@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $request->setLaravelSession($this->app['session']->driver());

        try {
            app(ClientPortalController::class)->register($request, $tenant);
        } catch (RouteNotFoundException) {
            // register() redirects to portal.dashboard, which no route defines
            // today; the client row is written before that redirect is built.
        }

        $this->assertDatabaseHas('clients', [
            'tenant_id' => $tenant->id,
            'email' => 'phoneless@example.com',
            'phone' => null,
        ]);
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
