<?php

use App\Livewire\Wishlist\WishlistDetail;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Models\WishlistShare;
use Livewire\Livewire;

it('orders all wishlist items by priority before pagination with stable ties', function (bool $asOwner) {
    $owner = User::factory()->create();
    $viewer = $asOwner ? $owner : User::factory()->create();
    $wishlist = Wishlist::factory()->create(['user_id' => $owner->id, 'privacy' => 'SPECIFIC']);
    if (! $asOwner) {
        WishlistShare::create(['wishlist_id' => $wishlist->id, 'shared_with_user_id' => $viewer->id, 'access_level' => 'VIEW']);
    }
    $createdAt = now()->subDay();
    $high = WishlistItem::factory()->count(21)->create([
        'wishlist_id' => $wishlist->id, 'priority' => 'HIGH', 'created_at' => $createdAt,
    ]);
    $medium = WishlistItem::factory()->create(['wishlist_id' => $wishlist->id, 'priority' => 'MEDIUM']);
    $low = WishlistItem::factory()->create(['wishlist_id' => $wishlist->id, 'priority' => 'LOW']);
    $expected = $high->reverse()->values()->modelKeys();

    Livewire::actingAs($viewer)->test(WishlistDetail::class, ['wishlist' => $wishlist])
        ->assertViewHas('items', fn ($items) => $items->pluck('id')->all() === array_slice($expected, 0, 20))
        ->call('nextPage')
        ->assertViewHas('items', fn ($items) => $items->pluck('id')->all() === [$expected[20], $medium->id, $low->id])
        ->call('previousPage')
        ->assertViewHas('items', fn ($items) => $items->pluck('id')->all() === array_slice($expected, 0, 20));
})->with([true, false]);

it('shows the owner only a whole-list purchase total without item-level purchase fields', function () {
    app()->setLocale('en');
    $owner = User::factory()->create();
    $buyer = User::factory()->create(['name' => 'Hidden Buyer Sentinel']);
    $wishlist = Wishlist::factory()->create(['user_id' => $owner->id]);
    WishlistItem::factory()->count(20)->create(['wishlist_id' => $wishlist->id, 'priority' => 'HIGH']);
    foreach (['BOUGHT', 'RECEIVED'] as $status) {
        WishlistItem::factory()->create([
            'wishlist_id' => $wishlist->id, 'priority' => 'LOW', 'status' => $status,
            'bought_by_user_id' => $buyer->id, 'bought_at' => now(),
        ]);
    }
    WishlistItem::factory()->create(['status' => 'BOUGHT']);
    $assertProjection = function ($items): bool {
        foreach ($items as $item) {
            expect(array_intersect(array_keys($item->getAttributes()), ['status', 'bought_by_user_id', 'bought_at', 'updated_at']))->toBe([]);
        }

        return true;
    };

    Livewire::actingAs($owner)->test(WishlistDetail::class, ['wishlist' => $wishlist])
        ->assertViewHas('purchasedCount', 2)
        ->assertViewHas('items', $assertProjection)
        ->assertSee('2 items purchased')
        ->assertDontSee('Available')->assertDontSee('opacity-60', false)
        ->assertDontSee('Hidden Buyer Sentinel')
        ->call('nextPage')
        ->assertViewHas('purchasedCount', 2)
        ->assertViewHas('items', $assertProjection)
        ->assertDontSee('opacity-60', false)
        ->assertDontSee('bought_at', false)->assertDontSee('bought_by_user_id', false)
        ->assertDontSee(__('messages.wishlist.buyer_hidden'));
});

it('denies owner purchase probes uniformly without exposing whether an item was bought', function (string $status) {
    $owner = User::factory()->create();
    $wishlist = Wishlist::factory()->create(['user_id' => $owner->id]);
    $item = WishlistItem::factory()->create(['wishlist_id' => $wishlist->id, 'status' => $status === 'MISSING' ? 'AVAILABLE' : $status]);
    $itemId = $item->id;
    if ($status === 'MISSING') {
        $item->delete();
    }

    Livewire::actingAs($owner)->test(WishlistDetail::class, ['wishlist' => $wishlist])
        ->call('markAsBought', $itemId)->assertForbidden();
    expect($status === 'MISSING' ? $item->fresh() : $item->fresh()->status)
        ->toBe($status === 'MISSING' ? null : $status);
})->with(['AVAILABLE', 'BOUGHT', 'RECEIVED', 'MISSING']);

it('keeps archived wishlists readable but removes and rejects purchase actions', function () {
    $buyer = User::factory()->create();
    $wishlist = Wishlist::factory()->create(['privacy' => 'SPECIFIC']);
    WishlistShare::create(['wishlist_id' => $wishlist->id, 'shared_with_user_id' => $buyer->id, 'access_level' => 'VIEW']);
    $item = WishlistItem::factory()->create(['wishlist_id' => $wishlist->id]);
    $component = Livewire::actingAs($buyer)->test(WishlistDetail::class, ['wishlist' => $wishlist]);
    $wishlist->update(['is_archived' => true]);

    $component->call('$refresh')->assertOk()
        ->assertSee(__('wishlist_purchase.archived'))
        ->assertDontSee('wire:click="markAsBought', false)
        ->call('markAsBought', $item->id)->assertForbidden();
    expect($item->fresh()->status)->toBe('AVAILABLE');
    $this->actingAs($buyer)->get(route('wishlists.show', $wishlist))->assertOk();
});

it('keeps per-item purchase coordination for authorized buyers without exposing buyer identities', function () {
    $buyer = User::factory()->create();
    $wishlist = Wishlist::factory()->create(['privacy' => 'SPECIFIC']);
    WishlistShare::create(['wishlist_id' => $wishlist->id, 'shared_with_user_id' => $buyer->id, 'access_level' => 'VIEW']);
    foreach (['AVAILABLE', 'BOUGHT', 'RECEIVED'] as $status) {
        WishlistItem::factory()->create(['wishlist_id' => $wishlist->id, 'status' => $status]);
    }
    Livewire::actingAs($buyer)->test(WishlistDetail::class, ['wishlist' => $wishlist])
        ->assertViewHas('purchasedCount', null)
        ->assertViewHas('items', function ($items): bool {
            expect($items->pluck('status')->sort()->values()->all())->toBe(['AVAILABLE', 'BOUGHT', 'RECEIVED']);
            foreach ($items as $item) {
                expect(array_intersect(array_keys($item->getAttributes()), ['bought_by_user_id', 'bought_at', 'updated_at']))->toBe([]);
            }

            return true;
        })->assertSee(__('wishlist_purchase.bought'))
        ->assertSee('wire:click="markAsBought', false);
});

it('localizes the owner purchase summary including empty singular and plural states', function (string $locale, int $count, string $text) {
    app()->setLocale($locale);
    $owner = User::factory()->create();
    $wishlist = Wishlist::factory()->create(['user_id' => $owner->id]);
    WishlistItem::factory()->count($count)->create(['wishlist_id' => $wishlist->id, 'status' => 'BOUGHT']);
    Livewire::actingAs($owner)->test(WishlistDetail::class, ['wishlist' => $wishlist])
        ->assertViewHas('purchasedCount', $count)->assertSee($text);
})->with([
    ['en', 0, 'No items purchased yet'], ['en', 1, '1 item purchased'], ['en', 2, '2 items purchased'],
    ['pt_BR', 0, 'Nenhum item comprado ainda'], ['pt_BR', 1, '1 item comprado'], ['pt_BR', 2, '2 itens comprados'],
]);
