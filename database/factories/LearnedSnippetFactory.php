<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LearnedSnippet;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearnedSnippet>
 */
class LearnedSnippetFactory extends Factory
{
    protected $model = LearnedSnippet::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'question' => $this->faker->sentence().'?',
            'answer' => $this->faker->paragraph(),
            'embedding' => [0.01, -0.02, 0.03, 0.04],
        ];
    }
}
