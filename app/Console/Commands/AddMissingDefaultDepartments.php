<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\Tenant;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Console\Command;

/**
 * Adds the default departments a workspace is missing — in practice Operations and
 * Pre-Sales, added to the defaults on 2026-09-30.
 *
 * Opt-in and per workspace, because backfilling a department into a live workspace is a
 * decision somebody makes, not something `db:seed` does by accident. DepartmentSeeder::run()
 * skips the new codes for that reason.
 *
 * It deliberately does **not** call DepartmentSeeder::seedForTenant(): that helper
 * updateOrCreates all nine defaults, so it would overwrite a workspace that renamed
 * "Others" or recoloured "Sales". firstOrCreate on (tenant_id, code) instead, appended
 * after the existing sort order, so nothing already there is rewritten.
 */
class AddMissingDefaultDepartments extends Command
{
    protected $signature = 'departments:add-missing-defaults
                            {--tenant= : The tenant id to add for}
                            {--all : Add for every tenant}
                            {--dry-run : Report what would be created without writing}';

    protected $description = 'Add any default departments a workspace does not yet have';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $tenantId = $this->option('tenant');

        if (! $tenantId && ! $this->option('all')) {
            $this->error('Pass --tenant=ID or --all. Adding a department to every workspace by accident is the mistake this guards against.');

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
            $existing = Department::withoutGlobalScopes()->where('tenant_id', $tenant->id)
                ->pluck('code')
                ->all();

            $nextSortOrder = (int) Department::withoutGlobalScopes()->where('tenant_id', $tenant->id)
                ->max('sort_order') + 1;

            $added = [];

            foreach (DepartmentSeeder::DEFAULTS as $default) {
                if (in_array($default['code'], $existing, true)) {
                    continue;
                }

                $added[] = $default['code'];

                if ($dryRun) {
                    continue;
                }

                Department::withoutGlobalScopes()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'code' => $default['code']],
                    [
                        'name' => $default['name'],
                        'description' => $default['description'],
                        'color' => $default['color'],
                        'is_active' => true,
                        'is_default' => true,
                        // Appended rather than slotted into the default order: renumbering
                        // the departments a workspace already sorted is a change nobody asked for.
                        'sort_order' => $nextSortOrder++,
                    ],
                );
            }

            $verb = $dryRun ? 'Would add' : 'Added';
            $this->line("  {$tenant->slug} (#{$tenant->id}): {$verb} ".(
                $added === [] ? 'nothing — all defaults present.' : implode(', ', $added).'.'
            ));
        }

        return self::SUCCESS;
    }
}
