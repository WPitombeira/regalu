<?php

use App\Livewire\Wishlist\AddItem;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\PublicUrlResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->wishlist = Wishlist::factory()->create(['user_id' => $this->owner->id]);
    $this->app->instance(PublicUrlResolver::class, new PublicUrlResolver(static fn (string $host): array => ['93.184.216.34']));
    Http::preventStrayRequests();
    Http::fake(['https://catalog.example.com/gift' => Http::response('<title>A thoughtful gift</title><meta property="product:price:amount" content="29.95"><meta property="product:price:currency" content="USD">')]);
});

it('sends price review inputs to the server on blur', function (string $property) {
    Livewire::actingAs($this->owner)->test(AddItem::class, ['wishlistId' => $this->wishlist->id])
        ->assertSee('wire:model.live.blur="'.$property.'"', false);
})->with(['url', 'price_min', 'price_max']);

it('reviews an imported price before explicitly applying and saving its currency', function () {
    $component = Livewire::actingAs($this->owner)->test(AddItem::class, ['wishlistId' => $this->wishlist->id])
        ->set('url', 'https://catalog.example.com/gift')
        ->call('fetchMeta')
        ->assertSet('name', 'A thoughtful gift')
        ->assertSet('price_min', null)
        ->assertSet('price_max', null)
        ->assertSet('suggestedPrice', '29.95')
        ->assertSet('suggestedCurrency', 'USD');
    $this->assertDatabaseCount('wishlist_items', 0);

    $component->call('applySuggestedPrice')
        ->assertSet('price_min', '29.95')
        ->assertSet('price_max', '29.95')
        ->assertSet('currency', 'USD')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('suggestedPrice', null)
        ->assertSet('currency', 'BRL');

    $item = $this->wishlist->items()->sole();
    expect($item->price_min)->toBe('29.95')->and($item->price_max)->toBe('29.95')->and($item->currency)->toBe('USD');
});

it('never replaces manual entries when an imported price is applied', function (?string $minimum, ?string $maximum) {
    Livewire::actingAs($this->owner)->test(AddItem::class, ['wishlistId' => $this->wishlist->id])
        ->set('name', 'My chosen name')
        ->set('description', 'My notes')
        ->set('price_min', $minimum)
        ->set('price_max', $maximum)
        ->set('currency', 'EUR')
        ->set('url', 'https://catalog.example.com/gift')
        ->call('fetchMeta')
        ->assertSet('name', 'My chosen name')
        ->assertSet('description', 'My notes')
        ->assertSet('price_min', $minimum)
        ->assertSet('price_max', $maximum)
        ->assertSet('currency', 'EUR')
        ->call('applySuggestedPrice')
        ->assertHasErrors('suggestedPrice')
        ->assertSet('price_min', $minimum)
        ->assertSet('price_max', $maximum)
        ->assertSet('currency', 'EUR');
    $this->assertDatabaseCount('wishlist_items', 0);
})->with([
    'minimum only' => ['19.90', null],
    'maximum only' => [null, '30.00'],
    'zero is a real price' => ['0', null],
    'range' => ['19.90', '30.00'],
]);

it('invalidates the imported preview when its product URL changes', function (string $url) {
    Http::fake(['https://catalog.example.com/illustrated' => Http::response('<title>A gift</title><meta property="og:image" content="https://cdn.example.com/gift.png"><meta property="product:price:amount" content="29.95"><meta property="product:price:currency" content="USD">')]);

    Livewire::actingAs($this->owner)->test(AddItem::class, ['wishlistId' => $this->wishlist->id])
        ->set('url', 'https://catalog.example.com/illustrated')->call('fetchMeta')
        ->assertSet('image_url', 'https://cdn.example.com/gift.png')
        ->assertSet('suggestedPrice', '29.95')
        ->set('url', $url)
        ->assertSet('image_url', '')
        ->assertSet('suggestedPrice', null)
        ->assertSet('suggestedCurrency', null)
        ->call('applySuggestedPrice')
        ->assertHasErrors('suggestedPrice')
        ->assertSet('price_min', null)
        ->assertSet('price_max', null);
})->with(['different product' => 'https://catalog.example.com/other', 'empty' => '', 'invalid' => 'not a URL']);

