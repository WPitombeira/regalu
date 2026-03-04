<?php

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Models\WishlistShare;
use Livewire\Livewire;

describe('wishlists', function () {

    it('can create a wishlist', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(\App\Livewire\Wishlist\CreateWishlist::class)
            ->set('name', 'My Birthday Wishlist')
            ->set('description', 'Things I want for my birthday')
            ->set('type', 'BIRTHDAY')
            ->set('privacy', 'PRIVATE')
            ->call('create')
            ->assertDispatched('notification')
            ->assertRedirect();

        $this->assertDatabaseHas('wishlists', [
            'user_id' => $user->id,
            'name' => 'My Birthday Wishlist',
            'type' => 'BIRTHDAY',
            'privacy' => 'PRIVATE',
        ]);
    });

    it('owner can view their wishlist', function () {
        $user = User::factory()->create();
        $wishlist = Wishlist::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('wishlists.show', $wishlist));

        $response->assertStatus(200);
    });

    it('non-owner cannot view private wishlist', function () {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $wishlist = Wishlist::factory()->create([
            'user_id' => $owner->id,
            'privacy' => 'PRIVATE',
        ]);

        $response = $this->actingAs($other)->get(route('wishlists.show', $wishlist));

        $response->assertStatus(403);
    });

    it('family member can view family wishlist', function () {
        $owner = User::factory()->create();
        $familyMember = User::factory()->create();
        $family = Family::factory()->create(['creator_id' => $owner->id]);

        FamilyMember::create([
            'family_id' => $family->id,
            'user_id' => $owner->id,
            'role' => 'ADMIN',
            'joined_at' => now(),
        ]);

        FamilyMember::create([
            'family_id' => $family->id,
            'user_id' => $familyMember->id,
            'role' => 'MEMBER',
            'joined_at' => now(),
        ]);

        $wishlist = Wishlist::factory()->create([
            'user_id' => $owner->id,
            'privacy' => 'FAMILY',
        ]);

        $response = $this->actingAs($familyMember)->get(route('wishlists.show', $wishlist));

        $response->assertStatus(200);
    });

    it('can add item to wishlist', function () {
        $user = User::factory()->create();
        $wishlist = Wishlist::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Wishlist\AddItem::class, ['wishlistId' => $wishlist->id])
            ->set('name', 'New Headphones')
            ->set('description', 'Sony WH-1000XM5')
            ->set('url', 'https://example.com/headphones')
            ->set('price_min', 299.99)
            ->set('price_max', 349.99)
            ->set('priority', 'HIGH')
            ->call('save')
            ->assertDispatched('notification')
            ->assertDispatched('item-added');

        $this->assertDatabaseHas('wishlist_items', [
            'wishlist_id' => $wishlist->id,
            'name' => 'New Headphones',
            'priority' => 'HIGH',
            'status' => 'AVAILABLE',
        ]);
    });

    it('non-owner can mark item as bought', function () {
        $owner = User::factory()->create();
        $buyer = User::factory()->create();
        $wishlist = Wishlist::factory()->create([
            'user_id' => $owner->id,
            'privacy' => 'SPECIFIC',
        ]);
        $item = WishlistItem::factory()->create([
            'wishlist_id' => $wishlist->id,
            'status' => 'AVAILABLE',
        ]);

        WishlistShare::create([
            'wishlist_id' => $wishlist->id,
            'shared_with_user_id' => $buyer->id,
            'access_level' => 'VIEW',
        ]);

        Livewire::actingAs($buyer)
            ->test(\App\Livewire\Wishlist\WishlistDetail::class, ['wishlist' => $wishlist])
            ->call('markAsBought', $item->id)
            ->assertDispatched('notification');

        $item->refresh();
        expect($item->status)->toBe('BOUGHT');
        expect($item->bought_by_user_id)->toBe($buyer->id);
        expect($item->bought_at)->not->toBeNull();
    });

    it('owner cannot see who bought an item', function () {
        $owner = User::factory()->create();
        $buyer = User::factory()->create();
        $wishlist = Wishlist::factory()->create(['user_id' => $owner->id]);
        $item = WishlistItem::factory()->create([
            'wishlist_id' => $wishlist->id,
            'status' => 'BOUGHT',
            'bought_by_user_id' => $buyer->id,
            'bought_at' => now(),
        ]);

        $response = $this->actingAs($owner)->get(route('wishlists.show', $wishlist));

        $response->assertStatus(200);
        $response->assertSee(__('messages.wishlist.buyer_hidden'));
        $response->assertDontSee($buyer->name);
    });

    it('can archive a wishlist', function () {
        $user = User::factory()->create();
        $wishlist = Wishlist::factory()->create([
            'user_id' => $user->id,
            'is_archived' => false,
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Wishlist\WishlistSettings::class, ['wishlist' => $wishlist])
            ->call('archive')
            ->assertRedirect(route('wishlists.index'));

        $wishlist->refresh();
        expect($wishlist->is_archived)->toBeTrue();
    });

});
