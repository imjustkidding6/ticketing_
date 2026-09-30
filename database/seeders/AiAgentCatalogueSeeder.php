<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Services\AI\AiAgentCatalogueInstaller;
use Illuminate\Database\Seeder;

/**
 * Installs the default AI agent catalogue for the demo workspace only.
 *
 * Deliberately not a loop over every tenant: the install is an on-demand action, and
 * `db:seed` reaching into six live workspaces to write several hundred rows is exactly
 * what `ai-agents:install-catalogue` exists to keep deliberate. The demo workspace is
 * fixture data, so local development gets the catalogue and criterion 33 ("db:seed twice,
 * identical counts") is something anyone can actually run.
 */
class AiAgentCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'demo-company')->first();

        if (! $tenant) {
            return;
        }

        app(AiAgentCatalogueInstaller::class)->installFor($tenant);
    }
}
