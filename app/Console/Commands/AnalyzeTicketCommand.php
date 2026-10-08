<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\AnalyzeInternalTicketJob;
use App\Models\AiTicketAnalysis;
use App\Models\Ticket;
use App\Services\AI\AnalyzeTicketService;
use App\Services\PlanService;
use Illuminate\Console\Command;

/**
 * Produce the intake analysis for one ticket, synchronously.
 *
 * Two jobs. It is the manual equivalent when no worker is running on the `ai` queue, and
 * it is the diagnostic for that situation: this command succeeding while the ticket page's
 * panel is still empty proves the worker is the problem, not the feature.
 *
 * A thin wrapper over AnalyzeInternalTicketJob::handle() rather than a second call into
 * AnalyzeTicketService, so it goes through EVERY gate and every tenant predicate the queued
 * run goes through and cannot become a bypass.
 */
class AnalyzeTicketCommand extends Command
{
    protected $signature = 'ai:analyze-ticket
                            {ticket : The ticket ID to analyse}
                            {--tenant= : The workspace the ticket belongs to (defaults to the ticket\'s own)}
                            {--user= : The engineer to attribute the spend to (defaults to the ticket\'s creator)}';

    protected $description = 'Run the intake AI analysis for one ticket synchronously, through every gate the queued job uses.';

    public function handle(AnalyzeTicketService $analyzer, PlanService $plans): int
    {
        $ticketId = (int) $this->argument('ticket');

        // Unscoped on purpose and then pinned: the console has no session, so the operator
        // must be able to name a ticket by id, and the tenant/creator are read from the
        // ticket's own columns rather than guessed. Every predicate that MATTERS is
        // re-applied inside handle() against the ids this method passes it.
        $ticket = Ticket::withoutGlobalScopes()->whereNull('deleted_at')->find($ticketId);

        if ($ticket === null) {
            $this->error("Ticket [{$ticketId}] was not found.");

            return self::FAILURE;
        }

        $tenantId = $this->option('tenant') === null ? (int) $ticket->tenant_id : (int) $this->option('tenant');
        $userId = $this->option('user') === null ? (int) $ticket->created_by : (int) $this->option('user');

        if ($userId === 0) {
            $this->error('No engineer to attribute this analysis to — pass --user=ID.');

            return self::FAILURE;
        }

        (new AnalyzeInternalTicketJob($tenantId, $ticketId, $userId))->handle($analyzer, $plans);

        $analysis = AiTicketAnalysis::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('ticket_id', $ticketId)
            ->latest('id')
            ->first();

        if ($analysis === null) {
            // The job refused: a withdrawn gate, the daily cap, a department or a creator
            // that is not this workspace's. The reason is in the log, by design — the job
            // writes no row and spends nothing rather than filling the pilot's table with
            // "this workspace has AI switched off".
            $this->warn('No analysis was produced. The job refused the run — check the log for the reason (gates, daily cap, or workspace membership).');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Analysis #%d — %s, trigger %s, estimated cost %s.',
            $analysis->getKey(),
            (string) $analysis->status,
            (string) $analysis->trigger,
            (string) $analysis->estimated_cost,
        ));

        return self::SUCCESS;
    }
}
