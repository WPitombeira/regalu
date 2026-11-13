<?php

namespace App\Services;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Throwable;

class NewsletterSignupLimiter
{
    public function reserve(?string $ipAddress): bool
    {
        try {
            $key = 'newsletter:signup:'.hash('sha256', $ipAddress ?? '');
            $storeName = config('cache.limiter');
            $cache = Cache::store(is_string($storeName) ? $storeName : null);
            $store = $cache->getStore();

            if (! $store instanceof LockProvider) {
                return false;
            }

            $limiter = new RateLimiter($cache);

            return $store->lock($key.':reservation', 10)->get(function () use ($limiter, $key): bool {
                $attempts = $limiter->hit($key, 60);

                return $attempts > 0 && $attempts <= 5;
            }) === true;
        } catch (Throwable) {
            // No persistence when admission cannot be established. Do not expose cache details.
            return false;
        }
    }
}
