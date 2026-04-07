<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CannedResponse>
 */
class CannedResponseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement([
                'Greeting', 'Closing', 'Escalation Notice', 'Password Reset',
                'Billing Inquiry', 'Thank You', 'Follow Up', 'Apology',
            ]),
            'category' => fake()->randomElement(['General', 'Billing', 'Technical', 'Support']),
            'content' => fake()->paragraph(),
            'shortcut' => null,
            'sort_order' => 0,
            'created_by' => User::factory(),
        ];
    }
}
