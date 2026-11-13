<?php

use App\Livewire\Wishlist\WishlistDetail;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

it('shows explicit currency and legacy uncertainty without exposing owner purchase state', function (string $locale, string $priced, string $unknown) {
    app()->setLocale($locale);
    $owner = User::factory()->create();
    $wishlist = Wishlist::factory()->create(['user_id' => $owner->id]);
    WishlistItem::factory()->create(['wishlist_id' => $wishlist->id, 'price_min' => '29.95', 'price_max' => '29.95', 'currency' => 'USD', 'status' => 'BOUGHT']);
    WishlistItem::factory()->create(['wishlist_id' => $wishlist->id, 'price_min' => '0', 'price_max' => null, 'currency' => null]);

    $component = Livewire::actingAs($owner)->test(WishlistDetail::class, ['wishlist' => $wishlist])
        ->assertSee($priced)->assertSee($unknown);
    foreach ($component->viewData('items') as $item) {
        expect($item->getAttributes())->toHaveKey('currency')->not->toHaveKeys(['status', 'bought_at', 'bought_by_user_id', 'updated_at']);
    }
})->with([
    ['en', 'USD 29.95', 'Currency not specified'],
    ['pt_BR', 'USD 29,95', 'Moeda não informada'],
]);

it('renders every price range shape without rounding away zero', function (string $locale, ?string $min, ?string $max, string $expected) {
    app()->setLocale($locale);
    $html = Blade::render('<x-wishlist.price :minimum="$min" :maximum="$max" currency="EUR" />', compact('min', 'max'));
    expect(trim(strip_tags($html)))->toBe($expected);
})->with([
    ['en', '0.00', null, 'From EUR 0.00'],
    ['en', null, '9.50', 'Up to EUR 9.50'],
    ['en', '9.50', '10.00', 'EUR 9.50 – 10.00'],
    ['pt_BR', '0.00', null, 'A partir de EUR 0,00'],
    ['pt_BR', null, '9.50', 'Até EUR 9,50'],
    ['pt_BR', '9.50', '10.00', 'EUR 9,50 – 10,00'],
    ['pt_BR', '99999999.99', '99999999.99', 'EUR 99999999,99'],
    ['en', null, null, ''],
]);
