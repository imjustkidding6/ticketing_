<?php

namespace Database\Seeders;

use App\Enums\PlanFeature;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'start',
                'description' => 'Perfect for small teams getting started.',
                'max_users' => 5,
                'max_tickets_per_month' => 100,
                'features' => PlanFeature::forPlan('start'),
                'is_active' => true,
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'For growing teams with increased needs.',
                'max_users' => 25,
                'max_tickets_per_month' => 500,
                'features' => PlanFeature::forPlan('business'),
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Unlimited access for large organizations.',
                'max_users' => null,
                'max_tickets_per_month' => null,
                'features' => PlanFeature::forPlan('enterprise'),
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
