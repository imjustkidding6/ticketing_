<?php

namespace Database\Factories;

use App\Models\Distributor;
use App\Models\License;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\License>
 */
class LicenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'license_key' => License::generateKey(),
            'distributor_id' => Distributor::factory(),
            'plan_id' => Plan::factory(),
            'tenant_id' => null,
            'seats' => fake()->numberBetween(5, 50),
            'status' => License::STATUS_PENDING,
            'issued_at' => now(),
            'activated_at' => null,
            'expires_at' => now()->addYear(),
            'grace_days' => License::DEFAULT_GRACE_DAYS,
        ];
    }

    /**
     * Indicate that the license is pending activation.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => License::STATUS_PENDING,
            'activated_at' => null,
            'tenant_id' => null,
        ]);
    }

    /**
     * Indicate that the license is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => License::STATUS_ACTIVE,
            'activated_at' => now(),
        ]);
    }

    /**
     * Indicate that the license is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => License::STATUS_ACTIVE,
            'activated_at' => now()->subYear()->subMonth(),
            'expires_at' => now()->subDays(License::DEFAULT_GRACE_DAYS + 1),
        ]);
    }

    /**
     * Indicate that the license is in grace period.
     */
    public function inGracePeriod(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => License::STATUS_ACTIVE,
            'activated_at' => now()->subYear(),
            'expires_at' => now()->subDays(3),
            'grace_days' => License::DEFAULT_GRACE_DAYS,
        ]);
    }

    /**
     * Indicate that the license is revoked.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => License::STATUS_REVOKED,
        ]);
    }

    /**
     * Configure the license with a specific plan.
     */
    public function forPlan(Plan $plan): static
    {
        return $this->state(fn (array $attributes) => [
            'plan_id' => $plan->id,
            'seats' => $plan->max_users ?? 100,
        ]);
    }
}
