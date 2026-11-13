<?php

use App\Livewire\Wishlist\WishlistDetail;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Models\WishlistShare;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('rechecks ordinary purchase grants in the final database mutation', function (string $change) {
    Queue::fake();
    $owner = User::factory()->create();
    $buyer = User::factory()->create();
    $other = User::factory()->create();
    $family = Family::factory()->create(['creator_id' => $owner->id]);
    foreach ([$owner, $buyer] as $member) {
        FamilyMember::create(['family_id' => $family->id, 'user_id' => $member->id, 'role' => 'MEMBER']);
    }
    $wishlist = Wishlist::factory()->create([
        'user_id' => $owner->id, 'privacy' => str_starts_with($change, 'family-') ? 'FAMILY' : 'SPECIFIC',
    ]);
    $share = WishlistShare::create(['wishlist_id' => $wishlist->id, 'shared_with_user_id' => $buyer->id, 'access_level' => 'VIEW']);
    $otherList = Wishlist::factory()->create(['user_id' => $owner->id, 'privacy' => 'SPECIFIC']);
    WishlistShare::create(['wishlist_id' => $otherList->id, 'shared_with_user_id' => $buyer->id, 'access_level' => 'VIEW']);
    $item = WishlistItem::factory()->create(['wishlist_id' => $wishlist->id, 'status' => 'AVAILABLE']);
    $component = Livewire::actingAs($buyer)->test(WishlistDetail::class, ['wishlist' => $wishlist]);
    $revoked = false;

    // Controlled SQLite interleaving after every permission read, not a multiprocess race.
    DB::connection()->beforeExecuting(function (string $sql) use ($change, $wishlist, $share, $family, $owner, $buyer, $other, $otherList, $item, &$revoked): void {
        if ($revoked || ! str_starts_with($sql, 'update "wishlist_items" set ')) {
            return;
        }
        $revoked = true;
        match ($change) {
            'share' => $share->delete(),
            'privacy' => $wishlist->update(['privacy' => 'PRIVATE']),
            'wishlist-archive' => $wishlist->update(['is_archived' => true]),
            'owner-to-buyer' => $wishlist->update(['user_id' => $buyer->id]),
            'owner-to-other' => $wishlist->update(['user_id' => $other->id]),
            'family-archive' => $family->update(['is_archived' => true]),
            'family-buyer' => FamilyMember::where('family_id', $family->id)->where('user_id', $buyer->id)->delete(),
            'family-owner' => FamilyMember::where('family_id', $family->id)->where('user_id', $owner->id)->delete(),
            'moved-item' => $item->update(['wishlist_id' => $otherList->id]),
            'deleted-item' => $item->delete(),
            'deleted-buyer' => $buyer->delete(),
        };
    });

    $component->call('markAsBought', $item->id);
    expect($revoked)->toBeTrue();
    if ($change === 'deleted-item') {
        expect($item->fresh())->toBeNull();
    } else {
        expect($item->fresh()->status)->toBe('AVAILABLE')
            ->and($item->fresh()->bought_by_user_id)->toBeNull()
            ->and($item->fresh()->bought_at)->toBeNull();
    }
    $component->assertStatus(409);
    Queue::assertNothingPushed();
})->with([
    'share', 'privacy', 'wishlist-archive', 'owner-to-buyer', 'owner-to-other',
    'family-archive', 'family-buyer', 'family-owner', 'moved-item', 'deleted-item', 'deleted-buyer',
]);

it('does not let the model purchase without a current ordinary grant', function (string $case) {
    Queue::fake();
    $owner = User::factory()->create();
    $buyer = $case === 'owner' ? $owner : User::factory()->create();
    $wishlist = Wishlist::factory()->create(['user_id' => $owner->id, 'privacy' => 'SPECIFIC']);
    $item = WishlistItem::factory()->create(['wishlist_id' => $wishlist->id]);
    if ($case === 'specific-with-family-only') {
        $family = Family::factory()->create(['creator_id' => $owner->id]);
        foreach ([$owner, $buyer] as $member) {
            FamilyMember::create(['family_id' => $family->id, 'user_id' => $member->id, 'role' => 'MEMBER']);
        }
    } else {
        WishlistShare::create(['wishlist_id' => $wishlist->id, 'shared_with_user_id' => $buyer->id, 'access_level' => 'VIEW']);
    }
    match ($case) {
        'private' => $wishlist->update(['privacy' => 'PRIVATE']),
        'family-with-share-only' => $wishlist->update(['privacy' => 'FAMILY']),
        'archived' => $wishlist->update(['is_archived' => true]),
        'deleted-list' => $wishlist->delete(),
        default => null,
    };

    expect($item->markAsBought($buyer))->toBeFalse();
    if ($case !== 'deleted-list') {
        expect($item->fresh()->status)->toBe('AVAILABLE')->and($item->fresh()->bought_by_user_id)->toBeNull();
    }
    Queue::assertNothingPushed();
})->with(['owner', 'private', 'specific-with-family-only', 'family-with-share-only', 'archived', 'deleted-list']);

it('rejects cross-wishlist item targets and purchases after logout without dispatching mail', function (bool $logout) {
    Queue::fake();
    $buyer = User::factory()->create();
    $wishlist = Wishlist::factory()->create(['privacy' => 'SPECIFIC']);
    WishlistShare::create(['wishlist_id' => $wishlist->id, 'shared_with_user_id' => $buyer->id, 'access_level' => 'VIEW']);
    $item = WishlistItem::factory()->create($logout ? ['wishlist_id' => $wishlist->id] : []);
    $component = Livewire::actingAs($buyer)->test(WishlistDetail::class, ['wishlist' => $wishlist]);
    if ($logout) {
        Auth::logout();
    }
    $component->call('markAsBought', $item->id)->assertStatus($logout ? 403 : 404);
    expect($item->fresh()->status)->toBe('AVAILABLE')->and($item->fresh()->bought_by_user_id)->toBeNull();
    Queue::assertNothingPushed();
})->with([false, true]);
