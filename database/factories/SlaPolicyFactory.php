<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SlaPolicy>
 */
class SlaPolicyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' SLA',
            'description' => fake()->optional()->sentence(),
            'client_tier' => fake()->optional()->randomElement(['basic', 'premium', 'enterprise']),
            'priority' => fake()->optional()->randomElement(['low', 'medium', 'high', 'critical']),
            'response_time_hours' => fake()->randomElement([1, 2, 4, 8, 24]),
            'resolution_time_hours' => fake()->randomElement([4, 8, 24, 48, 72]),
            'is_active' => true,
        ];
    }
}
