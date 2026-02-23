<?php

namespace Database\Factories;

use App\Models\Distributor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Distributor>
 */
class DistributorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'email' => fake()->companyEmail(),
            'contact_person' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'is_active' => true,
            'api_key' => Distributor::generateApiKey(),
        ];
    }

    /**
     * Indicate that the distributor is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the distributor should have an API key.
     */
    public function withApiKey(): static
    {
        return $this->state(fn (array $attributes) => [
            'api_key' => Distributor::generateApiKey(),
        ]);
    }

    /**
     * Indicate that the distributor should not have an API key.
     */
    public function withoutApiKey(): static
    {
        return $this->state(fn (array $attributes) => [
            'api_key' => null,
        ]);
    }
}
