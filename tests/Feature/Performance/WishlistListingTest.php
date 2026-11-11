<?php

use App\Livewire\Wishlist\WishlistDetail;
use App\Livewire\Wishlist\WishlistIndex;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('paginates wishlists with stable ordering and resets the page when filters change', function () {
    $user = User::factory()->create();
    $lists = Wishlist::factory()->count(16)->create(['user_id' => $user->id, 'type' => 'BIRTHDAY']);
    $target = Wishlist::factory()->create(['user_id' => $user->id, 'name' => 'Unique target', 'type' => 'WEDDING']);
    $archived = Wishlist::factory()->create(['user_id' => $user->id, 'is_archived' => true]);
    $other = Wishlist::factory()->create();

    Livewire::actingAs($user)->test(WishlistIndex::class)
        ->assertViewHas('wishlists', fn ($page) => $page->count() === 12 && $page->first()->is($target))
        ->assertDontSee($archived->name)->assertDontSee($other->name)
        ->call('nextPage')
        ->assertViewHas('wishlists', fn ($page) => $page->count() === 5)
        ->set('search', 'Unique target')
        ->assertViewHas('wishlists', fn ($page) => $page->count() === 1 && $page->first()->is($target))
        ->set('search', '')->set('filterType', 'WEDDING')
        ->assertViewHas('wishlists', fn ($page) => $page->count() === 1)
        ->set('filterPrivacy', 'FAMILY')
        ->assertViewHas('wishlists', fn ($page) => $page->isEmpty());
});

it('checks shared-family visibility in one database query without materializing memberships', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $family = Family::factory()->create(['creator_id' => $owner->id]);
    foreach ([$owner, $member] as $user) {
        FamilyMember::create(['family_id' => $family->id, 'user_id' => $user->id, 'role' => 'MEMBER', 'joined_at' => now()]);
    }
    $wishlist = Wishlist::factory()->create(['user_id' => $owner->id, 'privacy' => 'FAMILY']);
    DB::enableQueryLog();
    expect($wishlist->isVisibleTo($member))->toBeTrue();
    expect(DB::getQueryLog())->toHaveCount(1);
    DB::disableQueryLog();
    expect($wishlist->isVisibleTo($outsider))->toBeFalse();
    expect($wishlist->isVisibleTo($owner))->toBeTrue();
});

it('checks family visibility only once per wishlist detail rerender', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $family = Family::factory()->create(['creator_id' => $owner->id]);
    foreach ([$owner, $member] as $user) {
        FamilyMember::create(['family_id' => $family->id, 'user_id' => $user->id, 'role' => 'MEMBER', 'joined_at' => now()]);
    }
    $wishlist = Wishlist::factory()->create(['user_id' => $owner->id, 'privacy' => 'FAMILY']);
    $component = Livewire::actingAs($member)->test(WishlistDetail::class, ['wishlist' => $wishlist]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        $component->call('$refresh')->assertOk();
        $visibilityQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'family_members') && str_contains($query['query'], 'exists'));
        expect($visibilityQueries)->toHaveCount(1);
    } finally {
        DB::disableQueryLog();
    }
});

it('bounds item listings without losing pages or exposing buyer identifiers', function () {
    $owner = User::factory()->create();
    $wishlist = Wishlist::factory()->create(['user_id' => $owner->id]);
    $items = WishlistItem::factory()->count(23)->create(['wishlist_id' => $wishlist->id, 'priority' => 'MEDIUM', 'created_at' => now()]);
    Livewire::actingAs($owner)->test(WishlistDetail::class, ['wishlist' => $wishlist])
        ->assertViewHas('items', fn ($page) => $page->count() === 20 && $page->first()->is($items->last()))
        ->call('nextPage')
        ->assertViewHas('items', fn ($page) => $page->count() === 3 && $page->last()->is($items->first())
            && ! array_key_exists('bought_by_user_id', $page->first()->getAttributes()));
});
