<?php

namespace App\Console\Commands;

use App\Enums\PlanFeature;
use App\Models\Tenant;
use App\Notifications\SlaBreachWarningNotification;
use App\Services\PlanService;
use App\Services\SlaService;
use App\Services\TenantMailService;
use Illuminate\Console\Command;

class SendSlaBreachWarnings extends Command
{
    protected $signature = 'sla:send-breach-warnings';

    protected $description = 'Send SLA breach warning notifications to assigned agents.';

    public function __construct(
        private SlaService $slaService,
        private PlanService $planService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sent = 0;
        $seen = 0;

        // Chunked: this runs every 15 minutes over every tenant, and loading the
        // whole overdue set into memory at once does not survive real volume.
        // Tenant feature lookups are memoised per run rather than per ticket.
        $tenants = [];

        $this->slaService->breachWarningQuery()
            ->chunkById(200, function ($tickets) use (&$sent, &$seen, &$tenants) {
                foreach ($tickets as $ticket) {
                    $seen++;

                    if (! array_key_exists($ticket->tenant_id, $tenants)) {
                        $tenant = Tenant::find($ticket->tenant_id);
                        $tenants[$ticket->tenant_id] = $tenant
                            && $this->planService->tenantHasFeature($tenant, PlanFeature::EmailNotifications);
                    }

                    if (! $tenants[$ticket->tenant_id] || ! $ticket->assignee) {
                        continue;
                    }

                    $breachType = $ticket->isResponseOverdue() ? 'response' : 'resolution';

                    // This loop crosses tenants, so the mailer must be configured
                    // for each one and restored afterwards — otherwise every tenant
                    // after the first sends through the previous tenant's SMTP.
                    TenantMailService::usingTenant(
                        $ticket->tenant_id,
                        fn () => $ticket->assignee->notify(new SlaBreachWarningNotification($ticket, $breachType)),
                    );

                    $ticket->update(['sla_breach_notified_at' => now()]);

                    $sent++;
                }
            });

        $this->info("Sent {$sent} SLA breach warning(s) out of {$seen} overdue ticket(s).");

        return self::SUCCESS;
    }
}
