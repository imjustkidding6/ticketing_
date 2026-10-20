<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Default departments seeded for every tenant, in the requirement's §1 order.
     *
     * `sort_order` is stated per entry rather than taken from the array index: Operations
     * and Pre-Sales were added on 2026-09-30, and deriving the order from the position
     * would renumber every department the six live workspaces already have the moment
     * anyone ran `db:seed`. The two new codes therefore sort last everywhere — the same
     * place `departments:add-missing-defaults` appends them, so a workspace created
     * before the change and one created after end up identical.
     *
     * @var list<array{name: string, code: string, description: string, color: string, sort_order: int}>
     */
    public const DEFAULTS = [
        [
            'name' => 'Human Resource',
            'code' => 'HR',
            'description' => 'Human resource related requests.',
            'color' => '#6366f1',
            'sort_order' => 0,
        ],
        [
            'name' => 'Operations',
            'code' => 'OPS',
            'description' => 'Operational, process and service-delivery requests.',
            'color' => '#0ea5e9',
            'sort_order' => 7,
        ],
        [
            'name' => 'Pre-Sales',
            'code' => 'PRESALES',
            'description' => 'Client requirements, solution design and estimates before a sale.',
            'color' => '#a855f7',
            'sort_order' => 8,
        ],
        [
            'name' => 'Procurement',
            'code' => 'PROC',
            'description' => 'Procurement and purchasing requests.',
            'color' => '#8b5cf6',
            'sort_order' => 1,
        ],
        [
            'name' => 'Technical Software',
            'code' => 'SOFTWARE',
            'description' => 'Software-related technical issues.',
            'color' => '#3b82f6',
            'sort_order' => 2,
        ],
        [
            'name' => 'Technical Hardware',
            'code' => 'HARDWARE',
            'description' => 'Hardware-related technical issues.',
            'color' => '#ef4444',
            'sort_order' => 3,
        ],
        [
            'name' => 'Sales',
            'code' => 'SALES',
            'description' => 'Sales inquiries and support.',
            'color' => '#10b981',
            'sort_order' => 4,
        ],
        [
            'name' => 'Customer Service',
            'code' => 'CS',
            'description' => 'Customer service and general support.',
            'color' => '#f59e0b',
            'sort_order' => 5,
        ],
        [
            'name' => 'Others',
            'code' => 'OTHERS',
            'description' => 'Other requests that do not fall under specific departments.',
            'color' => '#6b7280',
            'sort_order' => 6,
        ],
    ];

    /**
     * The codes added to DEFAULTS on 2026-09-30, long after six workspaces went live.
     *
     * run() skips them so `db:seed` can never introduce a department into a workspace
     * that did not ask for one. `departments:add-missing-defaults` is the opt-in action.
     *
     * @var list<string>
     */
    public const ADDED_2026_09 = ['OPS', 'PRESALES'];

    /**
     * Seed default departments for all existing tenants.
     */
    public function run(): void
    {
        Tenant::all()->each(function (Tenant $tenant) {
            static::seedForTenant($tenant, self::ADDED_2026_09);
        });
    }

    /**
     * Seed default departments for a specific tenant.
     *
     * Registration calls this with no skip list, so a new workspace gets all nine.
     *
     * @param  list<string>  $skipCodes  codes to leave alone entirely
     */
    public static function seedForTenant(Tenant $tenant, array $skipCodes = []): void
    {
        foreach (self::DEFAULTS as $dept) {
            if (in_array($dept['code'], $skipCodes, true)) {
                continue;
            }

            Department::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'code' => $dept['code'],
                ],
                [
                    'name' => $dept['name'],
                    'description' => $dept['description'],
                    'color' => $dept['color'],
                    'is_active' => true,
                    'is_default' => true,
                    'sort_order' => $dept['sort_order'],
                ]
                // NO DETAIL-FIELD FLAG. P8/P9's `is_technical` seeding is gone with the
                // gate: which detail questions a ticket is asked is now a property of its
                // CATEGORY (TicketCategory::detailFlagsFor()), and no seeder creates
                // categories — a workspace starts with none and configures its own. The two
                // flags this seeder used to set on SOFTWARE and HARDWARE are not carried
                // anywhere; they are lost, and a migration cannot backfill them.
            );
        }
    }
}
