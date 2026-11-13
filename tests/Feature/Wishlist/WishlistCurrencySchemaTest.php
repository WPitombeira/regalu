<?php

use App\Models\WishlistItem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('stores a nullable explicit currency with the wishlist price', function () {
    expect(Schema::hasColumn('wishlist_items', 'currency'))->toBeTrue();
    $item = WishlistItem::factory()->create(['price_min' => '12.34', 'price_max' => '12.34']);
    expect($item->fresh()->currency)->toBeNull();
    DB::table('wishlist_items')->where('id', $item->id)->update(['currency' => 'USD']);
    expect($item->fresh()->currency)->toBe('USD')->and($item->fresh()->price_min)->toBe('12.34');
    expect(fn () => DB::table('wishlist_items')->where('id', $item->id)->update(['currency' => 'ZZZ']))->toThrow(QueryException::class);
});
