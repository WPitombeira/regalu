<?php

use App\Livewire\Wishlist\CreateWishlist;
use App\Livewire\Wishlist\WishlistDetail;
use App\Livewire\Wishlist\WishlistSettings;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Models\WishlistShare;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

it('denies direct Livewire wishlist mounts to unrelated users', function (string $component) {
    $wishlist = Wishlist::factory()->create();
    Livewire::actingAs(User::factory()->create())->test($component, ['wishlist' => $wishlist])->assertForbidden();
})->with([WishlistDetail::class, WishlistSettings::class]);

it('rechecks sharing before buying from an already mounted wishlist', function () {
    $buyer = User::factory()->create();
    $wishlist = Wishlist::factory()->create(['privacy' => 'SPECIFIC']);
    $share = WishlistShare::create(['wishlist_id' => $wishlist->id, 'shared_with_user_id' => $buyer->id, 'access_level' => 'VIEW']);
    $item = WishlistItem::factory()->create(['wishlist_id' => $wishlist->id, 'status' => 'AVAILABLE']);
    $component = Livewire::actingAs($buyer)->test(WishlistDetail::class, ['wishlist' => $wishlist]);
    $share->delete();
    $component->call('markAsBought', $item->id)->assertForbidden();
    expect($item->fresh()->status)->toBe('AVAILABLE');
});

it('rechecks ownership before every settings action', function (string $action, array $arguments) {
    $owner = User::factory()->create();
    $wishlist = Wishlist::factory()->create(['user_id' => $owner->id]);
    $component = Livewire::actingAs($owner)->test(WishlistSettings::class, ['wishlist' => $wishlist]);
    $wishlist->update(['user_id' => User::factory()->create()->id]);
    $component->call($action, ...$arguments)->assertForbidden();
    expect($wishlist->fresh())->not->toBeNull();
    expect($wishlist->fresh()->is_archived)->toBeFalse();
})->with([
    ['update', []], ['addShare', []], ['removeShare', [1]], ['archive', []], ['delete', []],
]);

it('does not disclose buyer identity in owner item projections', function () {
    $owner = User::factory()->create();
    $buyer = User::factory()->create();
    $wishlist = Wishlist::factory()->create(['user_id' => $owner->id]);
    WishlistItem::factory()->create(['wishlist_id' => $wishlist->id, 'status' => 'BOUGHT', 'bought_by_user_id' => $buyer->id]);
    Livewire::actingAs($owner)->test(WishlistDetail::class, ['wishlist' => $wishlist])
        ->assertViewHas('items', fn ($items) => ! array_key_exists('bought_by_user_id', $items->first()->getAttributes()));
});

it('atomically keeps the first buyer when a stale second model tries to reserve the same gift', function () {
    $firstBuyer = User::factory()->create();
    $secondBuyer = User::factory()->create();
    $wishlist = Wishlist::factory()->create(['privacy' => 'SPECIFIC']);
    foreach ([$firstBuyer, $secondBuyer] as $buyer) {
        WishlistShare::create(['wishlist_id' => $wishlist->id, 'shared_with_user_id' => $buyer->id, 'access_level' => 'VIEW']);
    }
    $item = WishlistItem::factory()->create(['wishlist_id' => $wishlist->id, 'status' => 'AVAILABLE']);
    $stale = $item->fresh();
    expect($item->markAsBought($firstBuyer))->toBeTrue();
    expect($stale->markAsBought($secondBuyer))->toBeFalse();
    expect($item->fresh()->bought_by_user_id)->toBe($firstBuyer->id);
});

it('fails closed when a wishlist component is refreshed after logout', function () {
    $owner = User::factory()->create();
    $wishlist = Wishlist::factory()->create(['user_id' => $owner->id]);
    $component = Livewire::actingAs($owner)->test(WishlistDetail::class, ['wishlist' => $wishlist]);
    Auth::logout();
    $component->call('$refresh')->assertForbidden();
});

it('allows the current owner to delete a wishlist and its items', function () {
    $owner = User::factory()->create();
    $wishlist = Wishlist::factory()->create(['user_id' => $owner->id]);
    $item = WishlistItem::factory()->create(['wishlist_id' => $wishlist->id]);
    Livewire::actingAs($owner)->test(WishlistSettings::class, ['wishlist' => $wishlist])
        ->call('delete')->assertRedirect(route('wishlists.index'));
    expect($wishlist->fresh())->toBeNull()->and($item->fresh())->toBeNull();
});

it('does not attach new wishlists to an unrelated or archived family', function (bool $archived) {
    $user = User::factory()->create();
    $family = Family::factory()->create(['is_archived' => $archived]);
    if ($archived) {
        FamilyMember::create(['family_id' => $family->id, 'user_id' => $user->id, 'role' => 'MEMBER']);
    }
    Livewire::actingAs($user)->test(CreateWishlist::class)
        ->set('name', 'Unrelated family list')->set('family_id', $family->id)
        ->call('create')->assertForbidden();
    expect(Wishlist::count())->toBe(0);
})->with([false, true]);

it('does not create wishlists after the session is logged out', function () {
    $component = Livewire::actingAs(User::factory()->create())->test(CreateWishlist::class);
    Auth::logout();
    $component->call('create')->assertForbidden();
    expect(Wishlist::count())->toBe(0);
});
