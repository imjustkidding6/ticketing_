<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Default departments seeded for every tenant.
     *
     * @var list<array{name: string, code: string, description: string, color: string}>
     */
    public const DEFAULTS = [
        [
            'name' => 'Human Resource',
            'code' => 'HR',
            'description' => 'Human resource related requests.',
            'color' => '#6366f1',
        ],
        [
            'name' => 'Procurement',
            'code' => 'PROC',
            'description' => 'Procurement and purchasing requests.',
            'color' => '#8b5cf6',
        ],
        [
            'name' => 'Technical Software',
            'code' => 'SOFTWARE',
            'description' => 'Software-related technical issues.',
            'color' => '#3b82f6',
        ],
        [
            'name' => 'Technical Hardware',
            'code' => 'HARDWARE',
            'description' => 'Hardware-related technical issues.',
            'color' => '#ef4444',
        ],
        [
            'name' => 'Sales',
            'code' => 'SALES',
            'description' => 'Sales inquiries and support.',
            'color' => '#10b981',
        ],
        [
            'name' => 'Customer Service',
            'code' => 'CS',
            'description' => 'Customer service and general support.',
            'color' => '#f59e0b',
        ],
        [
            'name' => 'Others',
            'code' => 'OTHERS',
            'description' => 'Other requests that do not fall under specific departments.',
            'color' => '#6b7280',
        ],
    ];

    /**
     * Seed default departments for all existing tenants.
     */
    public function run(): void
    {
        Tenant::all()->each(function (Tenant $tenant) {
            static::seedForTenant($tenant);
        });
    }

    /**
     * Seed default departments for a specific tenant.
     */
    public static function seedForTenant(Tenant $tenant): void
    {
        foreach (self::DEFAULTS as $index => $dept) {
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
                    'sort_order' => $index,
                ]
            );
        }
    }
}