it('checks the preview source again when applying it without a hydration hook', function () {
    $component = Livewire::actingAs($this->owner)->test(AddItem::class, ['wishlistId' => $this->wishlist->id])
        ->set('url', 'https://catalog.example.com/gift')->call('fetchMeta')->instance();
    $component->url = 'https://catalog.example.com/different';
    $component->applySuggestedPrice();

    expect($component->price_min)->toBeNull()->and($component->price_max)->toBeNull()
        ->and($component->getErrorBag()->has('suggestedPrice'))->toBeTrue();
});

it('removes old suggestions before a retry denied by the rate limiter', function () {
    $component = Livewire::actingAs($this->owner)->test(AddItem::class, ['wishlistId' => $this->wishlist->id])
        ->set('url', 'https://catalog.example.com/gift')->call('fetchMeta')
        ->assertSet('suggestedPrice', '29.95');
    for ($attempt = 0; $attempt < 10; $attempt++) {
        RateLimiter::hit('wishlist-meta-fetch:'.$this->owner->id, 60);
    }

    $component->call('fetchMeta')->assertHasErrors('url')->assertSet('suggestedPrice', null)
        ->assertSet('suggestedCurrency', null)->call('applySuggestedPrice')->assertSet('price_min', null);
    Http::assertSentCount(1);
});

it('presents localized import review and a manual-entry fallback', function (string $locale, string $fetch, string $apply, string $amount, string $fallback, string $currency) {
    app()->setLocale($locale);
    Http::fake(['https://catalog.example.com/no-price' => Http::response('<title>Unpriced product</title>')]);
    Livewire::actingAs($this->owner)->test(AddItem::class, ['wishlistId' => $this->wishlist->id])
        ->assertSee($fetch)->assertSee($currency)
        ->set('url', 'https://catalog.example.com/gift')->call('fetchMeta')
        ->assertSee($apply)->assertSee($amount)->assertSee('wire:click="applySuggestedPrice"', false)
        ->assertSee('role="status"', false)
        ->set('url', 'https://catalog.example.com/no-price')->call('fetchMeta')
        ->assertSee($fallback)->assertDontSee('wire:click="applySuggestedPrice"', false)
        ->set('price_min', '12.50')->set('currency', 'BRL')->call('save')->assertHasNoErrors();
    expect($this->wishlist->items()->sole()->price_min)->toBe('12.50');
})->with([
    ['en', 'Fetch details', 'Use this price', 'USD 29.95', 'No reliable price was found. You can enter it manually.', 'Currency'],
    ['pt_BR', 'Buscar detalhes', 'Usar este preço', 'USD 29,95', 'Nenhum preço confiável foi encontrado. Você pode informá-lo manualmente.', 'Moeda'],
]);

it('rejects client changes to the imported quote and its source', function (string $property, string $value) {
    $component = Livewire::actingAs($this->owner)->test(AddItem::class, ['wishlistId' => $this->wishlist->id])
        ->set('url', 'https://catalog.example.com/gift')->call('fetchMeta');

    expect(fn () => $component->set($property, $value))->toThrow(CannotUpdateLockedPropertyException::class);
    $this->assertDatabaseCount('wishlist_items', 0);
})->with([
    ['suggestedPrice', '1.00'], ['suggestedCurrency', 'EUR'], ['metadataSourceUrl', 'https://catalog.example.com/other'],
]);

it('rejects applying a previously fetched quote after ownership changes', function () {
    $component = Livewire::actingAs($this->owner)->test(AddItem::class, ['wishlistId' => $this->wishlist->id])
        ->set('url', 'https://catalog.example.com/gift')->call('fetchMeta');
    $this->wishlist->update(['user_id' => User::factory()->create()->id]);

    $component->call('applySuggestedPrice')->assertForbidden();
    $this->assertDatabaseCount('wishlist_items', 0);
    Http::assertSentCount(1);
});
