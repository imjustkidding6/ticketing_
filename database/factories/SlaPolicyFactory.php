<?php

namespace Database\Factories;

use App\Models\SlaPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SlaPolicy>
 */
class SlaPolicyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)).' SLA',
            'description' => fake()->sentence(),
            'client_tier' => null,
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'response_time_hours' => fake()->numberBetween(1, 8),
            'resolution_time_hours' => fake()->numberBetween(8, 72),
            'is_active' => true,
        ];
    }
}
