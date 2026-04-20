<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TicketTask>
 */
class TicketTaskFactory extends Factory
{
    protected $model = TicketTask::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'ticket_id' => Ticket::factory(),
            'description' => fake()->sentence(),
            'status' => 'pending',
        ];
    }

    /**
     * Set the task as completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => User::factory(),
        ]);
    }
}
