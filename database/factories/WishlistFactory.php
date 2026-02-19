<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Wishlist>
 */
class WishlistFactory extends Factory {
    public function definition(): array {
        return [
            'user_id' => User::factory(),
            'family_id' => null,
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'type' => fake()->randomElement(['CHRISTMAS', 'BIRTHDAY', 'WEDDING', 'GENERIC']),
            'privacy' => 'PRIVATE',
            'is_archived' => false,
        ];
    }
}
