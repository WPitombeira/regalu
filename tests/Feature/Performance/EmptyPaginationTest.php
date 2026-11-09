<?php

use App\Livewire\AmigoSecreto\EventIndex;
use App\Livewire\Wishlist\WishlistDetail;
use App\Livewire\Wishlist\WishlistIndex;
use App\Models\AmigoSecretoEvent;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Livewire\Livewire;

it('keeps previous navigation when another tab empties a later page', function (string $listing) {
    $owner = User::factory()->create();
    $pageSize = 12;
    $parameters = [];
    if ($listing === 'wishlists') {
        $records = Wishlist::factory()->count(13)->create(['user_id' => $owner->id]);
        $componentClass = WishlistIndex::class;
    } elseif ($listing === 'events') {
        $records = AmigoSecretoEvent::factory()->count(13)->create(['organizer_id' => $owner->id]);
        $componentClass = EventIndex::class;
    } else {
        $wishlist = Wishlist::factory()->create(['user_id' => $owner->id]);
        $records = WishlistItem::factory()->count(21)->create(['wishlist_id' => $wishlist->id]);
        $componentClass = WishlistDetail::class;
        $parameters = ['wishlist' => $wishlist];
        $pageSize = 20;
    }
    $component = Livewire::actingAs($owner)->test($componentClass, $parameters)
        ->call('nextPage')->assertViewHas($listing, fn ($page) => $page->currentPage() === 2 && $page->count() === 1);

    // A separate request removes a record after this component reached page two.
    if ($listing === 'items') {
        $records->first()->delete();
    } else {
        $records->first()->update(['is_archived' => true]);
    }

    $component->call('$refresh')
        ->assertViewHas($listing, fn ($page) => $page->isEmpty() && $page->currentPage() === 2)
        ->assertSee('previousPage', false)
        ->call('previousPage')
        ->assertViewHas($listing, fn ($page) => $page->currentPage() === 1 && $page->count() === $pageSize)
        ->assertSee($records->last()->name);
})->with(['wishlists', 'events', 'items']);
