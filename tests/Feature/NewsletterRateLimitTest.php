<?php

use App\Services\NewsletterSignupLimiter;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

it('bounds newsletter attempts per source even across new components', function (bool $valid) {
    foreach (range(1, 5) as $attempt) {
        $component = Livewire::test('home.index')
            ->set('email', $valid ? "reader{$attempt}@example.test" : 'invalid')
            ->set('newsletterConsent', true)
            ->call('registerNewsletter');

        $valid ? $component->assertHasNoErrors() : $component->assertHasErrors(['email']);
    }

    Livewire::test('home.index')
        ->set('email', 'blocked@example.test')
        ->set('newsletterConsent', true)
        ->call('registerNewsletter')
        ->assertHasErrors(['newsletter']);
    $this->assertDatabaseMissing('newsletters', ['email' => 'blocked@example.test']);
    $this->assertDatabaseCount('newsletters', $valid ? 5 : 0);

    $this->travel(61)->seconds();
    Livewire::test('home.index')
        ->set('email', 'later@example.test')
        ->set('newsletterConsent', true)
        ->call('registerNewsletter')
        ->assertHasNoErrors();
    $this->assertDatabaseHas('newsletters', ['email' => 'later@example.test']);
})->with(['valid attempts' => true, 'invalid attempts' => false]);

it('fails closed with a form error when the limiter cannot be resolved', function () {
    config(['cache.limiter' => 'missing-newsletter-store']);

    Livewire::test('home.index')
        ->set('email', 'reader@example.test')
        ->set('newsletterConsent', true)
        ->call('registerNewsletter')
        ->assertHasErrors(['newsletter'])
        ->assertDontSee('missing-newsletter-store');

    $this->assertDatabaseCount('newsletters', 0);
});

it('does not admit a signup while its source reservation is held', function () {
    $key = 'newsletter:signup:'.hash('sha256', '127.0.0.1').':reservation';
    $lock = Cache::store('array')->lock($key, 10);
    expect($lock->get())->toBeTrue();

    try {
        Livewire::test('home.index')
            ->set('email', 'reader@example.test')
            ->set('newsletterConsent', true)
            ->call('registerNewsletter')
            ->assertHasErrors(['newsletter']);
        $this->assertDatabaseCount('newsletters', 0);
    } finally {
        $lock->release();
    }

    Livewire::test('home.index')
        ->set('email', 'reader@example.test')
        ->set('newsletterConsent', true)
        ->call('registerNewsletter')
        ->assertHasNoErrors();
    $this->assertDatabaseCount('newsletters', 1);
});

it('keeps independent source budgets and counts missing addresses in one fallback bucket', function () {
    $limiter = app(NewsletterSignupLimiter::class);
    foreach (range(1, 5) as $attempt) {
        expect($limiter->reserve('192.0.2.1'))->toBeTrue();
        expect($limiter->reserve(null))->toBeTrue();
    }
    expect($limiter->reserve('192.0.2.1'))->toBeFalse();
    expect($limiter->reserve(null))->toBeFalse();
    expect($limiter->reserve('192.0.2.2'))->toBeTrue();
});
