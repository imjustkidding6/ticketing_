<?php

namespace App\Console\Commands;

use App\Enums\PlanFeature;
use App\Models\Tenant;
use App\Notifications\SlaBreachWarningNotification;
use App\Services\PlanService;
use App\Services\SlaService;
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
        $tickets = $this->slaService->getTicketsNeedingBreachWarning();
        $sent = 0;

        foreach ($tickets as $ticket) {
            $tenant = Tenant::find($ticket->tenant_id);

            if (! $tenant || ! $this->planService->tenantHasFeature($tenant, PlanFeature::EmailNotifications)) {
                continue;
            }

            if (! $ticket->assignee) {
                continue;
            }

            $breachType = $ticket->isResponseOverdue() ? 'response' : 'resolution';

            $ticket->assignee->notify(new SlaBreachWarningNotification($ticket, $breachType));

            $ticket->update(['sla_breach_notified_at' => now()]);

            $sent++;
        }

        $this->info("Sent {$sent} SLA breach warning(s) out of {$tickets->count()} overdue ticket(s).");

        return self::SUCCESS;
    }
}
