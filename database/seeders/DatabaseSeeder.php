<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder {
    public function run(): void {
        $testUser = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => '12345',
        ]);

        $secondUser = User::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => '12345',
        ]);

        $family = Family::create([
            'name' => 'Demo Family',
            'description' => 'A demo family for testing',
            'invite_code' => 'DEMO12345678',
            'creator_id' => $testUser->id,
        ]);

        FamilyMember::create([
            'family_id' => $family->id,
            'user_id' => $testUser->id,
            'role' => 'ADMIN',
            'joined_at' => now(),
        ]);

        FamilyMember::create([
            'family_id' => $family->id,
            'user_id' => $secondUser->id,
            'role' => 'MEMBER',
            'joined_at' => now(),
        ]);

        $wishlist = Wishlist::create([
            'user_id' => $testUser->id,
            'family_id' => $family->id,
            'name' => 'Christmas 2026',
            'description' => 'My Christmas wishlist',
            'type' => 'CHRISTMAS',
            'privacy' => 'FAMILY',
        ]);

        WishlistItem::create([
            'wishlist_id' => $wishlist->id,
            'name' => 'Wireless Headphones',
            'description' => 'Noise cancelling wireless headphones',
            'price_min' => 50.00,
            'price_max' => 150.00,
            'priority' => 'HIGH',
        ]);

        WishlistItem::create([
            'wishlist_id' => $wishlist->id,
            'name' => 'Book: Clean Code',
            'description' => 'By Robert C. Martin',
            'price_min' => 20.00,
            'price_max' => 40.00,
            'priority' => 'MEDIUM',
        ]);
    }
}
