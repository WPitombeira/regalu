<?php

namespace Database\Factories;

use App\Models\Wishlist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WishlistItem>
 */
class WishlistItemFactory extends Factory {
    public function definition(): array {
        return [
            'wishlist_id' => Wishlist::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'url' => fake()->optional()->url(),
            'image_url' => null,
            'price_min' => fake()->optional()->randomFloat(2, 10, 100),
            'price_max' => fake()->optional()->randomFloat(2, 100, 500),
            'category' => fake()->optional()->word(),
            'priority' => fake()->randomElement(['LOW', 'MEDIUM', 'HIGH']),
            'status' => 'AVAILABLE',
            'bought_by_user_id' => null,
            'bought_at' => null,
            'notes' => null,
        ];
    }
}
