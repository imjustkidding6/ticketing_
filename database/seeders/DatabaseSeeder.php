<?php

namespace Database\Seeders;

use App\Models\Distributor;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            RoleAndPermissionSeeder::class,
            AdminUserSeeder::class,
        ]);

        $admin = User::withTrashed()->where('email', 'admin@example.com')->first();
        if (! $admin) {
            $admin = User::updateOrCreate(
                ['email' => 'admin@example.com'],
                [
                    'name' => 'Administrator',
                    'password' => Hash::make('password'),
                    'is_admin' => true,
                    'email_verified_at' => now(),
                ]
            );
        } elseif ($admin->trashed()) {
            $admin->restore();
        }

        $user = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $distributor = Distributor::firstOrCreate(
            ['email' => 'distributor@example.com'],
            [
                'name' => 'Demo Distributor',
                'slug' => 'demo-distributor',
                'is_active' => true,
            ]
        );

        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo-company'],
            [
                'name' => 'Demo Company',
                'is_active' => true,
            ]
        );

        $startPlan = Plan::where('slug', 'start')->first();

        if ($distributor && $tenant && $startPlan) {
            $license = $tenant->license;

            if (! $license) {
                $license = $distributor->licenses()
                    ->where('plan_id', $startPlan->id)
                    ->whereNull('tenant_id')
                    ->first();

                if (! $license) {
                    $license = $distributor->generateLicense($startPlan, [
                        'seats' => 10,
                        'expires_at' => now()->addYear(),
                    ]);
                }

                $license->activate($tenant);
            }

            $tenant->addUser($user, 'owner');
            $tenant->addUser($admin, 'owner');

            $roleService = new TenantRoleService;
            $roleService->setupDefaultRoles($tenant);
            $roleService->assignRole($user, 'admin', $tenant);
            $roleService->assignRole($admin, 'admin', $tenant);

            DepartmentSeeder::seedForTenant($tenant);
        }
    }

    /**
     * Remove duplicate demo records, keeping only the primary record.
     */
    private function cleanupDuplicateDemoData(): void
    {
        // 1. Cleanup duplicate Demo Distributors
        $distributors = Distributor::where('name', 'Demo Distributor')
            ->orWhere('email', 'distributor@example.com')
            ->orderBy('id', 'asc')
            ->get();

        if ($distributors->count() > 1) {
            $primaryDistributor = $distributors->first();
            $duplicateDistributorIds = $distributors->slice(1)->pluck('id');

            License::whereIn('distributor_id', $duplicateDistributorIds)
                ->update(['distributor_id' => $primaryDistributor->id]);

            Distributor::whereIn('id', $duplicateDistributorIds)->delete();
        }

        // 2. Cleanup duplicate Demo Company Tenants
        $tenants = Tenant::where('name', 'Demo Company')
            ->orWhere('slug', 'demo-company')
            ->orderBy('id', 'asc')
            ->get();

        if ($tenants->count() > 0) {
            $primaryTenant = $tenants->first();

            if ($tenants->count() > 1) {
                $duplicateTenantIds = $tenants->slice(1)->pluck('id');

                DB::table('tenant_user')->whereIn('tenant_id', $duplicateTenantIds)->delete();
                DB::table('departments')->whereIn('tenant_id', $duplicateTenantIds)->delete();
                DB::table('tickets')->whereIn('tenant_id', $duplicateTenantIds)->update(['tenant_id' => $primaryTenant->id]);

                License::whereIn('tenant_id', $duplicateTenantIds)->delete();
                Tenant::whereIn('id', $duplicateTenantIds)->delete();
            }

            $primaryTenant->update([
                'name' => 'Demo Company',
                'slug' => 'demo-company',
            ]);
        }

        // 3. Cleanup duplicate licenses on the primary demo tenant
        $primaryTenant = Tenant::where('name', 'Demo Company')->orWhere('slug', 'demo-company')->first();
        if ($primaryTenant) {
            $licenses = License::where('tenant_id', $primaryTenant->id)->orderBy('id', 'asc')->get();
            if ($licenses->count() > 1) {
                $duplicateLicenseIds = $licenses->slice(1)->pluck('id');
                License::whereIn('id', $duplicateLicenseIds)->delete();
            }
        }
    }
}
