<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\AI\AiAgentCatalogueInstaller;
use Illuminate\Console\Command;

/**
 * Installs the default AI agent catalogue into one workspace, or all of them.
 *
 * The entry point that exists today; P3's settings screen calls the same service. Not
 * scheduled and not run at registration — see AiAgentCatalogueInstaller for why the
 * install is a deliberate action rather than something a signup or a toggle does.
 */
class InstallAiAgentCatalogue extends Command
{
    protected $signature = 'ai-agents:install-catalogue
                            {--tenant= : The tenant id to install for}
                            {--all : Install for every tenant}
                            {--dry-run : Report what would be created without writing}';

    protected $description = 'Install the default AI agents and skills for a workspace';

    public function handle(AiAgentCatalogueInstaller $installer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $tenantId = $this->option('tenant');

        if (! $tenantId && ! $this->option('all')) {
            $this->error('Pass --tenant=ID or --all. Installing for every workspace by accident is the mistake this guards against.');

            return self::FAILURE;
        }

        $tenants = Tenant::query()
            ->when($tenantId, fn ($q) => $q->where('id', $tenantId))
            ->orderBy('id')
            ->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants matched.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $summary = $installer->installFor($tenant, $dryRun);

            $this->line("  {$tenant->slug} (#{$tenant->id}): ".$summary->describe());

            if ($summary->missingDepartmentCodes !== []) {
                $this->warn(
                    '    No department for '.implode(', ', $summary->missingDepartmentCodes)
                    .' — those agents were created but left unassigned. Run departments:add-missing-defaults'
                    ." --tenant={$tenant->id}, then this command again."
                );
            }

            // Worth saying out loud: a re-run restores a catalogue link an administrator
            // removed, because "absent" cannot be told from "removed on purpose".
            if (! $summary->createdNothing() && ! $dryRun) {
                $this->comment('    Existing records were left exactly as they were.');
            }
        }

        return self::SUCCESS;
    }
}
