<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Jobs\AnalyzePublicTicketJob;
use App\Models\ApiToken;
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
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * WHERE the automatic analysis may be triggered from — and, mostly, where it may not.
 *
 * The negative is the point. "Imports get no analysis" must be a property of the service
 * boundary rather than of a list of call sites someone remembered, so the load-bearing
 * test here is the bare TicketService::createTicket() one: that is what a seeder, an
 * importer, the Jude connector's CreateTicket tool and the authenticated portal path all
 * reduce to.
 */
class AnalyzePublicTicketJobDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // OPENAI_API_KEY is live in this environment. Nothing in this suite may reach it.
        Http::preventStrayRequests();
        Http::fake();

        Queue::fake();
    }

    private function createTenant(string $planSlug = 'enterprise'): Tenant
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

    /**
     * @return array{0: Department, 1: TicketCategory}
     */
    private function departmentFor(Tenant $tenant): array
    {
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $category = TicketCategory::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
        ]);

        return [$department, $category];
    }

    private function actAs(Tenant $tenant, string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $tenant->addUser($user, 'member');

        $roles = app(TenantRoleService::class);
        $roles->setupDefaultRoles($tenant);
        $roles->syncRole($user, $role, $tenant);

        $this->actingAs($user)->withTenant($tenant)->withSession(['current_tenant_id' => $tenant->id]);
        session(['current_tenant_id' => $tenant->id]);

        return $user;
    }

    public function test_the_public_submit_path_dispatches_exactly_one_analysis(): void
    {
        $tenant = $this->createTenant();
        [$department, $category] = $this->departmentFor($tenant);

        $this->post("/{$tenant->slug}/submit-ticket", [
            'name' => 'Portal User',
            'email' => 'portal@example.com',
            'subject' => 'Printer offline',
            'description' => 'The office printer stopped responding.',
            'department_id' => $department->id,
            'category_id' => $category->id,
        ])->assertRedirect();

        $ticket = Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();

        Queue::assertPushed(AnalyzePublicTicketJob::class, 1);
        Queue::assertPushed(
            AnalyzePublicTicketJob::class,
            fn (AnalyzePublicTicketJob $job) => $job->tenantId === $tenant->id
                && $job->ticketId === (int) $ticket->getKey(),
        );
    }

    public function test_an_api_created_ticket_dispatches_no_analysis(): void
    {
        $tenant = $this->createTenant();
        [$department, $category] = $this->departmentFor($tenant);

        $plain = 'tk_'.str_repeat('a', 32);
        ApiToken::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'test',
            'token' => hash('sha256', $plain),
            'is_active' => true,
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$plain])
            ->postJson('/api/v1/tickets', [
                'subject' => 'API ticket',
                'description' => 'Raised through the REST API.',
                'client_email' => 'api@example.com',
                'client_name' => 'API Client',
                'department_id' => $department->id,
                'category_id' => $category->id,
            ])->assertSuccessful();

        Queue::assertNotPushed(AnalyzePublicTicketJob::class);
    }

    public function test_an_agent_created_ticket_dispatches_no_analysis(): void
    {
        $tenant = $this->createTenant();
        [$department, $category] = $this->departmentFor($tenant);
        $this->actAs($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->post($this->tenantUrl('tickets'), [
            'subject' => 'Agent ticket',
            'description' => 'Raised by an agent on the phone.',
            'client_id' => $client->id,
            'department_id' => $department->id,
            'category_id' => $category->id,
            'priority' => 'medium',
            'status' => 'open',
        ])->assertRedirect();

        Queue::assertNotPushed(AnalyzePublicTicketJob::class);
    }

    /**
     * The load-bearing negative: a seeder, an importer, the Jude connector's CreateTicket
     * tool and the authenticated portal submit all reduce to this one call.
     */
    public function test_a_ticket_created_directly_through_the_service_dispatches_no_analysis(): void
    {
        $tenant = $this->createTenant();
        [$department, $category] = $this->departmentFor($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        session(['current_tenant_id' => $tenant->id]);

        app(TicketService::class)->createTicket([
            'tenant_id' => $tenant->id,
            'subject' => 'Imported ticket',
            'description' => 'Row 4,112 of a migration CSV.',
            'priority' => 'low',
            'department_id' => $department->id,
            'category_id' => $category->id,
            'client_id' => $client->id,
            'created_by' => null,
        ]);

        Queue::assertNotPushed(AnalyzePublicTicketJob::class);
    }

    public function test_a_ticket_created_by_the_factory_dispatches_no_analysis(): void
    {
        $tenant = $this->createTenant();

        Ticket::factory()->create(['tenant_id' => $tenant->id]);

        Queue::assertNotPushed(AnalyzePublicTicketJob::class);
    }

    /**
     * The authenticated portal client chose their own priority and department, so the
     * triage question the automatic analysis answers does not arise. Pinned so a later
     * reader does not "fix" the omission.
     */
    public function test_the_authenticated_portal_submit_path_dispatches_no_analysis(): void
    {
        $tenant = $this->createTenant();
        [$department, $category] = $this->departmentFor($tenant);
        $client = Client::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'client@example.com',
        ]);
        session(['current_tenant_id' => $tenant->id]);

        app(TicketService::class)->createTicket([
            'tenant_id' => $tenant->id,
            'subject' => 'Portal client ticket',
            'description' => 'Submitted by a signed-in portal client.',
            'priority' => 'high',
            'department_id' => $department->id,
            'category_id' => $category->id,
            'client_id' => $client->id,
            'created_by' => null,
        ]);

        Queue::assertNotPushed(AnalyzePublicTicketJob::class);
    }

    /**
     * The guarantee is structural and greppable: there is no observer and no event, so if
     * someone later adds one this fails the build.
     */
    public function test_the_dispatch_appears_in_exactly_one_file(): void
    {
        $hits = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (str_contains($contents, 'AnalyzePublicTicketJob::dispatch')) {
                $hits[] = str_replace(app_path().'/', '', $file->getPathname());
            }
        }

        $this->assertSame(['Http/Controllers/ClientPortalController.php'], $hits);
    }
}
