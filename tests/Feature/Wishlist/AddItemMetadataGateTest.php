<?php

use App\Livewire\Wishlist\AddItem;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\PublicUrlResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

describe('adding wishlist items metadata actions', function () {
    it('validates the URL before attempting metadata fetching', function () {
        $user = User::factory()->create();
        $wishlist = Wishlist::factory()->create(['user_id' => $user->id]);
        $this->app->instance(PublicUrlResolver::class, new PublicUrlResolver(static fn (string $host): array => ['93.184.216.34']));
        Http::preventStrayRequests();
        Http::fake();

        Livewire::actingAs($user)
            ->test(AddItem::class, ['wishlistId' => $wishlist->id])
            ->set('url', 'file:///etc/passwd')
            ->call('fetchMeta')
            ->assertHasErrors('url');

        Http::assertNothingSent();
    });

    it('limits metadata fetch attempts per user', function () {
        $user = User::factory()->create();
        $wishlist = Wishlist::factory()->create(['user_id' => $user->id]);
        $this->app->instance(PublicUrlResolver::class, new PublicUrlResolver(static fn (string $host): array => ['93.184.216.34']));
        RateLimiter::clear('wishlist-meta-fetch:'.$user->id);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('<title>Fetched</title>')]);

        $component = Livewire::actingAs($user)->test(AddItem::class, ['wishlistId' => $wishlist->id]);

        for ($attempt = 1; $attempt <= 11; $attempt++) {
            $component
                ->set('url', 'https://catalog.example.com/item-'.$attempt)
                ->call('fetchMeta');
        }

        $component->assertHasErrors('url');
        Http::assertSentCount(10);
    });

    it('renders fetched image URLs with HTML-escaped query parameters', function () {
        $user = User::factory()->create();
        $wishlist = Wishlist::factory()->create(['user_id' => $user->id]);
        $url = 'https://catalog.example.com/escaped-image';
        $this->app->instance(PublicUrlResolver::class, new PublicUrlResolver(static fn (string $host): array => ['93.184.216.34']));
        Http::preventStrayRequests();
        Http::fake([$url => Http::response('<meta property="og:image" content="https://cdn.example.com/item.png?x=1&amp;y=2">')]);

        Livewire::actingAs($user)
            ->test(AddItem::class, ['wishlistId' => $wishlist->id])
            ->set('url', $url)
            ->call('fetchMeta')
            ->assertSee('src="https://cdn.example.com/item.png?x=1&amp;y=2"', false)
            ->assertDontSee('src="https://cdn.example.com/item.png?x=1&y=2"', false);
    });
});
