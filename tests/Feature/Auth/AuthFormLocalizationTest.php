<?php

use App\Models\User;
use Illuminate\Support\Arr;
use Livewire\Livewire;

it('renders the complete localized login and registration copy', function (string $locale, array $copy) {
    app()->setLocale($locale);
    $form = Livewire::test('home.login-form');

    foreach ($copy as $text) {
        $form->assertSee($text);
    }

    $form->assertDontSee('auth_forms.')->assertDontSee('messages.');
})->with([
    ['en', ['Sign in to your account', 'Remember me', 'Forgot your password?', 'Sign in', "Don't have an account?", 'Create an account', 'Already have an account?', 'Use at least 8 characters.']],
    ['pt_BR', ['Entre na sua conta', 'Manter conectado', 'Esqueceu sua senha?', 'Entrar', 'Ainda não tem uma conta?', 'Criar conta', 'Já tem uma conta?', 'Use pelo menos 8 caracteres.']],
]);

it('dispatches localized success only after the real auth operation', function (string $locale, string $method, string $message) {
    app()->setLocale($locale);
    $form = Livewire::test('home.login-form');

    if ($method === 'login') {
        User::factory()->create(['email' => 'auth@example.test', 'password' => 'valid-password']);
        $form->set('email', 'auth@example.test')->set('password', 'valid-password');
    } else {
        $form->set('registerName', 'Auth Tester')->set('registerEmail', 'auth@example.test')
            ->set('registerPassword', 'valid-password')->set('registerPasswordConfirm', 'valid-password');
    }

    $form->call($method)->assertHasNoErrors()->assertRedirect(route('dashboard'))
        ->assertDispatched('notification', ['message' => $message, 'type' => 'success']);
    $this->assertAuthenticatedAs(User::where('email', 'auth@example.test')->sole());
    $this->assertDatabaseCount('users', 1);
})->with([
    ['en', 'login', 'You are signed in.'],
    ['en', 'register', 'Your account has been created.'],
    ['pt_BR', 'login', 'Você entrou na sua conta.'],
    ['pt_BR', 'register', 'Sua conta foi criada.'],
]);

it('defines the same nonempty auth form keys in each own locale without fallback', function () {
    $english = Arr::dot(require lang_path('en/auth_forms.php'));
    $portuguese = Arr::dot(require lang_path('pt_BR/auth_forms.php'));
    expect(array_keys($portuguese))->toBe(array_keys($english));

    foreach ([$english, $portuguese] as $catalog) {
        foreach ($catalog as $text) {
            expect($text)->toBeString()->not->toBeEmpty();
        }
    }
});

it('makes unavailable identity providers explicitly disabled with localized explanation', function (string $locale, string $notice) {
    app()->setLocale($locale);
    $form = Livewire::test('home.login-form')->assertSee($notice);
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$form->html(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);

    foreach (['Apple', 'Google'] as $provider) {
        $buttons = $xpath->query('//button[span[normalize-space(.)="'.$provider.'"]]');
        expect($buttons)->toHaveCount(2);

        foreach ($buttons as $button) {
            if (! $button instanceof DOMElement) {
                $this->fail('Expected a provider button element.');
            }
            expect($button->hasAttribute('disabled'))->toBeTrue();
            $id = $button->getAttribute('aria-describedby');
            expect($id)->not->toBeEmpty();
            $description = $xpath->query('//*[@id="'.$id.'"]');
            expect($description)->toHaveCount(1)->and(trim($description->item(0)->textContent))->toBe($notice);
        }
    }
})->with([
    ['en', 'Apple and Google sign-in are not available. Use your email and password.'],
    ['pt_BR', 'O acesso com Apple e Google não está disponível. Use seu e-mail e senha.'],
]);
