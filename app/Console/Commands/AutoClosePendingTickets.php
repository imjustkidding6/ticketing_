<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Services\TenantMailService;
use App\Services\TicketService;
use Illuminate\Console\Command;

/**
 * Closes tickets that have sat waiting on the customer for longer than the
 * tenant's threshold — the grace window a "pending" state is worth having for.
 *
 * Off by default: auto_close_pending_days is 0 until a tenant sets it, so no
 * workspace starts closing its customers' tickets because of a deploy.
 */
class AutoClosePendingTickets extends Command
{
    protected $signature = 'tickets:auto-close-pending {--tenant= : Limit the sweep to one tenant id} {--dry-run : Report what would close without closing it}';

    protected $description = 'Close pending tickets that have been waiting on the customer past the tenant threshold.';

    public function __construct(private TicketService $ticketService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $closed = 0;
        $skipped = 0;

        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        $originalTenantId = session('current_tenant_id');

        try {
            foreach ($tenants as $tenant) {
                // AppSetting::get() reads the session, and this sweep has none — so the
                // tenant's threshold is read explicitly, the same way the query below is
                // scoped explicitly.
                $days = (int) (AppSetting::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('key', 'auto_close_pending_days')
                    ->value('value') ?? 0);

                if ($days <= 0) {
                    continue;
                }

                // "Untouched for N days" is a duration, not a calendar boundary, so
                // wall-clock arithmetic is correct here and TenantTime is not needed.
                $threshold = now()->subDays($days);

                // This sweep runs outside any tenant session, so the global scope is
                // not applied — the tenant_id filter is what keeps it isolated.
                $query = Ticket::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->pending()
                    ->notMerged()
                    ->notSpam()
                    ->whereNotNull('pending_since')
                    ->where('pending_since', '<=', $threshold);

                // Settings consulted deeper in TicketService (auto_generate_on_close and
                // friends) read the session, so point it at this tenant for the pass —
                // otherwise the sweep silently applies another workspace's defaults.
                session(['current_tenant_id' => $tenant->id]);

                $query->chunkById(100, function ($tickets) use ($tenant, $dryRun, &$closed, &$skipped) {
                    foreach ($tickets as $ticket) {
                        if ($dryRun) {
                            $this->line("Would close {$ticket->ticket_number} (tenant {$tenant->id}).");
                            $closed++;

                            continue;
                        }

                        try {
                            // Closing through the service ends the pause, accumulates the
                            // hold, stamps closed_at/first_closed_at and writes history.
                            // Notifications go out inside it, so the mailer must belong to
                            // this tenant or the notice leaves through the previous one's SMTP.
                            TenantMailService::usingTenant(
                                $tenant->id,
                                fn () => $this->ticketService->changeStatus($ticket, 'closed'),
                            );

                            $closed++;
                        } catch (\InvalidArgumentException $e) {
                            // A pending ticket with open tasks is refused by changeStatus().
                            // That is one ticket's problem, not the sweep's — skip it and
                            // keep going, or a single stuck ticket freezes every tenant.
                            $skipped++;

                            $this->warn("Skipped {$ticket->ticket_number}: {$e->getMessage()}");
                        }
                    }
                });
            }
        } finally {
            // The session is global runtime state; leaving it pointed at the last
            // tenant swept would hand the next caller someone else's workspace.
            session(['current_tenant_id' => $originalTenantId]);
        }

        $this->info("Auto-closed {$closed} pending ticket(s); skipped {$skipped}.");

        return self::SUCCESS;
    }
}
