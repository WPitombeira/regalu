<?php

use App\Livewire\Home\LoginForm;
use App\Models\User;
use Livewire\Livewire;

function authFormXPath(string $html): DOMXPath
{
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);

    return new DOMXPath($document);
}

it('associates descriptions with explanatory text rather than the control itself', function () {
    $xpath = authFormXPath(Livewire::test('home.login-form')->html());

    foreach ($xpath->query('//*[@aria-describedby]') as $control) {
        if (! $control instanceof DOMElement) {
            $this->fail('Expected an element with an accessible description.');
        }
        foreach (explode(' ', $control->getAttribute('aria-describedby')) as $id) {
            expect($id)->not->toBeEmpty()->not->toBe($control->getAttribute('id'));
            $description = $xpath->query('//*[@id="'.$id.'"]');
            expect($description)->toHaveCount(1)->and(trim($description->item(0)->textContent))->not->toBeEmpty();
        }
    }
});

it('renders the password confirmation rejection beside the registration field', function () {
    app()->setLocale('en');

    $form = Livewire::test('home.login-form');
    expect($form->instance())->toBeInstanceOf(LoginForm::class);

    $form->set('registerName', 'Form Tester')
        ->set('registerEmail', 'form@example.test')
        ->set('registerPassword', 'valid-password')
        ->set('registerPasswordConfirm', 'different-password')
        ->call('register')
        ->assertHasErrors(['registerPasswordConfirm'])
        ->assertSee('Passwords do not match.')
        ->assertSeeHtml('id="register-password-confirm-error"');

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'form@example.test']);
});

it('associates every auth field with its localized visible label and native input semantics', function (string $locale, array $labels) {
    app()->setLocale($locale);
    $response = $this->get(route('login'))->assertOk()->assertSeeLivewire('home.login-form');
    $xpath = authFormXPath($response->getContent());

    foreach ([
        ['email', 'login-email', 'email', 'username', 'email'],
        ['password', 'login-password', 'password', 'current-password', 'password'],
        ['registerName', 'register-name', 'text', 'name', 'name'],
        ['registerEmail', 'register-email', 'email', 'email', 'email'],
        ['registerPassword', 'register-password', 'password', 'new-password', 'password'],
        ['registerPasswordConfirm', 'register-password-confirm', 'password', 'new-password', 'confirmation'],
    ] as [$property, $id, $type, $autocomplete, $label]) {
        $fields = $xpath->query('//input[@*[name()="wire:model"]="'.$property.'"]');
        expect($fields)->toHaveCount(1);
        $field = $fields->item(0);
        expect($field)->toBeInstanceOf(DOMElement::class);
        expect($field->getAttribute('id'))->toBe($id)
            ->and($field->getAttribute('type'))->toBe($type)
            ->and($field->getAttribute('autocomplete'))->toBe($autocomplete)
            ->and($field->hasAttribute('required'))->toBeTrue();
        $labelNodes = $xpath->query('//label[@for="'.$id.'"]');
        expect($labelNodes)->toHaveCount(1)
            ->and(trim($labelNodes->item(0)->textContent))->toBe($labels[$label]);
    }
})->with([
    'English' => ['en', ['email' => 'Email', 'password' => 'Password', 'name' => 'Name', 'confirmation' => 'Confirm password']],
    'Portuguese' => ['pt_BR', ['email' => 'E-mail', 'password' => 'Senha', 'name' => 'Nome', 'confirmation' => 'Confirme a senha']],
]);

it('renders localized server validation with an associated alert for each field', function (string $locale, string $property, string $id, string $label) {
    app()->setLocale($locale);
    $method = str_starts_with($property, 'register') ? 'register' : 'login';
    $form = Livewire::test('home.login-form')->set([
        'email' => 'login@example.test',
        'password' => 'valid-password',
        'registerName' => 'Form Tester',
        'registerEmail' => 'form@example.test',
        'registerPassword' => 'valid-password',
        'registerPasswordConfirm' => 'valid-password',
    ])->set($property, '')->call($method)->assertHasErrors([$property => 'required']);
    $message = $locale === 'en' ? "The {$label} field is required." : "O campo {$label} é obrigatório.";
    $form->assertSee($message);
    $xpath = authFormXPath($form->html());
    $field = $xpath->query('//input[@id="'.$id.'"]')->item(0);
    expect($field)->toBeInstanceOf(DOMElement::class);
    expect($field->getAttribute('aria-invalid'))->toBe('true')
        ->and($field->getAttribute('aria-describedby'))->toBe($id.'-error');
    $alerts = $xpath->query('//*[@id="'.$id.'-error" and @role="alert"]');
    expect($alerts)->toHaveCount(1)
        ->and(trim($alerts->item(0)->textContent))->toBe($message);
    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
})->with([
    ['en', 'email', 'login-email', 'Email'],
    ['en', 'password', 'login-password', 'Password'],
    ['en', 'registerName', 'register-name', 'Name'],
    ['en', 'registerEmail', 'register-email', 'Email'],
    ['en', 'registerPassword', 'register-password', 'Password'],
    ['en', 'registerPasswordConfirm', 'register-password-confirm', 'Confirm password'],
    ['pt_BR', 'email', 'login-email', 'E-mail'],
    ['pt_BR', 'password', 'login-password', 'Senha'],
    ['pt_BR', 'registerName', 'register-name', 'Nome'],
    ['pt_BR', 'registerEmail', 'register-email', 'E-mail'],
    ['pt_BR', 'registerPassword', 'register-password', 'Senha'],
    ['pt_BR', 'registerPasswordConfirm', 'register-password-confirm', 'Confirme a senha'],
]);

it('shows localized domain rejections without authenticating or registering a second account', function (string $locale, string $failure, string $message, string $error) {
    app()->setLocale($locale);
    User::factory()->create(['email' => 'existing@example.test']);
    $form = Livewire::test('home.login-form');

    if ($failure === 'credentials') {
        $form->set('email', 'existing@example.test')->set('password', 'incorrect-password')->call('login');
    } else {
        $form->set('registerName', 'Form Tester')
            ->set('registerEmail', $failure === 'duplicate' ? 'existing@example.test' : 'new@example.test')
            ->set('registerPassword', 'valid-password')
            ->set('registerPasswordConfirm', $failure === 'mismatch' ? 'other-password' : 'valid-password')
            ->call('register');
    }

    $form->assertHasErrors([$error])->assertSee($message)->assertNotDispatched('notification');
    $xpath = authFormXPath($form->html());
    $alerts = $xpath->query('//*[@role="alert"]');
    expect($alerts)->toHaveCount(1)->and(trim($alerts->item(0)->textContent))->toBe($message);
    $this->assertGuest();
    $this->assertDatabaseCount('users', 1);
})->with([
    ['en', 'credentials', 'Unable to sign in. Check your email and password, or try again shortly.', 'loginError'],
    ['en', 'duplicate', 'This email is already registered.', 'registerEmail'],
    ['en', 'mismatch', 'Passwords do not match.', 'registerPasswordConfirm'],
    ['pt_BR', 'credentials', 'Não foi possível entrar. Confira seu e-mail e senha ou tente novamente em instantes.', 'loginError'],
    ['pt_BR', 'duplicate', 'Este e-mail já está cadastrado.', 'registerEmail'],
    ['pt_BR', 'mismatch', 'As senhas não coincidem.', 'registerPasswordConfirm'],
]);
