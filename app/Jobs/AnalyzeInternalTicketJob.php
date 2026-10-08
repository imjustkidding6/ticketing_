<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PlanFeature;
use App\Exceptions\OpenAiException;
use App\Models\AiTicketAnalysis;
use App\Models\AppSetting;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AI\AnalyzeTicketService;
use App\Services\PlanService;
use App\Support\TenantTime;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Analyse one INTERNALLY created ticket, after the engineer's create form has committed it.
 *
 * The create form's "Polish with Nexus AI" answers a different question on a different
 * input — the engineer's rough notes, with no ticket id, no retrieval and a small token
 * ceiling — and its job is to decide what gets SAVED. This job's job is to brief whoever
 * opens the ticket: a summary, a category suggestion, a troubleshooting checklist and the
 * top similar resolved tickets, hydrated into the panel on `tickets.show` with no click.
 *
 * Modelled on AnalyzePublicTicketJob line for line, with three differences: the creating
 * engineer rides along as a primitive id so the spend is attributable to a person,
 * `TRIGGER_INTAKE` keeps the internal population readable apart from the portal's, and
 * nothing here serves the portal.
 *
 * Dispatched from exactly one line of exactly one method — TicketController::store().
 * There is no Ticket observer and no event, deliberately: an observer would fire for the
 * REST API, the Jude connector, seeders, factories and every import row, with nothing able
 * to tell them apart. The guarantee is structural and greppable, and a test asserts the
 * dispatch string appears in exactly one file in `app/`.
 *
 * **No SerializesModels.** Primitive ids, plus lookups that drop the global scopes and
 * pin `tenant_id` explicitly instead. SerializesModels re-resolves on unserialize through
 * the GLOBAL SCOPE, and TenantScope applies no predicate at all in a worker with no
 * session — so it would resolve by raw id with no tenant predicate at all. Ids make the
 * predicate visible and testable.
 *
 * (The guardrail scanner reads `;`-delimited statements with comments stripped, so prose
 * naming the scope bypass in this docblock would read as an unfiltered one.)
 *
 * **Every gate is re-evaluated here, at run time, against an explicit Tenant.** A
 * workspace that switched AI off between the POST and the worker picking this up has
 * withdrawn consent. The answer in every case is a silent return: no row, no retry.
 */
class AnalyzeInternalTicketJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * One retry, not two. Every AI failure mode is handled terminally inside handle(), so
     * the only thing a further attempt can fix is an infrastructure blip between dispatch
     * and the first run.
     */
    public int $tries = 2;

    public int $backoff = 60;

    public int $timeout = 120;

    /**
     * Collapses a double dispatch at the queue level. A CONVENIENCE, not the correctness
     * guarantee — that is the unique index on `ai_ticket_analyses.auto_for_ticket_id`,
     * which `TRIGGER_INTAKE` now writes alongside `TRIGGER_AUTO`.
     */
    public int $uniqueFor = 900;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $ticketId,
        public readonly int $userId,
    ) {
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return 'analyze-internal-ticket:'.$this->ticketId;
    }

    /**
     * An analysis arriving an hour after creation has no triage value — the engineer has
     * already read the ticket they wrote.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(10);
    }

    public function handle(AnalyzeTicketService $analyzer, PlanService $plans): void
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null || ! $tenant->is_active || $tenant->suspended_at !== null) {
            return;
        }

        // Explicit tenant predicate: the only thing separating workspaces here, since
        // TenantScope is inert without a session.
        // `whereNull('deleted_at')` explicitly: withoutGlobalScopes() drops the
        // SoftDeletingScope along with TenantScope, so without it a ticket deleted between
        // dispatch and this run would still be analysed and billed for.
        $ticket = Ticket::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->find($this->ticketId);

        if ($ticket === null) {
            return;
        }

        // The creating engineer, resolved through THIS workspace's pivot. `User` carries
        // no global scope, so this predicate is the only thing separating workspaces for
        // this lookup — a raw id would resolve any user in the installation.
        //
        // A miss REFUSES the run rather than degrading to `user_id = null`: an
        // unattributable spend row is the thing the cost requirement exists to prevent,
        // and the engineer's own Analyze button is still one click away, so nothing is
        // unrecoverable. The cost of refusing is one lost automatic analysis on a
        // vanishingly rare race (the creator removed from the workspace between the POST
        // and the worker).
        $user = User::query()
            ->whereHas('tenants', fn ($q) => $q->where('tenant_id', $tenant->id))
            ->find($this->userId);

        if ($user === null) {
            Log::warning('Internal ticket analysis refused: the creating engineer is not a member of this workspace.', [
                'tenant_id' => $tenant->id,
                'ticket_id' => $ticket->getKey(),
                'user_id' => $this->userId,
            ]);

            return;
        }

        // Defence in depth, and the one check that is about the ticket rather than the
        // workspace: the department selects the categories, the engineers and the AI
        // agents, so a `department_id` pointing at another workspace's department has no
        // honest analysis to produce. StoreTicketRequest's `tenantExists('departments')`
        // is the first defence; this is the second, as everywhere else in this feature.
        if (! $analyzer->departmentIsOwnedByTicketTenant($ticket)) {
            Log::warning('Internal ticket analysis refused: the ticket\'s department belongs to another workspace.', [
                'tenant_id' => $tenant->id,
                'ticket_id' => $ticket->getKey(),
                'department_id' => $ticket->department_id,
            ]);

            return;
        }

        // ── The gates, re-evaluated at run time and resolved TENANT-EXPLICITLY ───────
        //
        // Before any session is set, deliberately: PlanService::currentTenantHasFeature()
        // and AppSetting::get() both read the session, and a gate that only works because
        // this job happened to set the context first is a gate that silently stops working
        // the day the ordering changes.
        if (! $plans->tenantHasFeature($tenant, PlanFeature::AiChatbot)) {
            return;
        }

        if (! $plans->tenantHasFeature($tenant, PlanFeature::AiAgents)) {
            return;
        }

        if ($this->setting($tenant, 'ai_enabled') !== '1') {
            return;
        }

        if ($this->setting($tenant, 'ai_agents_enabled') !== '1') {
            return;
        }

        // ── Tenant context, with the restore obligation ───────────────────────────────
        //
        // session('current_tenant_id') is GLOBAL RUNTIME STATE in a long-lived worker
        // process: one PHP process handles many jobs, so a job that sets it and does not
        // unset it hands the next job — possibly another tenant's — this workspace. The
        // cap is inside this block rather than above it because TenantTime::dayRange()
        // resolves the timezone through the session, so the four gates above stay
        // session-free and provable.
        $previousTenantId = session('current_tenant_id');
        session(['current_tenant_id' => $tenant->id]);

        try {
            if ($this->overDailyCap($tenant)) {
                Log::info('Internal ticket analysis skipped: daily cap reached.', [
                    'tenant_id' => $tenant->id,
                    'ticket_id' => $ticket->getKey(),
                ]);

                return;
            }

            // Cheap, and BEFORE any OpenAI spend: a retried job that already wrote its
            // analysis must not pay for a second one. The unique index would refuse the
            // row, but only after the money was spent.
            if ($analyzer->alreadyAutoAnalysed($ticket)) {
                return;
            }

            $analyzer->analyze($ticket, $user, AiTicketAnalysis::TRIGGER_INTAKE);
        } catch (OpenAiException $e) {
            // Includes a tripped circuit breaker, raised before any HTTP. Caught, NOT
            // allowed to propagate: letting the queue retry a breaker trip is exactly
            // "one outage kills the workspace", and the breaker exists to fail fast.
            report($e);
        } finally {
            session(['current_tenant_id' => $previousTenantId]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Internal ticket analysis failed.', [
            'tenant_id' => $this->tenantId,
            'ticket_id' => $this->ticketId,
            'exception' => $exception?->getMessage(),
        ]);
    }

    /**
     * One workspace setting, read against the tenant rather than the session.
     *
     * AppSetting::get() returns the DEFAULT when no session tenant is set, so in a worker
     * it would read every opt-in as OFF and this job would never run — the
     * permissive-looking failure that is actually silent.
     */
    private function setting(Tenant $tenant, string $key): ?string
    {
        return AppSetting::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('key', $key)
            ->value('value');
    }

    /**
     * Automatic analyses already written today, on the WORKSPACE's calendar.
     *
     * The cap counts BOTH automatic triggers against one budget: it exists to bound
     * UNATTENDED spend, and the intake run is unattended. An engineer pressing Analyze is
     * still never gated by it.
     *
     * `TenantTime::dayRange()`, never `whereDate('created_at', now()->toDateString())`:
     * that pattern evaluates the boundary on the SERVER's calendar, so a UTC+8 workspace's
     * cap would reset mid-afternoon.
     */
    private function overDailyCap(Tenant $tenant): bool
    {
        [$from, $to] = TenantTime::dayRange();

        $count = AiTicketAnalysis::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('trigger', AiTicketAnalysis::AUTOMATIC_TRIGGERS)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        return $count >= (int) config('ai.analyze.daily_cap', 100);
    }
}
