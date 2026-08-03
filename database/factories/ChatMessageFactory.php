<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_conversation_id' => ChatConversation::factory(),
            'role' => ChatMessage::ROLE_USER,
            'content' => $this->faker->paragraph(),
            'metadata' => null,
        ];
    }
}
