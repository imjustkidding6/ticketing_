<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BugReport;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BugReport>
 */
class BugReportFactory extends Factory
{
    protected $model = BugReport::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'steps_to_reproduce' => $this->faker->paragraph(),
            'area' => 'tickets',
            'severity' => BugReport::SEVERITY_MEDIUM,
            'status' => BugReport::STATUS_NEW,
        ];
    }
}
