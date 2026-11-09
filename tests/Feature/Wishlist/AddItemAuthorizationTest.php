<?php

use App\Livewire\Wishlist\AddItem;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

describe('adding wishlist items authorization', function () {
    it('does not allow another user to mount metadata fetching for a wishlist', function () {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $wishlist = Wishlist::factory()->create(['user_id' => $owner->id]);

        Livewire::actingAs($other)
            ->test(AddItem::class, ['wishlistId' => $wishlist->id])
            ->assertForbidden();
    });

    it('rechecks ownership before fetching metadata', function () {
        $owner = User::factory()->create();
        $newOwner = User::factory()->create();
        $wishlist = Wishlist::factory()->create(['user_id' => $owner->id]);
        $component = Livewire::actingAs($owner)->test(AddItem::class, ['wishlistId' => $wishlist->id]);

        $wishlist->update(['user_id' => $newOwner->id]);

        $component->call('fetchMeta')->assertForbidden();
    });

    it('rechecks ownership before saving a wishlist item', function () {
        $owner = User::factory()->create();
        $newOwner = User::factory()->create();
        $wishlist = Wishlist::factory()->create(['user_id' => $owner->id]);
        $component = Livewire::actingAs($owner)
            ->test(AddItem::class, ['wishlistId' => $wishlist->id])
            ->set('name', 'Restricted item');

        $wishlist->update(['user_id' => $newOwner->id]);

        $component->call('save')->assertForbidden();
        expect(WishlistItem::query()->where('wishlist_id', $wishlist->id)->exists())->toBeFalse();
    });

    it('locks the wishlist identifier against client-side changes', function () {
        $owner = User::factory()->create();
        $wishlist = Wishlist::factory()->create(['user_id' => $owner->id]);
        $otherWishlist = Wishlist::factory()->create(['user_id' => $owner->id]);
        $component = Livewire::actingAs($owner)->test(AddItem::class, ['wishlistId' => $wishlist->id]);

        expect(fn () => $component->set('wishlist_id', $otherWishlist->id))
            ->toThrow(CannotUpdateLockedPropertyException::class);
    });

    it('rejects client changes to server-selected image URLs before rendering or saving', function (string $image) {
        $owner = User::factory()->create();
        $wishlist = Wishlist::factory()->create(['user_id' => $owner->id]);
        $component = Livewire::actingAs($owner)->test(AddItem::class, ['wishlistId' => $wishlist->id])
            ->set('name', 'A legitimate item');

        expect(fn () => $component->set('image_url', $image)->call('save'))
            ->toThrow(CannotUpdateLockedPropertyException::class);
        expect(WishlistItem::query()->exists())->toBeFalse();
    })->with([
        'private network' => 'http://127.0.0.1/private.png',
        'public tracker' => 'https://tracker.example.com/pixel.png',
        'non-HTTP' => 'file:///etc/passwd',
    ]);
});
