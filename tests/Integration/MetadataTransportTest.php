<?php

use App\Services\PublicUrlResolver;
use App\Services\UrlMetaFetchService;

it('uses real cURL pinning without proxies and bounds redirects and unknown-length bodies', function (string $scenario) {
    $process = proc_open([PHP_BINARY, base_path('tests/Fixtures/metadata-server.php'), $scenario], [
        0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $pipes);
    expect(is_resource($process))->toBeTrue();
    fclose($pipes[0]);
    stream_set_timeout($pipes[1], 10);
    $proxyNames = ['HTTP_PROXY', 'http_proxy', 'ALL_PROXY', 'all_proxy'];
    $originalProxies = [];
    foreach ($proxyNames as $name) {
        $originalProxies[$name] = getenv($name);
        putenv($name.'=http://127.0.0.1:1');
    }

    try {
        $ready = json_decode(fgets($pipes[1]), true, flags: JSON_THROW_ON_ERROR);
        $port = $ready['port'];
        // Deliberately replace only DNS validation for this transport-boundary probe.
        // Production private-address rejection is covered separately; no real DNS or internet is used.
        $resolver = new class($port) extends PublicUrlResolver
        {
            public function __construct(private int $port) {}

            public function resolve(string $url): ?array
            {
                return ['url' => $url, 'host' => 'metadata-transport.invalid', 'port' => $this->port,
                    'addresses' => ['127.0.0.1'], 'literal' => false];
            }
        };
        $metadata = (new UrlMetaFetchService($resolver))->fetch("http://metadata-transport.invalid:{$port}/{$scenario}");
        $observed = json_decode(fgets($pipes[1]), true, flags: JSON_THROW_ON_ERROR);
        expect($observed['request'])->toContain("Host: metadata-transport.invalid:{$port}")
            ->and($observed['requests'])->toBe(1);
        if ($scenario === 'valid') {
            expect($metadata)->toBe(['title' => 'Native transport']);
        } else {
            expect($metadata)->toBe([]);
        }
        if ($scenario === 'oversized') {
            // The server observes an abort before it can write its full 8 MiB response.
            expect($observed['written'])->toBeLessThan(8 * 1024 * 1024);
        }
        expect(stream_get_contents($pipes[2]))->toBe('');
    } finally {
        foreach ($originalProxies as $name => $value) {
            putenv($value === false ? $name : $name.'='.$value);
        }
        proc_terminate($process);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    }
})->with(['valid', 'redirect', 'oversized']);
