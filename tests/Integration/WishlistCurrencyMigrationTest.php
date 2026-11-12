<?php

use App\Models\WishlistItem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('preserves populated prices through currency migration rollback and reapplication without guessing legacy currency', function () {
    expect(Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]))->toBe(0);
    expect(Schema::hasColumn('wishlist_items', 'currency'))->toBeFalse();
    $legacy = WishlistItem::factory()->create(['price_min' => '12.34', 'price_max' => '56.78']);

    expect(Artisan::call('migrate', ['--force' => true]))->toBe(0);
    expect($legacy->fresh()->currency)->toBeNull()
        ->and($legacy->fresh()->price_min)->toBe('12.34')->and($legacy->fresh()->price_max)->toBe('56.78');
    $explicit = WishlistItem::factory()->create(['price_min' => '0.00', 'price_max' => '99999999.99', 'currency' => 'USD']);
    expect($explicit->fresh()->currency)->toBe('USD');

    expect(Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]))->toBe(0);
    expect(Schema::hasColumn('wishlist_items', 'currency'))->toBeFalse()
        ->and(DB::table('wishlist_items')->count())->toBe(2);
    expect($legacy->fresh()->price_min)->toBe('12.34')->and($explicit->fresh()->price_min)->toBe('0.00')
        ->and($explicit->fresh()->price_max)->toBe('99999999.99');

    expect(Artisan::call('migrate', ['--force' => true]))->toBe(0);
    // Rolling back a nullable metadata column intentionally loses recorded currency.
    expect($legacy->fresh()->currency)->toBeNull()->and($explicit->fresh()->currency)->toBeNull()
        ->and(DB::select('PRAGMA foreign_key_check'))->toBe([]);
});
