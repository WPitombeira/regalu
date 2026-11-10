<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Exercise the real middleware, disabling only its test-environment bypass.
    app()->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });
    Route::middleware('web')->post('/_test/forgery', fn () => response('accepted'));
});

it('rejects cross-site writes without a valid CSRF token', function () {
    $this->withHeader('Sec-Fetch-Site', 'cross-site')->post('/_test/forgery')->assertStatus(419);
});

it('accepts same-origin writes through Laravel 13 origin verification', function () {
    $this->withHeader('Sec-Fetch-Site', 'same-origin')->post('/_test/forgery')->assertOk();
});

it('retains token fallback for clients without fetch metadata', function () {
    $this->withSession(['_token' => 'test-csrf-token'])
        ->post('/_test/forgery', ['_token' => 'test-csrf-token'])->assertOk();
});
