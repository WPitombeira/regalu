<?php

use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('renders the public newsletter entry point in Portuguese', function () {
    $this->withoutVite();
    app()->setLocale('pt_BR');
    $this->get(route('home'))->assertOk()
        ->assertSee('Inscreva-se na nossa newsletter.');
});

it('keeps newsletter catalogs complete in both supported locales', function () {
    $english = require lang_path('en/newsletter.php');
    $portuguese = require lang_path('pt_BR/newsletter.php');
    expect(array_keys($portuguese))->toBe(array_keys($english));
    foreach ([$english, $portuguese] as $catalog) {
        foreach ($catalog as $text) {
            expect($text)->toBeString()->not->toBe('');
        }
    }
});

it('renders a labeled newsletter form with explicit localized consent', function (string $locale, string $label, string $consent, string $purpose, string $button) {
    app()->setLocale($locale);
    $component = Livewire::test('home.index');
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$component->html());
    $xpath = new DOMXPath($document);
    $form = $xpath->query('//section[@id="newsletter"]//form')->item(0);
    expect($form)->not->toBeNull();
    expect($form->textContent)->toContain($label, $consent, $purpose, $button);
    $input = $xpath->query('.//input[@type="email"]', $form)->item(0);
    expect($input)->not->toBeNull();
    assert($input instanceof DOMElement);
    expect($input->getAttribute('id'))->not->toBe('');
    expect($xpath->query('.//label[@for="'.$input->getAttribute('id').'"]', $form)->length)->toBe(1);
    expect($input->getAttribute('maxlength'))->toBe('254');
    expect($input->getAttribute('autocomplete'))->toBe('email');
    expect($input->hasAttribute('required'))->toBeTrue();
    $checkbox = $xpath->query('.//input[@type="checkbox"]', $form)->item(0);
    expect($checkbox)->not->toBeNull();
    assert($checkbox instanceof DOMElement);
    expect($checkbox->hasAttribute('checked'))->toBeFalse();
    expect($checkbox->hasAttribute('required'))->toBeTrue();
    expect($checkbox->getAttribute('id'))->not->toBe('');
    expect($xpath->query('.//label[@for="'.$checkbox->getAttribute('id').'"]', $form)->length)->toBe(1);
    expect($xpath->query('.//a[@href="#"]', $form)->length)->toBe(0);
    $submit = $xpath->query('.//button[@type="submit"]', $form)->item(0);
    assert($submit instanceof DOMElement);
    expect($submit->getAttribute('wire:loading.attr'))->toBe('disabled');
    expect($submit->getAttribute('wire:target'))->toBe('registerNewsletter');
})->with([
    ['en', 'Email address', 'I want to receive Regalu product updates by email.', 'We store your email address to record your interest in product updates.', 'Notify Me'],
    ['pt_BR', 'Endereço de e-mail', 'Quero receber novidades do Regalu por e-mail.', 'Armazenamos seu e-mail para registrar seu interesse nas novidades do produto.', 'Notifique-me'],
]);

it('renders localized newsletter validation linked to the invalid control', function (string $locale, string $email, bool $consent, string $field, string $message) {
    app()->setLocale($locale);
    $component = Livewire::test('home.index')->set('email', $email)->set('newsletterConsent', $consent)
        ->call('registerNewsletter')->assertHasErrors([$field])->assertSee($message);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$component->html());
    $xpath = new DOMXPath($document);
    $id = $field === 'email' ? 'newsletter-email' : 'newsletter-consent';
    $input = $xpath->query('//*[@id="'.$id.'"]')->item(0);
    assert($input instanceof DOMElement);
    expect($input->getAttribute('aria-invalid'))->toBe('true');
    expect(explode(' ', $input->getAttribute('aria-describedby')))->toContain($id.'-error');
    $error = $xpath->query('//*[@id="'.$id.'-error" and @role="alert"]')->item(0);
    expect($error)->not->toBeNull();
    expect(trim($error->textContent))->toBe($message);
    $this->assertDatabaseCount('newsletters', 0);
})->with([
    ['en', '', true, 'email', 'Enter your email address.'],
    ['en', 'invalid', true, 'email', 'Enter a valid email address.'],
    ['en', str_repeat('a', 255).'@example.test', true, 'email', 'Use an email address with no more than 254 characters.'],
    ['en', 'reader@example.test', false, 'newsletterConsent', 'Please agree to receive product updates before signing up.'],
    ['pt_BR', '', true, 'email', 'Informe seu endereço de e-mail.'],
    ['pt_BR', 'invalid', true, 'email', 'Informe um endereço de e-mail válido.'],
    ['pt_BR', str_repeat('a', 255).'@example.test', true, 'email', 'Use um endereço de e-mail com no máximo 254 caracteres.'],
    ['pt_BR', 'reader@example.test', false, 'newsletterConsent', 'Concorde em receber novidades do produto antes de se inscrever.'],
]);

it('renders a localized live receipt only after an accepted newsletter request', function (string $locale, string $message) {
    app()->setLocale($locale);
    $component = Livewire::test('home.index')->assertDontSee($message)
        ->set('email', 'reader@example.test')->set('newsletterConsent', true)
        ->call('registerNewsletter')->assertHasNoErrors()->assertSee($message);
    $component->assertSeeHtml('role="status"')->assertSeeHtml('aria-live="polite"');
    $component->set('email', 'another@example.test')->assertDontSee($message);
})->with([
    ['en', 'Thanks. Your request has been received. Existing subscription preferences are unchanged.'],
    ['pt_BR', 'Obrigado. Recebemos sua solicitação. As preferências de inscrições existentes não foram alteradas.'],
]);

it('renders a localized recoverable newsletter form failure without internal details', function (string $locale, string $cause, string $message) {
    app()->setLocale($locale);
    if ($cause === 'cache') {
        config(['cache.limiter' => 'unconfigured-newsletter-test-store']);
    } else {
        DB::statement("CREATE TRIGGER reject_newsletter BEFORE INSERT ON newsletters BEGIN SELECT RAISE(FAIL, 'synthetic persistence failure'); END");
    }
    try {
        Livewire::test('home.index')->set('email', 'reader@example.test')->set('newsletterConsent', true)
            ->call('registerNewsletter')->assertHasErrors(['newsletter'])->assertSee($message)
            ->assertSeeHtml('id="newsletter-error" role="alert"')
            ->assertSet('newsletterSubmitted', false)->assertDontSee('SQLSTATE')
            ->assertDontSee('synthetic persistence failure')->assertDontSee('unconfigured-newsletter-test-store');
        $this->assertDatabaseCount('newsletters', 0);
    } finally {
        if ($cause !== 'cache') {
            DB::statement('DROP TRIGGER reject_newsletter');
        }
    }
})->with([
    ['en', 'cache', 'We cannot accept another request right now. Please wait a minute and try again.'],
    ['pt_BR', 'cache', 'Não podemos aceitar outra solicitação agora. Aguarde um minuto e tente novamente.'],
    ['en', 'database', 'We could not save your request. Please try again later.'],
    ['pt_BR', 'database', 'Não foi possível salvar sua solicitação. Tente novamente mais tarde.'],
]);
