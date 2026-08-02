<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Throwable;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Restore soft-deleted administrator account if soft-deleted
        $admin = User::withTrashed()->where('email', 'admin@example.com')->first();
        if ($admin && $admin->trashed()) {
            $admin->restore();
        }

        // 2. Guaranteed idempotent creation / update of the administrator account
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        // 3. Fail-safe role assignment within tenant context if a tenant exists
        try {
            $tenant = Tenant::first();
            if ($tenant) {
                $roleService = new TenantRoleService;
                $roleService->setupDefaultRoles($tenant);
                $roleService->assignRole($admin, 'admin', $tenant);
            }
        } catch (Throwable $e) {
            // Fail-safe: Role assignment failure must never abort or roll back admin account creation
        }
    }
}
