<?php

use App\Services\PublicUrlResolver;

describe('public URL resolution', function () {
    it('rejects unsafe schemes, ports, userinfo, and local address forms before DNS', function () {
        $lookups = 0;
        $resolver = new PublicUrlResolver(static function (string $host) use (&$lookups): array {
            $lookups++;

            return ['93.184.216.34'];
        });

        foreach ([
            'file:///etc/passwd',
            'ftp://example.com/item',
            'http://user:pass@example.com/',
            'https://example.com:8443/',
            'http://127.0.0.1/',
            'http://127.1/',
            'http://0x7f000001/',
            'http://0177.0.0.1/',
            'http://10.0.0.1/',
            'http://169.254.169.254/latest/meta-data/',
            'http://100.64.0.1/',
            'http://198.18.0.1/',
            'http://192.0.2.1/',
            'http://[::1]/',
            'http://[::ffff:127.0.0.1]/',
            'http://[fd00::1]/',
            'http://[2001:db8::1]/',
            'http://[2002:7f00:1::1]/',
            'http://[64:ff9b::7f00:1]/',
            'http://[3fff::1]/',
            'http://localhost/',
            'http://service.internal/',
            'http://service.test/',
            'http://catalog.example.com./',
        ] as $url) {
            expect($resolver->resolve($url))->toBeNull($url);
        }

        expect($lookups)->toBe(0);
    });

    it('rejects the whole DNS answer set when any address is non-public', function () {
        $resolver = new PublicUrlResolver(static fn (string $host): array => [
            '93.184.216.34',
            '127.0.0.1',
        ]);

        expect($resolver->resolve('https://catalog.example.com/item'))->toBeNull();
    });

    it('returns every normalized public DNS address for transport pinning', function () {
        $addresses = ['93.184.216.34', '2606:4700:4700::1111'];
        $resolver = new PublicUrlResolver(static fn (string $host): array => $addresses);

        expect($resolver->resolve('https://catalog.example.com/item'))->toBe([
            'url' => 'https://catalog.example.com/item',
            'host' => 'catalog.example.com',
            'port' => 443,
            'addresses' => $addresses,
            'literal' => false,
        ]);
    });
});
