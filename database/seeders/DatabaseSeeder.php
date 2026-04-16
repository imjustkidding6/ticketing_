<?php

namespace Database\Seeders;

use App\Models\Client;
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

        $tenantAdmin = User::firstOrCreate(
            ['email' => 'tenant-admin@example.com'],
            [
                'name' => 'Tenant Admin',
                'password' => bcrypt('password'),
            ]
        );

        $manager = User::firstOrCreate(
            ['email' => 'manager@example.com'],
            [
                'name' => 'Manager User',
                'password' => bcrypt('password'),
            ]
        );

        $agent = User::firstOrCreate(
            ['email' => 'agent@example.com'],
            [
                'name' => 'Agent User',
                'password' => bcrypt('password'),
            ]
        );

        $testUser = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
            ]
        );

        $distributor = Distributor::firstOrCreate(
            ['email' => 'distributor@example.com'],
            ['name' => 'Demo Distributor']
        );

        $startPlan = Plan::where('slug', 'start')->firstOrFail();

        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo-company'],
            ['name' => 'Demo Company']
        );
        $tenant->loadMissing('license.plan');

        if (! $tenant->license) {
            $license = $distributor->generateLicense($startPlan, [
                'seats' => 10,
                'expires_at' => now()->addYear(),
            ]);
            $license->activate($tenant);
        } elseif ($tenant->license->plan?->slug !== $startPlan->slug) {
            $tenant->changePlan($startPlan);
        }

        $tenant->addUser($tenantAdmin, 'owner');
        $tenant->addUser($manager, 'member');
        $tenant->addUser($agent, 'member');
        $tenant->addUser($testUser, 'member');

        $roleService = new TenantRoleService;
        $roleService->setupDefaultRoles($tenant);
        $roleService->assignRole($tenantAdmin, 'admin', $tenant);
        $roleService->assignRole($manager, 'manager', $tenant);
        $roleService->assignRole($agent, 'agent', $tenant);

        $planTenants = [
            'start' => [
                'tenant' => ['name' => 'Start Plan Co', 'slug' => 'start-plan-co'],
                'owner' => ['name' => 'Start Tenant Admin', 'email' => 'start-admin@example.com'],
                'agent' => ['name' => 'Start Agent', 'email' => 'start-agent@example.com'],
                'client' => ['name' => 'Start Client', 'email' => 'start-client@example.com'],
                'seats' => 10,
                'client_tier' => Client::TIER_BASIC,
            ],
            'business' => [
                'tenant' => ['name' => 'Business Plan Co', 'slug' => 'business-plan-co'],
                'owner' => ['name' => 'Business Tenant Admin', 'email' => 'business-admin@example.com'],
                'agent' => ['name' => 'Business Agent', 'email' => 'business-agent@example.com'],
                'client' => ['name' => 'Business Client', 'email' => 'business-client@example.com'],
                'seats' => 25,
                'client_tier' => Client::TIER_PREMIUM,
            ],
            'enterprise' => [
                'tenant' => ['name' => 'Enterprise Plan Co', 'slug' => 'enterprise-plan-co'],
                'owner' => ['name' => 'Enterprise Tenant Admin', 'email' => 'enterprise-admin@example.com'],
                'agent' => ['name' => 'Enterprise Agent', 'email' => 'enterprise-agent@example.com'],
                'client' => ['name' => 'Enterprise Client', 'email' => 'enterprise-client@example.com'],
                'seats' => 100,
                'client_tier' => Client::TIER_ENTERPRISE,
            ],
        ];

        $plans = Plan::whereIn('slug', array_keys($planTenants))->get()->keyBy('slug');

        foreach ($planTenants as $planSlug => $config) {
            $plan = $plans->get($planSlug);
            if (! $plan) {
                continue;
            }

            $planTenant = Tenant::firstOrCreate(
                ['slug' => $config['tenant']['slug']],
                ['name' => $config['tenant']['name']]
            );
            $planTenant->loadMissing('license.plan');

            if (! $planTenant->license) {
                $planLicense = $distributor->generateLicense($plan, [
                    'seats' => $config['seats'],
                    'expires_at' => now()->addYear(),
                ]);
                $planLicense->activate($planTenant);
            } elseif ($planTenant->license->plan?->slug !== $planSlug) {
                $planTenant->changePlan($plan);
            }

            $owner = User::firstOrCreate(
                ['email' => $config['owner']['email']],
                [
                    'name' => $config['owner']['name'],
                    'password' => bcrypt('password'),
                ]
            );
            $agentUser = User::firstOrCreate(
                ['email' => $config['agent']['email']],
                [
                    'name' => $config['agent']['name'],
                    'password' => bcrypt('password'),
                ]
            );
            $clientUser = User::firstOrCreate(
                ['email' => $config['client']['email']],
                [
                    'name' => $config['client']['name'],
                    'password' => bcrypt('password'),
                ]
            );

            $planTenant->addUser($owner, 'owner');
            $planTenant->addUser($agentUser, 'member');

            $roleService->setupDefaultRoles($planTenant);
            $roleService->assignRole($owner, 'admin', $planTenant);
            $roleService->assignRole($agentUser, 'agent', $planTenant);

            Client::updateOrCreate(
                ['email' => $config['client']['email']],
                [
                    'tenant_id' => $planTenant->id,
                    'user_id' => $clientUser->id,
                    'name' => $config['client']['name'],
                    'contact_person' => $config['client']['name'],
                    'tier' => $config['client_tier'],
                    'status' => Client::STATUS_ACTIVE,
                ]
            );
        }
    }
}
