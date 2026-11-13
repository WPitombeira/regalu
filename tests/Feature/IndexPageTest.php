<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('renders the hero section', function () {
    $response = $this->get(route('home'));
    $response->assertStatus(200);
    $response->assertSee(__('messages.hero.headline'));
    $response->assertSee(__('messages.hero.subheadline'));
    $response->assertSee(__('messages.buttons.get_started'));
});

it('renders the features section', function () {
    $response = $this->get(route('home'));
    $response->assertStatus(200);
    $response->assertSee(__('messages.features.headline'));
    $response->assertSee(__('messages.features.subheadline'));
    $response->assertSee(__('messages.features.wishlist.headline'));
    $response->assertSee(__('messages.features.wishlist.subheadline'));
    $response->assertSee(__('messages.features.family_friends.headline'));
    $response->assertSee(__('messages.features.family_friends.subheadline'));
});

it('renders the privacy section', function () {
    $response = $this->get(route('home'));
    $response->assertStatus(200);
    $response->assertSee(__('messages.features.privacy.headline'));
    $response->assertSee(__('messages.features.privacy.subheadline'));
});

it('renders the newsletter section', function () {
    $response = $this->get(route('home'));
    $response->assertStatus(200);
    $response->assertSee(__('messages.newsletter.headline'));
    $response->assertSee(__('messages.newsletter.subheadline'));
    $response->assertSee(__('messages.buttons.notify_me'));
    $response->assertSee(__('newsletter.purpose'));
    $response->assertSee(__('newsletter.consent'));
});

it('renders the contact section', function () {
    $response = $this->get(route('home'));
    $response->assertStatus(200);
    $response->assertSee('Contact delivery is unavailable. Please do not enter a message; this form cannot send it.');
    $response->assertSee('Your name (optional)');
    $response->assertSee('Your email (optional)');
    $response->assertSee('Your message');
    $response->assertSee('Sending unavailable');
});

it('handles contact submission without falsely acknowledging delivery', function (string $alias, string $locale, string $message) {
    app()->setLocale($locale);
    Mail::fake();
    Queue::fake();

    Livewire::test($alias)
        ->set('feedback_name', 'Reader')
        ->set('feedback_email', 'reader@example.test')
        ->set('feedback_message', 'Please keep my message until it can be sent.')
        ->call('registerFeedback')
        ->assertStatus(200)
        ->assertHasErrors(['feedback'])
        ->assertHasNoErrors(['email', 'newsletterConsent'])
        ->assertSet('feedback_name', 'Reader')
        ->assertSet('feedback_email', 'reader@example.test')
        ->assertSet('feedback_message', 'Please keep my message until it can be sent.')
        ->assertSet('newsletterSubmitted', false)
        ->assertSee($message)
        ->assertSee('id="contact-error" role="alert"', false)
        ->assertNotDispatched('notification');

    $this->assertDatabaseCount('newsletters', 0);
    Mail::assertNothingOutgoing();
    Queue::assertNothingPushed();
})->with([
    'mounted English' => ['home.index', 'en', 'Contact delivery is unavailable. Your message has not been sent.'],
    'legacy English' => ['home', 'en', 'Contact delivery is unavailable. Your message has not been sent.'],
    'mounted Portuguese' => ['home.index', 'pt_BR', 'O envio de mensagens está indisponível. Sua mensagem não foi enviada.'],
    'legacy Portuguese' => ['home', 'pt_BR', 'O envio de mensagens está indisponível. Sua mensagem não foi enviada.'],
]);
