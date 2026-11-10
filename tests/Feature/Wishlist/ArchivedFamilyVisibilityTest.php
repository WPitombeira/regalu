<?php

use App\Livewire\Wishlist\WishlistDetail;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Models\WishlistShare;
use Livewire\Livewire;

it('revokes family wishlist reads and reservations when the last shared family is archived', function (string $action) {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $family = Family::factory()->create(['creator_id' => $owner->id]);
    foreach ([$owner, $viewer] as $user) {
        FamilyMember::create(['family_id' => $family->id, 'user_id' => $user->id, 'role' => 'MEMBER']);
    }
    $wishlist = Wishlist::factory()->create(['user_id' => $owner->id, 'privacy' => 'FAMILY']);
    $item = WishlistItem::factory()->create(['wishlist_id' => $wishlist->id, 'status' => 'AVAILABLE']);
    $component = Livewire::actingAs($viewer)->test(WishlistDetail::class, ['wishlist' => $wishlist])->assertOk();
    $family->update(['is_archived' => true]);

    $component->call($action, ...($action === 'markAsBought' ? [$item->id] : []))->assertForbidden();
    expect($item->fresh()->status)->toBe('AVAILABLE');
    expect($wishlist->isVisibleTo($viewer))->toBeFalse();
    expect($wishlist->isVisibleTo($owner))->toBeTrue();
    $future = Wishlist::factory()->create(['user_id' => $owner->id, 'privacy' => 'FAMILY']);
    Livewire::test(WishlistDetail::class, ['wishlist' => $future])->assertForbidden();
})->with(['$refresh', 'markAsBought']);

it('preserves access through another active shared family or an explicit share', function (string $grant) {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $archived = Family::factory()->create(['creator_id' => $owner->id, 'is_archived' => true]);
    foreach ([$owner, $viewer] as $user) {
        FamilyMember::create(['family_id' => $archived->id, 'user_id' => $user->id, 'role' => 'MEMBER']);
    }
    $wishlist = Wishlist::factory()->create(['user_id' => $owner->id, 'privacy' => $grant === 'family' ? 'FAMILY' : 'SPECIFIC']);
    if ($grant === 'family') {
        $active = Family::factory()->create(['creator_id' => $owner->id]);
        foreach ([$owner, $viewer] as $user) {
            FamilyMember::create(['family_id' => $active->id, 'user_id' => $user->id, 'role' => 'MEMBER']);
        }
    } else {
        WishlistShare::create(['wishlist_id' => $wishlist->id, 'shared_with_user_id' => $viewer->id, 'access_level' => 'VIEW']);
    }
    $item = WishlistItem::factory()->create(['wishlist_id' => $wishlist->id, 'status' => 'AVAILABLE']);
    Livewire::actingAs($viewer)->test(WishlistDetail::class, ['wishlist' => $wishlist])
        ->call('markAsBought', $item->id)->assertOk();
    expect($item->fresh()->status)->toBe('BOUGHT');
})->with(['family', 'explicit share']);
