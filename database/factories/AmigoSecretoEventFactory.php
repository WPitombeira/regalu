<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AmigoSecretoEvent>
 */
class AmigoSecretoEventFactory extends Factory {
    public function definition(): array {
        return [
            'organizer_id' => User::factory(),
            'family_id' => null,
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'event_code' => Str::upper(Str::random(12)),
            'event_type' => fake()->randomElement(['CHRISTMAS', 'BIRTHDAY', 'WEDDING', 'CASUAL']),
            'budget_min' => fake()->optional()->randomFloat(2, 20, 50),
            'budget_max' => fake()->optional()->randomFloat(2, 50, 200),
            'event_date' => fake()->optional()->dateTimeBetween('+1 week', '+3 months'),
            'reveal_date' => null,
            'status' => 'PLANNING',
            'is_archived' => false,
        ];
    }
}
