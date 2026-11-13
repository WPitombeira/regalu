<?php

use App\Livewire\Wishlist\AddItem;
use App\Models\User;
use App\Models\Wishlist;
use Livewire\Livewire;

it('rejects prices that cannot be stored exactly as a supported decimal', function (string $field, string $amount) {
    $owner = User::factory()->create();
    $wishlist = Wishlist::factory()->create(['user_id' => $owner->id]);

    Livewire::actingAs($owner)->test(AddItem::class, ['wishlistId' => $wishlist->id])
        ->set('name', 'A gift')->set($field, $amount)->call('save')->assertHasErrors($field);
    $this->assertDatabaseCount('wishlist_items', 0);
})->with(['price_min', 'price_max'])->with([
    'overflow' => '100000000',
    'fractional cents' => '12.345',
    'scientific notation' => '1e2',
    'negative' => '-1',
    'localized decimal' => '12,34',
]);

it('rejects an inverted price range without creating an item', function () {
    $owner = User::factory()->create();
    $wishlist = Wishlist::factory()->create(['user_id' => $owner->id]);
    Livewire::actingAs($owner)->test(AddItem::class, ['wishlistId' => $wishlist->id])
        ->set('name', 'A gift')->set('price_min', '20.00')->set('price_max', '19.99')
        ->call('save')->assertHasErrors('price_max');
    $this->assertDatabaseCount('wishlist_items', 0);
});

it('stores manual amounts without inventing currency for an unpriced item', function (?string $minimum, ?string $maximum, ?string $expectedMinimum, ?string $expectedMaximum, ?string $expectedCurrency) {
    $owner = User::factory()->create();
    $wishlist = Wishlist::factory()->create(['user_id' => $owner->id]);
    Livewire::actingAs($owner)->test(AddItem::class, ['wishlistId' => $wishlist->id])
        ->set('name', 'A gift')->set('price_min', $minimum)->set('price_max', $maximum)->set('currency', 'EUR')
        ->call('save')->assertHasNoErrors();
    $item = $wishlist->items()->sole();
    expect($item->price_min)->toBe($expectedMinimum)->and($item->price_max)->toBe($expectedMaximum)
        ->and($item->currency)->toBe($expectedCurrency);
})->with([
    'zero' => ['0', '0', '0.00', '0.00', 'EUR'],
    'maximum' => ['99999999.99', null, '99999999.99', null, 'EUR'],
    'upper bound only' => [null, '9.5', null, '9.50', 'EUR'],
    'blank' => ['', '', null, null, null],
    'null' => [null, null, null, null, null],
]);
