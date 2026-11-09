<?php

use App\Models\User;
use App\Services\LoginAttemptService;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

it('reserves the fifth attempt before overlapping requests can check credentials', function () {
    $user = User::factory()->create(['email' => 'overlap@example.com']);
    $service = app(LoginAttemptService::class);
    foreach (range(1, 4) as $attempt) {
        expect($service->attempt($user->email, 'incorrect', false, '192.0.2.1'))->toBeFalse();
    }

    $credentialChecks = 0;
    Event::listen(Attempting::class, function () use (&$credentialChecks, $service, $user) {
        $credentialChecks++;
        if ($credentialChecks === 1) {
            // Enter a second request while the fifth is inside the authentication guard.
            expect($service->attempt($user->email, 'incorrect', false, '192.0.2.1'))->toBeFalse();
        }
    });
    expect($service->attempt($user->email, 'incorrect', false, '192.0.2.1'))->toBeFalse();
    expect($credentialChecks)->toBe(1);
    $this->assertGuest();
});

it('expires reservations without blocking another address and clears successful attempts', function () {
    $user = User::factory()->create(['password' => 'correct-password']);
    $service = app(LoginAttemptService::class);
    foreach (range(1, 5) as $attempt) {
        expect($service->attempt($user->email, 'incorrect', false, '192.0.2.1'))->toBeFalse();
    }
    expect($service->attempt($user->email, 'correct-password', false, '192.0.2.1'))->toBeFalse();
    expect($service->attempt($user->email, 'correct-password', false, '192.0.2.2'))->toBeTrue();
    Auth::logout();
    $this->travel(61)->seconds();
    expect($service->attempt($user->email, 'correct-password', true, '192.0.2.1'))->toBeTrue();
    Auth::logout();
    foreach (range(1, 4) as $attempt) {
        expect($service->attempt($user->email, 'incorrect', false, '192.0.2.1'))->toBeFalse();
    }
    expect($service->attempt($user->email, 'correct-password', false, '192.0.2.1'))->toBeTrue();
});
