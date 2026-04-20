<?php

namespace Database\Seeders;

use App\Models\Distributor;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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
        ]);

        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => bcrypt('password'),
                'is_admin' => true,
            ]
        );

        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
            ]
        );

        $distributor = Distributor::factory()->create([
            'name' => 'Demo Distributor',
            'email' => 'distributor@example.com',
        ]);

        $startPlan = Plan::where('slug', 'start')->first();

        $license = $distributor->generateLicense($startPlan, [
            'seats' => 10,
            'expires_at' => now()->addYear(),
        ]);

        $tenant = Tenant::factory()->create([
            'name' => 'Demo Company',
        ]);

        $license->activate($tenant);

        $tenant->addUser($user, 'owner');

        $roleService = new TenantRoleService;
        $roleService->setupDefaultRoles($tenant);
        $roleService->assignRole($user, 'admin', $tenant);

        DepartmentSeeder::seedForTenant($tenant);
    }
}
