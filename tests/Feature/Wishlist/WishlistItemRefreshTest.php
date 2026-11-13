<?php

use App\Livewire\Wishlist\AddItem;
use App\Livewire\Wishlist\WishlistDetail;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Models\WishlistShare;
use Livewire\Livewire;

it('refreshes the mounted wishlist when the add form saves an item', function () {
    $owner = User::factory()->create();
    $wishlist = Wishlist::factory()->create(['user_id' => $owner->id]);
    $detail = Livewire::actingAs($owner)->test(WishlistDetail::class, ['wishlist' => $wishlist])
        ->assertViewHas('items', fn ($items) => $items->isEmpty());

    Livewire::actingAs($owner)->test(AddItem::class, ['wishlistId' => $wishlist->id])
        ->set('name', 'Newly saved gift')
        ->set('price_min', '29.95')
        ->set('price_max', '29.95')
        ->set('currency', 'USD')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('item-added');

    $item = $wishlist->items()->sole();
    expect($item->price_min)->toBe('29.95')->and($item->currency)->toBe('USD');
    $detail->dispatch('item-added')
        ->assertSee('Newly saved gift')
        ->assertSee('USD 29.95')
        ->assertViewHas('items', fn ($items) => $items->modelKeys() === [$item->id]);
});

it('returns to the first priority-sorted page after an item is added', function () {
    $owner = User::factory()->create();
    $wishlist = Wishlist::factory()->create(['user_id' => $owner->id]);
    WishlistItem::factory()->count(21)->create(['wishlist_id' => $wishlist->id, 'priority' => 'HIGH']);
    $detail = Livewire::actingAs($owner)->test(WishlistDetail::class, ['wishlist' => $wishlist])
        ->call('nextPage')->assertSet('paginators.page', 2);
    $item = WishlistItem::factory()->create(['wishlist_id' => $wishlist->id, 'priority' => 'HIGH']);
    WishlistItem::factory()->create(['name' => 'Other private list sentinel']);

    $detail->dispatch('item-added')
        ->assertSet('paginators.page', 1)
        ->assertViewHas('items', fn ($items) => $items->first()->id === $item->id && $items->count() === 20)
        ->assertDontSee('Other private list sentinel')
        ->assertDontSee('bought_by_user_id', false)
        ->assertDontSee('bought_at', false);
});

it('rechecks current visibility when an item-added event refreshes the list', function () {
    $viewer = User::factory()->create();
    $wishlist = Wishlist::factory()->create(['privacy' => 'SPECIFIC']);
    $share = WishlistShare::create(['wishlist_id' => $wishlist->id, 'shared_with_user_id' => $viewer->id, 'access_level' => 'VIEW']);
    $detail = Livewire::actingAs($viewer)->test(WishlistDetail::class, ['wishlist' => $wishlist]);
    $share->delete();
    WishlistItem::factory()->create(['wishlist_id' => $wishlist->id, 'name' => 'Revoked access sentinel']);

    $detail->dispatch('item-added')->assertForbidden()->assertDontSee('Revoked access sentinel');
});
