<?php

use App\Models\Newsletter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

it('acknowledges valid requests without exposing existing subscription state or sending mail', function (?bool $active) {
    Mail::fake();
    if ($active !== null) {
        Newsletter::factory()->create(['email' => 'reader@example.test', 'active' => $active]);
    }

    Livewire::test('home.index')
        ->set('email', 'reader@example.test')
        ->set('newsletterConsent', true)
        ->call('registerNewsletter')
        ->assertHasNoErrors()
        ->assertSet('newsletterSubmitted', true)
        ->assertSet('email', '')
        ->assertSet('newsletterConsent', false);

    $this->assertDatabaseCount('newsletters', 1);
    $this->assertDatabaseHas('newsletters', ['email' => 'reader@example.test', 'active' => $active ?? true]);
    Mail::assertNothingOutgoing();
})->with(['new' => null, 'active' => true, 'inactive' => false]);

it('clears an old receipt when a new signup is edited or rejected', function (?string $property) {
    $component = Livewire::test('home.index')
        ->set('email', 'reader@example.test')
        ->set('newsletterConsent', true)
        ->call('registerNewsletter')
        ->assertSet('newsletterSubmitted', true);

    if ($property !== null) {
        $component->set($property, $property === 'email' ? 'invalid' : true);
    } else {
        $component->call('registerNewsletter')->assertHasErrors(['email', 'newsletterConsent']);
    }
    $component->assertSet('newsletterSubmitted', false);
    $this->assertDatabaseCount('newsletters', 1);
})->with(['email edit' => 'email', 'consent edit' => 'newsletterConsent', 'empty retry' => null]);

it('does not allow clients to forge a newsletter receipt', function () {
    expect(fn () => Livewire::test('home.index')->set('newsletterSubmitted', true))
        ->toThrow(CannotUpdateLockedPropertyException::class);
    $this->assertDatabaseCount('newsletters', 0);
});

it('reports a persistence failure without claiming success or disclosing database details', function () {
    DB::statement("CREATE TRIGGER reject_newsletter BEFORE INSERT ON newsletters BEGIN SELECT RAISE(FAIL, 'synthetic persistence failure'); END");

    try {
        Livewire::test('home.index')
            ->set('email', 'reader@example.test')
            ->set('newsletterConsent', true)
            ->call('registerNewsletter')
            ->assertHasErrors(['newsletter'])
            ->assertSet('newsletterSubmitted', false)
            ->assertSet('email', 'reader@example.test')
            ->assertDontSee('synthetic persistence failure')
            ->assertDontSee('SQLSTATE');
        $this->assertDatabaseCount('newsletters', 0);
    } finally {
        DB::statement('DROP TRIGGER reject_newsletter');
    }
});
