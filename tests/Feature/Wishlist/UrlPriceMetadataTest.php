<?php

use App\Services\PublicUrlResolver;
use App\Services\UrlMetaFetchService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->app->instance(PublicUrlResolver::class, new PublicUrlResolver(static fn (string $host): array => ['93.184.216.34']));
    Http::preventStrayRequests();
});

it('imports an explicit product offer amount together with its currency', function () {
    $url = 'https://catalog.example.com/gift';
    Http::fake([$url => Http::response(<<<'HTML'
        <title>A thoughtful gift</title>
        <script type="application/ld+json">
        {"@context":"https://schema.org","@type":"Product","offers":{"@type":"Offer","price":"129.90","priceCurrency":"BRL"}}
        </script>
        HTML)]);

    expect(app(UrlMetaFetchService::class)->fetch($url))->toBe([
        'title' => 'A thoughtful gift',
        'price' => '129.90',
        'currency' => 'BRL',
    ]);
    Http::assertSentCount(1);
});

it('does not guess which product a multi-product page describes', function () {
    $url = 'https://catalog.example.com/collection';
    Http::fake([$url => Http::response(<<<'HTML'
        <title>Choose a gift</title>
        <script type="application/ld+json">{"@type":"Product","offers":{"@type":"Offer","price":"10.00","priceCurrency":"BRL"}}</script>
        <script type="application/ld+json">{"@type":"Product","offers":{"@type":"Offer","price":"20.00","priceCurrency":"BRL"}}</script>
        HTML)]);

    expect(app(UrlMetaFetchService::class)->fetch($url))->toBe(['title' => 'Choose a gift']);
});

it('reads a single product offer in common JSON-LD containers', function (array $data) {
    $url = 'https://catalog.example.com/structured';
    Http::fake([$url => Http::response('<script type="application/ld+json">'.json_encode($data).'</script>')]);

    expect(app(UrlMetaFetchService::class)->fetch($url))->toBe(['price' => '9.50', 'currency' => 'EUR']);
})->with([
    'graph' => [['@graph' => [['@type' => 'WebSite'], ['@type' => 'Product', 'offers' => ['@type' => 'Offer', 'price' => '9.5', 'priceCurrency' => 'EUR']]]]],
    'top-level list' => [[['@type' => 'Product', 'offers' => ['@type' => 'Offer', 'price' => '9.5', 'priceCurrency' => 'EUR']]]],
    'type and offer lists' => [['@type' => ['Thing', 'https://schema.org/Product'], 'offers' => [['@type' => 'http://schema.org/Offer', 'price' => '9.5', 'priceCurrency' => 'EUR']]]],
]);

it('keeps title fallback when structured data is malformed or exceeds its parsing budget', function (string $extra) {
    $url = 'https://catalog.example.com/untrusted';
    $product = '<script type="application/ld+json">{"@type":"Product","offers":{"@type":"Offer","price":"10.00","priceCurrency":"USD"}}</script>';
    Http::fake([$url => Http::response('<title>Manual gift</title>'.$product.$extra)]);

    expect(app(UrlMetaFetchService::class)->fetch($url))->toBe(['title' => 'Manual gift']);
})->with([
    'too many scripts' => str_repeat('<script type="application/ld+json">{}</script>', 10),
    'too many nodes' => '<script type="application/ld+json">'.json_encode(array_fill(0, 300, ['@type' => 'Thing'])).'</script>',
    'broken JSON' => '<script type="application/ld+json">{not json</script>',
    'invalid type shape' => '<script type="application/ld+json">{"@type":[{"name":"Product"}]}</script>',
]);

it('normalizes JSON numeric amounts without converting currency or rounding precision', function (mixed $amount, ?string $expected) {
    $url = 'https://catalog.example.com/amount';
    $data = ['@type' => 'Product', 'offers' => ['@type' => 'Offer', 'price' => $amount, 'priceCurrency' => 'USD']];
    Http::fake([$url => Http::response('<title>Gift</title><script type="application/ld+json">'.json_encode($data).'</script>')]);

    expect(app(UrlMetaFetchService::class)->fetch($url))->toBe($expected === null
        ? ['title' => 'Gift']
        : ['title' => 'Gift', 'price' => $expected, 'currency' => 'USD']);
})->with([
    'zero' => [0, '0.00'],
    'integer' => [12, '12.00'],
    'decimal' => [12.34, '12.34'],
    'maximum' => ['99999999.99', '99999999.99'],
    'too large' => ['100000000.00', null],
    'fractional cents' => [12.345, null],
    'negative' => ['-1', null],
    'localized string' => ['1.234,56', null],
    'scientific string' => ['1e2', null],
    'boolean' => [true, null],
    'missing' => [null, null],
    'object' => [['value' => '10'], null],
]);

