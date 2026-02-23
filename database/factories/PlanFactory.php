<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'max_users' => fake()->numberBetween(5, 50),
            'max_tickets_per_month' => fake()->numberBetween(100, 1000),
            'is_active' => true,
        ];
    }

    /**
     * Configure the Start plan.
     */
    public function start(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Start',
            'slug' => 'start',
            'description' => 'Perfect for small teams getting started.',
            'max_users' => 5,
            'max_tickets_per_month' => 100,
        ]);
    }

    /**
     * Configure the Business plan.
     */
    public function business(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Business',
            'slug' => 'business',
            'description' => 'For growing teams with increased needs.',
            'max_users' => 25,
            'max_tickets_per_month' => 500,
        ]);
    }

    /**
     * Configure the Enterprise plan.
     */
    public function enterprise(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'description' => 'Unlimited access for large organizations.',
            'max_users' => null,
            'max_tickets_per_month' => null,
        ]);
    }

    /**
     * Indicate that the plan is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
