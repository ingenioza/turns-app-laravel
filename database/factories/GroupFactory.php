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
            'name' => $this->faker->company.' Group',
            'description' => $this->faker->sentence(),
            'creator_id' => \App\Models\User::factory(),
            'status' => $this->faker->randomElement(['active', 'inactive', 'archived']),
            'invite_code' => strtoupper($this->faker->bothify('????????')), // Generate 8 char code
            'settings' => [
                'turn_duration' => $this->faker->numberBetween(5, 60),
                'notifications_enabled' => $this->faker->boolean(),
                'auto_advance' => $this->faker->boolean(),
            ],
            'last_turn_at' => $this->faker->optional()->dateTimeThisMonth(),
        ];
    }
}
