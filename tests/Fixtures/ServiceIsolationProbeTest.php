<?php

use Illuminate\Cache\ArrayStore;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

// Explicit subprocess-only probe. Never connect to inherited service endpoints.
if (getenv('REGALU_CACHE_PROBE_OVERRIDE')) {
    putenv('CACHE_STORE=redis');
    $_ENV['CACHE_STORE'] = $_SERVER['CACHE_STORE'] = 'redis';
}

pest()->extend(TestCase::class)->in(__FILE__);

it('boots only isolated cache, session, queue and mail services', function () {
    expect(config('cache.default'))->toBe('array');
    expect(config('session.driver'))->toBe('array');
    expect(config('queue.default'))->toBe('sync');
    expect(config('mail.default'))->toBe('array');
    expect(Cache::store()->getStore())->toBeInstanceOf(ArrayStore::class);
    Cache::put('isolation-sentinel', 'untouched');
    expect(Cache::get('isolation-sentinel'))->toBe('untouched');
});
