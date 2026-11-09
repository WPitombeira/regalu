<?php

use App\Services\PublicUrlResolver;
use App\Services\UrlMetaFetchService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

describe('URL metadata fetching', function () {
    it('refuses local file URLs without reading their contents', function () {
        $path = tempnam(sys_get_temp_dir(), 'regalu-meta-');
        file_put_contents($path, '<html><head><title>Local secret</title></head></html>');

        Http::preventStrayRequests();
        Http::fake();

        try {
            expect(app(UrlMetaFetchService::class)->fetch('file://'.$path))->toBe([]);
        } finally {
            @unlink($path);
        }
    });

    it('uses Laravel HTTP fakes to extract Open Graph metadata', function () {
        $url = 'https://metadata.invalid.example.com/product';
        $resolver = new PublicUrlResolver(static fn (string $host): array => ['93.184.216.34']);
        $this->app->instance(PublicUrlResolver::class, $resolver);

        Http::preventStrayRequests();
        Http::fake([
            $url => Http::response('<html><head><meta property="og:title" content="A &amp; B"><meta property="og:image" content="https://cdn.metadata.invalid.example.com/item.png"></head></html>'),
        ]);

        $unregistered = [];
        foreach (['http', 'https'] as $scheme) {
            if (stream_wrapper_unregister($scheme)) {
                $unregistered[] = $scheme;
            }
        }

        try {
            expect(app(UrlMetaFetchService::class)->fetch($url))->toBe([
                'title' => 'A & B',
                'image' => 'https://cdn.metadata.invalid.example.com/item.png',
            ]);
            Http::assertSent(fn ($request) => $request->url() === $url);
        } finally {
            foreach ($unregistered as $scheme) {
                stream_wrapper_restore($scheme);
            }
        }
    });

    it('falls back to a malformed document title and restores libxml error mode', function () {
        $url = 'https://catalog.example.com/fallback';
        $this->app->instance(PublicUrlResolver::class, new PublicUrlResolver(static fn (string $host): array => ['93.184.216.34']));
        Http::preventStrayRequests();
        Http::fake([$url => Http::response('<html><head><title>Fallback &amp; title')]);

        $previousErrorMode = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            expect(app(UrlMetaFetchService::class)->fetch($url))->toBe(['title' => 'Fallback & title']);
            expect(libxml_use_internal_errors())->toBeTrue();
            expect(libxml_get_errors())->toBe([]);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
    });

    it('discards unsafe image schemes while retaining decoded text metadata', function () {
        $url = 'https://catalog.example.com/unsafe-image';
        $this->app->instance(PublicUrlResolver::class, new PublicUrlResolver(static fn (string $host): array => ['93.184.216.34']));
        Http::preventStrayRequests();
        Http::fake([$url => Http::response('<meta property="og:title" content="A &amp; B"><meta property="og:image" content="data:image/svg+xml,%3Csvg%3E">')]);

        expect(app(UrlMetaFetchService::class)->fetch($url))->toBe(['title' => 'A & B']);
    });

    it('pins all validated DNS addresses and caches successful metadata under a hashed key', function () {
        $url = 'https://catalog.example.com/item?secret=not-a-cache-key';
        $addresses = ['93.184.216.34', '2606:4700:4700::1111'];
        $this->app->instance(PublicUrlResolver::class, new PublicUrlResolver(static fn (string $host): array => $addresses));
        Http::preventStrayRequests();

        $optionsSeen = [];
        Http::fake(function ($request, array $options) use (&$optionsSeen) {
            $optionsSeen = $options;

            return Http::response('<meta property="og:title" content="Pinned">');
        });

        $service = app(UrlMetaFetchService::class);
        $expected = ['title' => 'Pinned'];
        expect($service->fetch($url))->toBe($expected);
        expect($service->fetch($url))->toBe($expected);

        Http::assertSentCount(1);
        expect($optionsSeen['curl'][CURLOPT_RESOLVE])->toBe([
            'catalog.example.com:443:93.184.216.34,[2606:4700:4700::1111]',
        ]);
        expect($optionsSeen['allow_redirects'])->toBeFalse();
        expect($optionsSeen['proxy'])->toBe(['no' => '*']);

        $hashedKey = 'url-meta:v1:'.hash('sha256', $url);
        expect(Cache::get($hashedKey))->toBe($expected);
        expect(Cache::has('url-meta:v1:'.$url))->toBeFalse();
    });

    it('rejects HTTP failures, redirects, timeouts, and oversized response bodies', function () {
        $urls = [
            'https://catalog.example.com/error',
            'https://catalog.example.com/redirect',
            'https://catalog.example.com/timeout',
            'https://catalog.example.com/oversized',
        ];
        $this->app->instance(PublicUrlResolver::class, new PublicUrlResolver(static fn (string $host): array => ['93.184.216.34']));
        Http::preventStrayRequests();

        $sentUrls = [];
        $lastOptions = [];
        Http::fake(function ($request, array $options) use (&$sentUrls, &$lastOptions) {
            $sentUrls[] = $request->url();
            $lastOptions = $options;

            return match ($request->url()) {
                'https://catalog.example.com/error' => Http::response('unavailable', 503),
                'https://catalog.example.com/redirect' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin']),
                'https://catalog.example.com/timeout' => throw new ConnectionException('Timed out'),
                default => Http::response(str_repeat('x', 524289)),
            };
        });

        $service = app(UrlMetaFetchService::class);
        foreach ($urls as $url) {
            expect($service->fetch($url))->toBe([]);
        }

        expect($sentUrls)->toBe($urls);
        expect($lastOptions['timeout'])->toBe(5);
        expect($lastOptions['connect_timeout'])->toBe(2);
    });

    it('bounds rejected image resolution while retaining metadata and valid early candidates', function (?int $validImage) {
        $lookups = [];
        $this->app->instance(PublicUrlResolver::class, new PublicUrlResolver(function (string $host) use (&$lookups, $validImage): array {
            $lookups[] = $host;

            return $host === 'catalog.example.com' || $host === "image{$validImage}.example.com" ? ['93.184.216.34'] : [];
        }));
        $html = '<html><head>';
        for ($i = 1; $i <= 1000; $i++) {
            $html .= '<meta property="og:image" content="https://image'.$i.'.example.com/image.png">';
        }
        $html .= '<meta property="og:title" content="Title after all image candidates"></head></html>';
        expect(strlen($html))->toBeLessThan(524288);
        Http::preventStrayRequests();
        Http::fake(['https://catalog.example.com/bounded-images' => Http::response($html)]);

        $expected = ['title' => 'Title after all image candidates'];
        if ($validImage === 3) {
            $expected = ['image' => 'https://image3.example.com/image.png', ...$expected];
        }
        expect(app(UrlMetaFetchService::class)->fetch('https://catalog.example.com/bounded-images'))->toBe($expected);
        expect($lookups)->toHaveCount(4);
        expect($lookups)->toBe(['catalog.example.com', 'image1.example.com', 'image2.example.com', 'image3.example.com']);
        Http::assertSentCount(1);
    })->with(['all rejected' => null, 'valid within budget' => 3, 'valid beyond budget' => 4]);

    it('rejects mixed public and private DNS answers before making a request', function () {
        $url = 'https://catalog.example.com/rebinding';
        $this->app->instance(PublicUrlResolver::class, new PublicUrlResolver(static fn (string $host): array => ['93.184.216.34', '127.0.0.1']));
        Http::preventStrayRequests();
        Http::fake();

        expect(app(UrlMetaFetchService::class)->fetch($url))->toBe([]);
        Http::assertNothingSent();
    });
});
