<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PlanFeature;
use App\Exceptions\OpenAiException;
use App\Models\AiTicketAnalysis;
use App\Models\AppSetting;
use App\Models\Tenant;
use App\Models\Ticket;
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
 * Analyse one PUBLICLY submitted ticket, after it is committed and the customer has been
 * acknowledged.
 *
 * Dispatched from exactly one line of exactly one method —
 * ClientPortalController::publicSubmitStore(). There is no Ticket observer and no event,
 * deliberately: an observer would fire for the REST API, agent-created tickets, the Jude
 * connector, seeders, factories and every import row, with nothing able to tell them
 * apart. The guarantee is structural and greppable, and a test asserts the dispatch
 * string appears in exactly one file in `app/`.
 *
 * **No SerializesModels.** Primitive ids, plus one explicit
 * `withoutGlobalScopes()->where('tenant_id', …)` lookup. SerializesModels re-resolves the
 * model on unserialize through the GLOBAL SCOPE, and TenantScope applies no predicate at
 * all in a worker with no session — so it would resolve by raw id with no tenant
 * predicate at all. Ids make the predicate visible and testable.
 *
 * **Every gate is re-evaluated here, at run time, against an explicit Tenant.** A
 * workspace that switched AI off between submission and the worker picking this up has
 * withdrawn consent; honouring the queue-time answer would spend their money against a
 * flag that now reads off. A plan downgrade is the same question. The answer in every
 * case is a silent return: no row, no retry. A "this workspace has the feature switched
 * off" row per public ticket would dwarf the real data in the table the pilot reads.
 */
class AnalyzePublicTicketJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * One retry, not two. Every AI failure mode is handled terminally inside handle(), so
     * the only thing a further attempt can fix is an infrastructure blip between dispatch
     * and the first run — a dead DB connection, a worker killed mid-job. More attempts
     * only buy duplicate risk.
     */
    public int $tries = 2;

    public int $backoff = 60;

    public int $timeout = 120;

    /**
     * Collapses a double dispatch (a double-submitted form, a retried POST) at the queue
     * level. A CONVENIENCE, not the correctness guarantee — that is the unique index on
     * `ai_ticket_analyses.auto_for_ticket_id`, because this lock is cache-backed and two
     * workers can still race past alreadyAutoAnalysed().
     */
    public int $uniqueFor = 900;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $ticketId,
    ) {
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return 'analyze-public-ticket:'.$this->ticketId;
    }

    /**
     * An analysis arriving an hour after submission has no triage value — the agent has
     * already opened the ticket.
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
        // SoftDeletingScope along with TenantScope, so without it a ticket soft-deleted
        // between dispatch and this run would still be analysed and billed for.
        $ticket = Ticket::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->find($this->ticketId);

        if ($ticket === null) {
            // Deleted between dispatch and this run.
            return;
        }

        // Defence in depth, and the one check that is about the ticket rather than the
        // workspace: a ticket whose `department_id` points at ANOTHER workspace's
        // department has no honest analysis to produce, because the department is what
        // selects the categories, the engineers and the AI agents. This state should be
        // impossible once the portal's tenant-scoped validation rules are in place
        // (`fix/portal-submit-unscoped-fks`, a hard merge-order requirement for this
        // branch), but the trigger is an UNAUTHENTICATED POST, so it is refused here as
        // well as failing closed inside the service. No row, no retry, no spend — the
        // answer will not change.
        if (! $analyzer->departmentIsOwnedByTicketTenant($ticket)) {
            Log::warning('Automatic ticket analysis refused: the ticket\'s department belongs to another workspace.', [
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
        // the day the ordering changes. Asking the tenant directly is provable — a
        // mutation to either session-reading form reds a test.
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
        // unset it hands the next job — possibly another tenant's — this workspace. That
        // is the TenantMailService::configure() class of bug, and the repo's precedent for
        // the fix is AutoClosePendingTickets: capture, then restore in a `finally`.
        //
        // The cap is inside this block rather than above it because
        // TenantTime::dayRange() resolves the timezone through the session — the design's
        // first option, taken so the four gates above stay session-free and provable.
        $previousTenantId = session('current_tenant_id');
        session(['current_tenant_id' => $tenant->id]);

        try {
            if ($this->overDailyCap($tenant)) {
                // Logged once per occurrence so "why did this ticket not get analysed" is
                // answerable without a code read.
                Log::info('Automatic ticket analysis skipped: daily cap reached.', [
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

            $analyzer->analyze($ticket, null, AiTicketAnalysis::TRIGGER_AUTO);
        } catch (OpenAiException $e) {
            // Includes a tripped circuit breaker, raised before any HTTP. Caught, NOT
            // allowed to propagate: letting the queue retry a breaker trip is exactly
            // "one outage kills the workspace", and the breaker exists to fail fast.
            report($e);
        } finally {
            // Writing null back is correct — it returns the process to "no workspace",
            // which is what TenantScope and AppSetting::get() expect outside a request.
            session(['current_tenant_id' => $previousTenantId]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Automatic ticket analysis failed.', [
            'tenant_id' => $this->tenantId,
            'ticket_id' => $this->ticketId,
            'exception' => $exception?->getMessage(),
        ]);
    }

    /**
     * One workspace setting, read against the tenant rather than the session.
     *
     * AppSetting::get() returns the DEFAULT when no session tenant is set, so in a worker
     * it would read every opt-in as OFF and this job would never run — the permissive-
     * looking failure that is actually silent. The shape is AiChatController::aiSetting()'s.
     *
     * A private method, not a new shared concern: a third place this question is asked
     * would be one more thing to keep in agreement.
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
     * Counted from `ai_ticket_analyses` itself — the rows are already written, already
     * tenant-scoped, already carry `trigger` and already carry the cost, so a cache
     * counter would be a second number that can disagree with the billing record and is
     * lost on a flush (and under CACHE_STORE=database it is not even faster).
     *
     * `TenantTime::dayRange()`, never `whereDate('created_at', now()->toDateString())`:
     * that pattern evaluates the boundary on the SERVER's calendar, so a UTC+8 workspace's
     * cap would reset mid-afternoon. It is a live defect in the portal bot's own cap and
     * is deliberately not copied here.
     */
    private function overDailyCap(Tenant $tenant): bool
    {
        [$from, $to] = TenantTime::dayRange();

        $count = AiTicketAnalysis::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('trigger', AiTicketAnalysis::TRIGGER_AUTO)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        return $count >= (int) config('ai.analyze.daily_cap', 100);
    }
}
