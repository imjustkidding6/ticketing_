<?php

namespace Tests\Feature;

use App\Enums\PlanFeature;
use App\Models\License;
use App\Models\Plan;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Guardrails against cross-tenant leaks in Blade views.
 *
 * Background: the admin SLA revamp repurposed the tenant views in
 * resources/views/sla/* — switching them to layouts.admin and running an
 * unscoped Ticket::withoutGlobalScopes() query inline — so every tenant
 * saw the admin UI and other tenants' tickets. These tests fail any PR
 * that reintroduces either pattern.
 *
 * Rules enforced:
 *  - Views outside resources/views/admin/ must not extend layouts.admin.
 *    Admin screens get their own view under admin/ instead of hijacking
 *    a tenant view.
 *  - withoutGlobalScopes in a view outside admin/ must carry a tenant_id
 *    filter in the same statement (same line), e.g.
 *    AppSetting::withoutGlobalScopes()->where('tenant_id', $tenant->id).
 *    Admin views are exempt: the admin panel is cross-tenant by design.
 *  - PHP outside App\Http\Controllers\Admin\ must not bypass the scope
 *    without a tenant filter in the same *statement* — deliberately not the
 *    same line, because most queries here span several.
 *  - Any file outside Admin\ that branches on routeIs('admin.*') has declared
 *    itself dual-surface and must be listed in DUAL_SURFACE, naming the
 *    behaviour test that proves its tenant half. The /admin/sla defect was
 *    exactly a dual-surface file whose tenant half nothing asserted.
 */
class TenantIsolationGuardrailsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Files outside App\Http\Controllers\Admin\ that choose their behaviour
     * from routeIs('admin.*'), mapped to the behaviour test covering them.
     *
     * A static check cannot decide whether a given scope bypass is legitimate
     * — surface is not a property of a file. What it can do is refuse to let a
     * file serve both surfaces without someone asserting both.
     *
     * @var array<string, string>
     */
    private const DUAL_SURFACE = [
        'app/Http/Controllers/SlaPolicyController.php' => 'Tests\Feature\DualSurfaceScopeTest',
        // The help centre renders the same config/help.php filtered for a
        // tenant role and unfiltered for platform staff; DualSurfaceScopeTest
        // asserts the tenant half only sees what the reader's role permits.
        'app/Http/Controllers/TutorialController.php' => 'Tests\Feature\DualSurfaceScopeTest',
        // The base controller's permission bypass: is_admin skips checks on
        // admin.* routes only. Covered by asserting it does NOT apply on a
        // tenant route.
        'app/Http/Controllers/Controller.php' => 'Tests\Feature\DualSurfaceScopeTest',
    ];

    /**
     * Scope bypasses outside Admin\ that have been read and judged safe.
     *
     * Each entry is a file plus a distinctive substring of the statement, so
     * blessing one bypass does not bless the next one added to the same file.
     * Unused entries fail too — an allowance that no longer matches any code
     * is a stale claim about the codebase.
     *
     * @var list<array{file: string, contains: string, why: string}>
     */
    private const ALLOWED_BYPASSES = [
        [
            'file' => 'app/Http/Controllers/SlaPolicyController.php',
            'contains' => '$isAdmin ? SlaPolicy::withoutGlobalScopes()',
            'why' => 'Dual-surface: the bypass is confined to the admin branch of the ternary.',
        ],
        [
            'file' => 'app/Http/Controllers/SlaPolicyController.php',
            'contains' => '$scoped = fn ($q) => $isAdmin ? $q->withoutGlobalScopes()',
            'why' => 'withCount() closures follow the same branch as the outer query, on purpose.',
        ],
        [
            'file' => 'app/Http/Controllers/SlaPolicyController.php',
            'contains' => '$nearBreach = Ticket::withoutGlobalScopes()',
            'why' => 'adminOps() is only called on the admin branch; the panel is cross-tenant by intent.',
        ],
        [
            'file' => 'app/Http/Controllers/SlaPolicyController.php',
            'contains' => "'department' => fn (\$q) => \$q->withoutGlobalScopes()",
            'why' => 'Eager loads on an already-unscoped admin query; a scoped relation would render N/A.',
        ],
        [
            'file' => 'app/Http/Controllers/GitHubWebhookController.php',
            'contains' => 'BugReport::withoutGlobalScopes()',
            'why' => 'Webhook, no session: bug reports are matched by GitHub issue number across tenants.',
        ],
        [
            'file' => 'app/Models/Ticket.php',
            'contains' => "where('ticket_number', \$number)->exists()",
            'why' => 'Ticket numbers are globally unique; an existence probe returns no foreign data.',
        ],
        [
            'file' => 'app/Models/TicketTask.php',
            'contains' => "->where('ticket_id', \$task->ticket_id)",
            'why' => 'Numbering within one ticket, which is itself already tenant-owned.',
        ],
        [
            'file' => 'app/Models/ApiToken.php',
            'contains' => "where('token', hash('sha256', \$plainToken))",
            'why' => 'Runs before any session exists — this lookup is what establishes the tenant.',
        ],
        [
            'file' => 'app/Services/SlaService.php',
            'contains' => 'return Ticket::withoutGlobalScopes()',
            'why' => 'The breach sweep runs from the scheduler across every tenant by design.',
        ],
        [
            'file' => 'app/Services/SlaService.php',
            'contains' => "'client' => fn (\$q) => \$q->withoutGlobalScopes()",
            'why' => 'Eager loads on the cross-tenant sweep; a scoped relation resolves to null.',
        ],
        [
            'file' => 'app/Services/AiAssistantService.php',
            'contains' => "\$w->where('email', 'like'",
            'why' => 'Relation filter inside a query whose own statement filters on tenant_id.',
        ],
        [
            'file' => 'app/Services/TicketService.php',
            'contains' => "Ticket::withoutGlobalScopes()->where('id', \$ticket->id)",
            'why' => 'Re-queries one already-resolved, already-scoped ticket by primary key.',
        ],
        [
            // NOT a clean bill of health. Route-model binding here ignores the
            // tenant scope, and AiAssistantController::myConversationMessages
            // compensates with an explicit tenant_id/user_id check while
            // showConversation (settings.ai.conversation) does not. Raised as a
            // follow-up package; listed so this guardrail can ship without
            // either hiding it or blocking on a fix in another subsystem.
            'file' => 'app/Models/ChatConversation.php',
            'contains' => 'public function resolveRouteBinding',
            'why' => 'KNOWN GAP — callers must check tenant_id themselves and one of them does not.',
        ],
    ];

    /**
     * @return array<string, string> relative path => contents for every PHP
     *                               file under app/ that is not admin-only
     */
    private function nonAdminPhpFiles(): array
    {
        $files = [];
        foreach (File::allFiles(app_path()) as $file) {
            $relative = 'app/'.str_replace('\\', '/', $file->getRelativePathname());
            if (! str_ends_with($relative, '.php')) {
                continue;
            }
            // Admin controllers are cross-tenant by design; console commands and
            // jobs run without a session and are swept by their own tests.
            foreach (['app/Http/Controllers/Admin/', 'app/Console/', 'app/Jobs/'] as $exempt) {
                if (str_starts_with($relative, $exempt)) {
                    continue 2;
                }
            }
            $files[$relative] = $file->getContents();
        }

        $this->assertNotEmpty($files, 'No PHP files found under app/ — is app_path() correct?');

        return $files;
    }

    /**
     * @return array<string, string> relative path => contents for every
     *                               Blade file outside resources/views/admin/
     */
    private function nonAdminBladeFiles(): array
    {
        $views = [];
        foreach (File::allFiles(resource_path('views')) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            if (! str_ends_with($relative, '.blade.php') || str_starts_with($relative, 'admin/')) {
                continue;
            }
            $views[$relative] = $file->getContents();
        }

        $this->assertNotEmpty($views, 'No Blade views found — is resource_path(\'views\') correct?');

        return $views;
    }

    public function test_views_outside_admin_never_extend_the_admin_layout(): void
    {
        $offenders = [];
        foreach ($this->nonAdminBladeFiles() as $path => $contents) {
            if (preg_match('/@extends\(\s*[\'"]layouts\.admin/', $contents)) {
                $offenders[] = $path;
            }
        }

        $this->assertSame([], $offenders, sprintf(
            "Tenant-facing views must not use the admin layout. Move admin screens to resources/views/admin/ instead of repurposing tenant views: \n - %s",
            implode("\n - ", $offenders)
        ));
    }

    public function test_views_outside_admin_never_bypass_tenant_scope_without_a_tenant_filter(): void
    {
        $offenders = [];
        foreach ($this->nonAdminBladeFiles() as $path => $contents) {
            foreach (explode("\n", $contents) as $i => $line) {
                if (str_contains($line, 'withoutGlobalScopes') && ! str_contains($line, 'tenant_id')) {
                    $offenders[] = $path.':'.($i + 1);
                }
            }
        }

        $this->assertSame([], $offenders, sprintf(
            "withoutGlobalScopes() in a tenant-facing view leaks data across tenants unless the same statement filters by tenant_id (keep the ->where('tenant_id', ...) on the same line): \n - %s",
            implode("\n - ", $offenders)
        ));
    }

    public function test_php_outside_admin_never_bypasses_tenant_scope_without_a_tenant_filter(): void
    {
        $offenders = [];
        $usedAllowances = [];

        foreach ($this->nonAdminPhpFiles() as $path => $contents) {
            // Statements, not lines: almost every query here is a multi-line
            // builder, and a line-based grep would either fire on all of them
            // or teach people to keep withoutGlobalScopes() on its own line.
            foreach (explode(';', $contents) as $statement) {
                if (! str_contains($statement, 'withoutGlobalScopes')) {
                    continue;
                }
                if (str_contains($statement, 'tenant_id') || str_contains($statement, '$tenant->id')) {
                    continue;
                }

                $allowance = null;
                foreach (self::ALLOWED_BYPASSES as $i => $allowed) {
                    if ($allowed['file'] === $path && str_contains($statement, $allowed['contains'])) {
                        $allowance = $i;
                        break;
                    }
                }

                if ($allowance === null) {
                    $offenders[] = $path.': '.trim(preg_replace('/\s+/', ' ', substr($statement, -120)));
                } else {
                    $usedAllowances[$allowance] = true;
                }
            }
        }

        $this->assertSame([], $offenders, sprintf(
            "withoutGlobalScopes() outside App\\Http\\Controllers\\Admin\\ must filter by tenant_id in the same statement. If a bypass is genuinely correct, add it to ALLOWED_BYPASSES with a reason — do not widen the rule: \n - %s",
            implode("\n - ", $offenders)
        ));

        $stale = [];
        foreach (self::ALLOWED_BYPASSES as $i => $allowed) {
            if (! isset($usedAllowances[$i])) {
                $stale[] = $allowed['file'].' :: '.$allowed['contains'];
            }
        }

        $this->assertSame([], $stale, sprintf(
            "These ALLOWED_BYPASSES entries match no code any more. Remove them rather than leaving a standing permission for code that no longer exists: \n - %s",
            implode("\n - ", $stale)
        ));
    }

    public function test_every_dual_surface_controller_is_covered_by_a_behaviour_test(): void
    {
        $declared = [];
        foreach ($this->nonAdminPhpFiles() as $path => $contents) {
            if (preg_match('/routeIs\(\s*[\'"]admin\.\*[\'"]\s*\)/', $contents)) {
                $declared[] = $path;
            }
        }
        sort($declared);

        $registered = array_keys(self::DUAL_SURFACE);
        sort($registered);

        $this->assertSame($registered, $declared, sprintf(
            "A file outside App\\Http\\Controllers\\Admin\\ that branches on routeIs('admin.*') serves both surfaces. Register it in DUAL_SURFACE and assert its TENANT half in the named behaviour test — /admin/sla shipped cross-tenant precisely because its tenant half was unasserted.\nDeclared: %s\nRegistered: %s",
            implode(', ', $declared),
            implode(', ', $registered)
        ));

        foreach (self::DUAL_SURFACE as $file => $testClass) {
            $this->assertTrue(
                class_exists($testClass),
                "{$file} names {$testClass} as its behaviour cover, but that test class does not exist."
            );
        }
    }

    public function test_tenant_sla_page_renders_tenant_view_without_foreign_data(): void
    {
        $plan = Plan::factory()->create(['slug' => 'business', 'features' => PlanFeature::forPlan('business')]);

        $tenantA = Tenant::factory()->create([
            'license_id' => License::factory()->active()->forPlan($plan)->create()->id,
        ]);
        $tenantB = Tenant::factory()->create([
            'license_id' => License::factory()->active()->forPlan($plan)->create()->id,
        ]);

        SlaPolicy::factory()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'ZZZ Foreign Tenant Policy',
        ]);

        $user = User::factory()->create();
        $tenantA->addUser($user, 'admin');

        $roleService = app(TenantRoleService::class);
        $roleService->setupDefaultRoles($tenantA);
        $roleService->syncRole($user, 'admin', $tenantA);

        $response = $this->actingAs($user)
            ->withTenant($tenantA)
            ->withSession(['current_tenant_id' => $tenantA->id])
            ->get($this->tenantUrl('/sla'));

        $response->assertOk()
            ->assertViewIs('sla.index')
            // The admin layout renders an admin-sidebar element; the tenant page must not.
            ->assertDontSee('admin-sidebar')
            ->assertDontSee('ZZZ Foreign Tenant Policy');
    }

    public function test_admin_sla_page_uses_the_admin_view_and_is_cross_tenant(): void
    {
        $plan = Plan::factory()->create(['slug' => 'business', 'features' => PlanFeature::forPlan('business')]);

        $tenantA = Tenant::factory()->create([
            'license_id' => License::factory()->active()->forPlan($plan)->create()->id,
        ]);
        $tenantB = Tenant::factory()->create([
            'license_id' => License::factory()->active()->forPlan($plan)->create()->id,
        ]);

        SlaPolicy::factory()->create(['tenant_id' => $tenantA->id, 'name' => 'AAA Alpha Policy']);
        SlaPolicy::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'ZZZ Foreign Tenant Policy']);

        $admin = User::factory()->create(['is_admin' => true]);

        // A view-name assertion alone would not notice that the page is only
        // cross-tenant when no tenant happens to be in session — which is what it
        // used to be. Assert the data, with the harder session state set.
        $this->actingAs($admin)
            ->withSession(['current_tenant_id' => $tenantA->id])
            ->get('/admin/sla')
            ->assertOk()
            ->assertViewIs('admin.sla.index')
            ->assertSee('AAA Alpha Policy')
            ->assertSee('ZZZ Foreign Tenant Policy');
    }
}
