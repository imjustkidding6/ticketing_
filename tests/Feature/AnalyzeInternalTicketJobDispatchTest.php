<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Jobs\AnalyzeInternalTicketJob;
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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * WHERE the intake analysis may be triggered from — and that it is exactly one place.
 *
 * The greppable single call site is the whole design: an observer or a `TicketCreated`
 * event would fire for the REST API, the Jude connector, seeders, factories and every
 * import row with nothing able to tell them apart, so the guarantee has to be structural.
 */
class AnalyzeInternalTicketJobDispatchTest extends TestCase
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

    private function actAs(Tenant $tenant): User
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
     * @return array<string, mixed>
     */
    private function payload(Tenant $tenant): array
    {
        $department = Department::factory()->create(['tenant_id' => $tenant->id]);
        $category = TicketCategory::factory()->create([
            'tenant_id' => $tenant->id,
            'department_id' => $department->id,
        ]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        return [
            'client_id' => $client->id,
            'department_id' => $department->id,
            'category_id' => $category->id,
            'subject' => 'Printer offline after update',
            'description' => 'The office printer stopped responding after the Windows update.',
            // The universal half of the declared detail set is required on the internal
            // create form in every department.
            'impact_scope' => 'one_person',
            'workaround' => 'none',
            'priority' => 'medium',
            'status' => 'open',
        ];
    }

    public function test_the_internal_analysis_job_is_dispatched_from_exactly_one_file(): void
    {
        $hits = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (str_contains($contents, 'AnalyzeInternalTicketJob::dispatch')) {
                $hits[] = str_replace(app_path().'/', '', $file->getPathname());
            }
        }

        $this->assertSame(['Http/Controllers/TicketController.php'], $hits);
    }

    public function test_creating_a_ticket_internally_dispatches_the_analysis_with_the_creating_engineer(): void
    {
        $tenant = $this->createTenant();
        $user = $this->actAs($tenant);

        $this->post($this->tenantUrl('tickets'), $this->payload($tenant))->assertRedirect();

        $ticket = Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();

        Queue::assertPushed(AnalyzeInternalTicketJob::class, 1);
        Queue::assertPushed(
            AnalyzeInternalTicketJob::class,
            fn (AnalyzeInternalTicketJob $job) => $job->tenantId === $tenant->id
                && $job->ticketId === (int) $ticket->getKey()
                // The AUTHENTICATED engineer, not `tickets.created_by`: the column is not
                // confirmed to be populated on every internal create path, and the
                // session user is unambiguous.
                && $job->userId === (int) $user->getKey(),
        );
    }

    /**
     * The double-submit collapse returns before `createTicket()` runs, so there is no new
     * ticket to analyse — and a second dispatch would pay for a second analysis of the
     * first one.
     */
    public function test_a_duplicate_collapsed_submission_dispatches_no_second_analysis(): void
    {
        $tenant = $this->createTenant();
        $this->actAs($tenant);
        $payload = $this->payload($tenant);

        $this->post($this->tenantUrl('tickets'), $payload)->assertRedirect();
        $this->post($this->tenantUrl('tickets'), $payload)->assertRedirect();

        $this->assertSame(1, Ticket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        Queue::assertPushed(AnalyzeInternalTicketJob::class, 1);
    }
}
