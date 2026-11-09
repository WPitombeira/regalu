<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class LoginAttemptService
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function attempt(string $email, string $password, bool $remember, ?string $ipAddress): bool
    {
        $key = $this->limiterKey($email, $ipAddress);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return false;
        }

        if (Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
            RateLimiter::clear($key);

            return true;
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);

        return false;
    }

    private function limiterKey(string $email, ?string $ipAddress): string
    {
        return 'auth:login:'.hash('sha256', strtolower(trim($email))."\0".($ipAddress ?? ''));
    }
}
