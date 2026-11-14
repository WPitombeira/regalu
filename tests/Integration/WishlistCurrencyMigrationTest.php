<?php

use App\Models\WishlistItem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('preserves populated prices through currency migration rollback and reapplication without guessing legacy currency', function () {
    $migration = '2026_11_11_000002_add_currency_to_wishlist_items';
    $options = ['--path' => ["database/migrations/{$migration}.php"], '--force' => true];
    $otherMigrations = fn () => DB::table('migrations')->where('migration', '!=', $migration)->orderBy('id')->get()->toJson();
    $before = $otherMigrations();

    // Later migrations must remain applied while this exact migration is exercised.
    expect(Artisan::call('migrate:rollback', $options))->toBe(0);
    expect(Schema::hasColumn('wishlist_items', 'currency'))->toBeFalse()
        ->and(DB::table('migrations')->where('migration', $migration)->exists())->toBeFalse()
        ->and($otherMigrations())->toBe($before);
    $legacy = WishlistItem::factory()->create(['price_min' => '12.34', 'price_max' => '56.78']);

    expect(Artisan::call('migrate', $options))->toBe(0);
    expect(Schema::hasColumn('wishlist_items', 'currency'))->toBeTrue()
        ->and(DB::table('migrations')->where('migration', $migration)->exists())->toBeTrue()
        ->and($otherMigrations())->toBe($before);
    expect($legacy->fresh()->currency)->toBeNull()
        ->and($legacy->fresh()->price_min)->toBe('12.34')->and($legacy->fresh()->price_max)->toBe('56.78');
    $explicit = WishlistItem::factory()->create(['price_min' => '0.00', 'price_max' => '99999999.99', 'currency' => 'USD']);
    expect($explicit->fresh()->currency)->toBe('USD');

    expect(Artisan::call('migrate:rollback', $options))->toBe(0);
    expect(Schema::hasColumn('wishlist_items', 'currency'))->toBeFalse()
        ->and(DB::table('migrations')->where('migration', $migration)->exists())->toBeFalse()
        ->and($otherMigrations())->toBe($before)
        ->and(DB::table('wishlist_items')->count())->toBe(2);
    expect($legacy->fresh()->price_min)->toBe('12.34')->and($explicit->fresh()->price_min)->toBe('0.00')
        ->and($explicit->fresh()->price_max)->toBe('99999999.99');

    expect(Artisan::call('migrate', $options))->toBe(0);
    expect(Schema::hasColumn('wishlist_items', 'currency'))->toBeTrue()
        ->and(DB::table('migrations')->where('migration', $migration)->exists())->toBeTrue()
        ->and($otherMigrations())->toBe($before);
    // Rolling back a nullable metadata column intentionally loses recorded currency.
    expect($legacy->fresh()->currency)->toBeNull()->and($explicit->fresh()->currency)->toBeNull()
        ->and(DB::select('PRAGMA foreign_key_check'))->toBe([]);
});
