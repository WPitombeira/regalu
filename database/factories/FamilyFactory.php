<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Family>
 */
class FamilyFactory extends Factory {
    public function definition(): array {
        return [
            'name' => fake()->lastName() . ' Family',
            'description' => fake()->optional()->sentence(),
            'invite_code' => Str::upper(Str::random(12)),
            'creator_id' => User::factory(),
            'is_archived' => false,
        ];
    }
}