it('preserves the original numeric price token instead of rounding through a float', function (string $token, ?string $expected) {
    $url = 'https://catalog.example.com/precise';
    $json = '{"@type":"Product","description":"price: 99.99","offers":{"@type":"Offer","pr\\u0069ce":'.$token.',"priceCurrency":"USD"}}';
    Http::fake([$url => Http::response('<title>Gift</title><script type="application/ld+json">'.$json.'</script>')]);

    expect(app(UrlMetaFetchService::class)->fetch($url))->toBe($expected === null
        ? ['title' => 'Gift']
        : ['title' => 'Gift', 'price' => $expected, 'currency' => 'USD']);
})->with([
    'fraction rounded by string cast' => ['12.340000000000001', null],
    'fraction beyond floating point precision' => ['12.34000000000000000000001', null],
    'numeric exponent' => ['1e2', null],
    'exact decimal' => ['12.34', '12.34'],
    'exact upper bound' => ['99999999.99', '99999999.99'],
]);

it('uses explicit product meta prices only when the page is unambiguous', function (string $html, array $quote) {
    $url = 'https://catalog.example.com/meta';
    Http::fake([$url => Http::response('<title>Gift</title>'.$html)]);

    expect(app(UrlMetaFetchService::class)->fetch($url))->toBe(['title' => 'Gift', ...$quote]);
})->with([
    'explicit pair' => ['<meta property="product:price:amount" content="45.6"><meta property="product:price:currency" content="BRL">', ['price' => '45.60', 'currency' => 'BRL']],
    'no currency' => ['<meta property="product:price:amount" content="45.6">', []],
    'unknown currency' => ['<meta property="product:price:amount" content="45.6"><meta property="product:price:currency" content="JPY">', []],
    'duplicate amount' => ['<meta property="product:price:amount" content="45.6"><meta property="product:price:amount" content="50"><meta property="product:price:currency" content="BRL">', []],
    'no guessing from prose' => ['<p>R$ 45,60</p><meta itemprop="price" content="45.6">', []],
    'structured conflict' => ['<meta property="product:price:amount" content="45.6"><meta property="product:price:currency" content="BRL"><script type="application/ld+json">{"@type":"Product","offers":{"@type":"Offer","price":"99.00","priceCurrency":"USD"}}</script>', []],
    'variant group' => ['<meta property="product:price:amount" content="45.6"><meta property="product:price:currency" content="BRL"><script type="application/ld+json">{"@type":"ProductGroup"}</script>', []],
    'aggregate offer' => ['<meta property="product:price:amount" content="45.6"><meta property="product:price:currency" content="BRL"><script type="application/ld+json">{"@type":"Product","offers":{"@type":"AggregateOffer","lowPrice":"45.60","highPrice":"100.00","priceCurrency":"BRL"}}</script>', []],
    'multiple offers' => ['<script type="application/ld+json">{"@type":"Product","offers":[{"@type":"Offer","price":"10.00","priceCurrency":"BRL"},{"@type":"Offer","price":"20.00","priceCurrency":"BRL"}]}</script>', []],
    'product with conflicting group type' => ['<script type="application/ld+json">{"@type":["Product","ProductGroup"],"offers":{"@type":"Offer","price":"10.00","priceCurrency":"BRL"}}</script>', []],
    'offer with conflicting aggregate type' => ['<script type="application/ld+json">{"@type":"Product","offers":{"@type":["Offer","AggregateOffer"],"price":"10.00","priceCurrency":"BRL"}}</script>', []],
]);

it('caches validated price currency pairs without reusing the title-only cache generation', function (array $cached) {
    $url = 'https://catalog.example.com/cached';
    $key = hash('sha256', $url);
    Cache::put('url-meta:v1:'.$key, ['title' => 'Legacy title']);
    Cache::put('url-meta:v2:'.$key, $cached);
    Http::fake([$url => Http::response('<title>New title</title><meta property="product:price:amount" content="10"><meta property="product:price:currency" content="BRL">')]);

    $expected = ['title' => 'New title', 'price' => '10.00', 'currency' => 'BRL'];
    $service = app(UrlMetaFetchService::class);
    expect($service->fetch($url))->toBe($expected)
        ->and($service->fetch($url))->toBe($expected)
        ->and(Cache::get('url-meta:v2:'.$key))->toBe($expected);
    Http::assertSentCount(1);
})->with([
    'empty' => [[]],
    'orphan price' => [['price' => '10.00']],
    'orphan currency' => [['currency' => 'BRL']],
    'unsupported currency' => [['price' => '10.00', 'currency' => 'ZZZ']],
    'noncanonical amount' => [['price' => '1e2', 'currency' => 'BRL']],
    'unknown key' => [['price' => '10.00', 'currency' => 'BRL', 'unsafe' => 'extra']],
]);
