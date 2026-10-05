<?php

namespace Database\Factories;

use App\Models\AiAgent;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiAgent>
 */
class AiAgentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true).' Agent';

        return [
            'tenant_id' => Tenant::factory(),
            'ai_profile_id' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'role_type' => fake()->randomElement(AiAgent::ROLE_TYPES),
            // NULL, so effectiveSystemPrompt() falls back to DEFAULT_SYSTEM_PROMPT —
            // which is the state almost every real row is in.
            'system_prompt' => null,
            'is_active' => true,
            'is_default' => false,
            'priority' => 0,
        ];
    }

    /**
     * Pin the role type rather than leaving it random.
     */
    public function role(string $roleType): static
    {
        return $this->state(fn (array $attributes) => [
            'role_type' => $roleType,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
