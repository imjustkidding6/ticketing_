<?php

namespace Database\Factories;

use App\Models\AiUsageLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiUsageLog>
 */
class AiUsageLogFactory extends Factory
{
    protected $model = AiUsageLog::class;

    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'user_id' => null,
            'conversation_id' => null,
            'chat_message_id' => null,
            'model' => 'gpt-5',
            'prompt_tokens' => $this->faker->numberBetween(100, 1000),
            'completion_tokens' => $this->faker->numberBetween(50, 500),
            'total_tokens' => 500,
            'estimated_cost' => 0.0015,
            'latency_ms' => $this->faker->numberBetween(100, 1000),
            'response_status' => 'success',
            'feature' => 'chat',
        ];
    }
}
