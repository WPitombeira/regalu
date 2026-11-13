<?php

use App\Livewire\Home\Index;
use App\Models\Newsletter;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('stores a newsletter signup through the mounted home component', function () {
    $this->withoutVite();
    $this->get(route('home'))->assertOk()->assertSeeLivewire('home.index');

    $component = Livewire::test('home.index');
    expect($component->instance())->toBeInstanceOf(Index::class);

    $component
        ->set('email', 'reader@example.test')
        ->set('newsletterConsent', true)
        ->call('registerNewsletter')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('newsletters', ['email' => 'reader@example.test', 'active' => true]);
});

it('requires explicit newsletter consent before persisting an address', function () {
    Livewire::test('home.index')
        ->set('email', 'reader@example.test')
        ->call('registerNewsletter')
        ->assertHasErrors(['newsletterConsent' => 'accepted']);

    $this->assertDatabaseCount('newsletters', 0);
});

it('validates the newsletter address independently of the contact form', function (string $email, string $rule) {
    Livewire::test('home.index')
        ->set('email', $email)
        ->set('newsletterConsent', true)
        ->call('registerNewsletter')
        ->assertHasErrors(['email' => $rule])
        ->assertHasNoErrors(['feedback_message', 'feedback_email', 'feedback_name']);

    $this->assertDatabaseCount('newsletters', 0);
})->with([
    'empty' => ['', 'required'],
    'malformed' => ['not-an-email', 'email'],
    'too long' => [str_repeat('a', 255).'@example.test', 'max'],
]);

it('normalizes signup retries without rewriting existing subscriptions', function (?bool $active, string $storedEmail) {
    $existing = $active === null ? null : Newsletter::factory()->create([
        'email' => $storedEmail,
        'active' => $active,
        'created_at' => now()->subMonth(),
        'updated_at' => now()->subMonth(),
    ]);
    $original = $existing?->fresh()->getAttributes();

    foreach (['  Reader@Example.Test  ', 'reader@example.test'] as $email) {
        Livewire::test('home.index')
            ->set('email', $email)
            ->set('newsletterConsent', true)
            ->call('registerNewsletter')
            ->assertHasNoErrors();
    }

    $this->assertDatabaseCount('newsletters', 1);
    if ($existing) {
        expect($existing->fresh()->getAttributes())->toBe($original);
    } else {
        $this->assertDatabaseHas('newsletters', ['email' => 'reader@example.test', 'active' => true]);
    }
})->with([
    'new' => [null, ''],
    'active' => [true, 'reader@example.test'],
    'inactive' => [false, 'reader@example.test'],
    'legacy mixed case inactive' => [false, 'Reader@Example.Test'],
]);

it('does not allow the legacy home alias to bypass explicit consent', function () {
    Livewire::test('home')
        ->set('email', 'reader@example.test')
        ->call('registerNewsletter')
        ->assertHasErrors(['newsletterConsent' => 'accepted']);
    $this->assertDatabaseCount('newsletters', 0);

    Livewire::test('home')
        ->set('email', 'reader@example.test')
        ->set('newsletterConsent', true)
        ->call('registerNewsletter')
        ->assertHasNoErrors();
    $this->assertDatabaseCount('newsletters', 1);
});

it('preserves a subscription inserted after the preflight lookup', function () {
    $fired = false;
    $original = null;
    // Inject after SELECT, outside createOrFirst's rollback savepoint. This is
    // a controlled single-connection interleaving, not concurrent-commit proof.
    DB::listen(function (QueryExecuted $query) use (&$fired, &$original): void {
        if ($fired || ! str_contains($query->sql, 'LOWER(email)') || ! str_contains($query->sql, '"newsletters"')) {
            return;
        }
        $fired = true;
        $original = Newsletter::factory()->create([
            'email' => 'reader@example.test', 'active' => false,
            'created_at' => now()->subMonth(), 'updated_at' => now()->subMonth(),
        ])->fresh()->getAttributes();
    });

    Livewire::test('home.index')->set('email', 'Reader@Example.Test')->set('newsletterConsent', true)
        ->call('registerNewsletter')->assertHasNoErrors()->assertSet('newsletterSubmitted', true);

    expect($fired)->toBeTrue();
    $this->assertDatabaseCount('newsletters', 1);
    expect(Newsletter::query()->sole()->getAttributes())->toBe($original);
});
