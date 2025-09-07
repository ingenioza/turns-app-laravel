<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Turn>
 */
class TurnFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = $this->faker->dateTimeThisMonth();
        $endedAt = $this->faker->optional(0.7)->dateTimeBetween($startedAt, 'now');

        return [
            'group_id' => \App\Models\Group::factory(),
            'user_id' => \App\Models\User::factory(),
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'status' => $endedAt ?
                $this->faker->randomElement(['completed', 'skipped']) :
                $this->faker->randomElement(['active', 'expired']),
            'duration_seconds' => $endedAt ?
                \Carbon\Carbon::parse($startedAt)->diffInSeconds(\Carbon\Carbon::parse($endedAt)) :
                null,
            'notes' => $this->faker->optional(0.3)->sentence(),
            'metadata' => [
                'device' => $this->faker->randomElement(['mobile', 'web']),
                'location' => $this->faker->optional()->city(),
            ],
        ];
    }
}
