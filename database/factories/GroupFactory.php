<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Group>
 */
class GroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Test Group '.rand(100, 999),
            'description' => 'Test group description',
            'creator_id' => \App\Models\User::factory(),
            'status' => 'active',
            'invite_code' => 'TEST'.rand(1000, 9999),
            'settings' => [
                'turn_duration' => 30,
                'notifications_enabled' => true,
                'auto_advance' => false,
            ],
            'last_turn_at' => null,
        ];
    }
}
