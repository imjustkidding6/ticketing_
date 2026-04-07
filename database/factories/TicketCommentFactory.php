<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TicketComment>
 */
class TicketCommentFactory extends Factory
{
    protected $model = TicketComment::class;

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
            'user_id' => User::factory(),
            'content' => fake()->paragraphs(2, true),
            'type' => 'internal',
            'is_public' => false,
        ];
    }

    /**
     * Set the comment as public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'public',
            'is_public' => true,
        ]);
    }

    /**
     * Set the comment as a client reply.
     */
    public function clientReply(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'client_reply',
            'is_public' => true,
        ]);
    }
}
