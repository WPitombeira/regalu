<?php

use App\Livewire\Wishlist\AddItem;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Services\PublicUrlResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('validates server-selected image state again before persisting an item', function (string $image) {
    $owner = User::factory()->create();
    $wishlist = Wishlist::factory()->create(['user_id' => $owner->id]);
    $component = Livewire::actingAs($owner)->test(AddItem::class, ['wishlistId' => $wishlist->id])->instance();
    $component->name = 'Valid item';
    // Server-side assignment exercises validation independently of the hydration lock.
    $component->image_url = $image;

    expect(fn () => $component->save())->toThrow(ValidationException::class);
    expect(WishlistItem::query()->exists())->toBeFalse();
})->with([
    'non-HTTP scheme' => 'ftp://cdn.example.com/item.png',
    'oversized URL' => 'https://cdn.example.com/'.str_repeat('a', 2048),
]);

it('saves a server-fetched public image and resets the locked preview', function () {
    $owner = User::factory()->create();
    $wishlist = Wishlist::factory()->create(['user_id' => $owner->id]);
    $this->app->instance(PublicUrlResolver::class, new PublicUrlResolver(static fn (string $host): array => ['93.184.216.34']));
    Http::preventStrayRequests();
    Http::fake(['https://catalog.example.com/item' => Http::response(
        '<title>A gift</title><meta property="og:image" content="https://cdn.example.com/item.png">',
    )]);

    Livewire::actingAs($owner)->test(AddItem::class, ['wishlistId' => $wishlist->id])
        ->set('url', 'https://catalog.example.com/item')->call('fetchMeta')
        ->assertSet('image_url', 'https://cdn.example.com/item.png')
        ->call('save')->assertHasNoErrors()->assertSet('image_url', '');

    expect(WishlistItem::query()->sole()->image_url)->toBe('https://cdn.example.com/item.png');
});
