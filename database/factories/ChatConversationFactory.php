<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ChatConversation;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatConversation>
 */
class ChatConversationFactory extends Factory
{
    protected $model = ChatConversation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'channel' => ChatConversation::CHANNEL_PORTAL,
            'title' => $this->faker->sentence(3),
            'status' => ChatConversation::STATUS_ACTIVE,
            'last_message_at' => now(),
        ];
    }
}
