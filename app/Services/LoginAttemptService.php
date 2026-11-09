<?php

namespace App\Services;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class LoginAttemptService
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function attempt(string $email, string $password, bool $remember, ?string $ipAddress): bool
    {
        $credentials = ['email' => $email, 'password' => $password];
        $user = Auth::getProvider()->retrieveByCredentials(['email' => $email]);
        $identity = $user === null
            ? 'email:'.strtolower(trim($email))
            : 'user:'.$user->getAuthIdentifier();

        if ($user !== null) {
            // Bind the credential check to the same account that owns the reservation,
            // even if the email changes between the provider's two lookups.
            $credentials[$user->getAuthIdentifierName()] = $user->getAuthIdentifier();
        }

        $key = $this->limiterKey($identity, $ipAddress);
        $storeName = config('cache.limiter');
        $cache = Cache::store(is_string($storeName) ? $storeName : null);
        $store = $cache->getStore();

        if (! $store instanceof LockProvider) {
            return false;
        }

        // Reserve before password verification. The shared lock also protects stores
        // whose increments are not atomic, and counter initialization/expiry races.
        $limiter = new RateLimiter($cache);
        $reserved = $store->lock($key.':reservation', 10)->get(function () use ($limiter, $key): bool {
            $attempts = $limiter->hit($key, self::DECAY_SECONDS);

            return $attempts > 0 && $attempts <= self::MAX_ATTEMPTS;
        });

        if ($reserved !== true || ! Auth::attempt($credentials, $remember)) {
            return false;
        }

        $store->lock($key.':reservation', 10)->get(fn () => $limiter->clear($key));

        return true;
    }

    private function limiterKey(string $identity, ?string $ipAddress): string
    {
        return 'auth:login:'.hash('sha256', Auth::getDefaultDriver()."\0".$identity."\0".($ipAddress ?? ''));
    }
}
