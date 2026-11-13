<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

it('acknowledges reset requests honestly without disclosing account existence', function (string $locale, string $message, bool $registered) {
    config(['app.locale' => $locale]);
    app()->setLocale($locale);
    Notification::fake();
    if ($registered) {
        $user = User::factory()->create(['email' => 'recovery@example.test']);
    }

    $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => 'recovery@example.test'])
        ->assertRedirect(route('password.request'))
        ->assertSessionHas('status', $message)
        ->assertSessionHasNoErrors();

    if ($registered) {
        Notification::assertSentTo($user, ResetPassword::class);
    } else {
        Notification::assertNothingSent();
    }
    $this->assertGuest();
})->with([
    ['en', 'If an account matches this email, you will receive a password reset link. Please check your inbox and spam folder.'],
    ['pt_BR', 'Se houver uma conta com este e-mail, você receberá um link para redefinir sua senha. Confira sua caixa de entrada e a pasta de spam.'],
])->with([true, false]);

function recoveryDocument(string $html): DOMXPath
{
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);

    return new DOMXPath($document);
}

it('renders localized recovery forms with native constraints and recovery navigation', function (string $locale, array $copy, bool $reset) {
    app()->setLocale($locale);
    $route = $reset ? route('password.reset', ['token' => 'presentation-only', 'email' => 'reader@example.test']) : route('password.request');
    $response = $this->get($route)->assertOk();
    $html = $response->getContent();
    $xpath = recoveryDocument($html);
    $response->assertSee($copy[$reset ? 'reset' : 'forgot'])->assertSee($copy['back'])
        ->assertDontSee('messages.')->assertDontSee('password_recovery.');

    $form = $xpath->query('//main//form')->item(0);
    if (! $form instanceof DOMElement) {
        $this->fail('Recovery form is missing.');
    }
    expect($form->getAttribute('method'))->toBe('POST')
        ->and($form->getAttribute('action'))->toBe(route($reset ? 'password.update' : 'password.email'))
        ->and($form->hasAttribute('novalidate'))->toBeFalse();
    expect($xpath->query('//main//input[@name="_token"]'))->toHaveCount(1);
    expect($xpath->query('//main//a[@href="'.route('login').'"]'))->toHaveCount(1);

    $names = $reset ? ['email', 'password', 'password_confirmation'] : ['email'];
    foreach ($names as $name) {
        $input = $xpath->query('//main//input[@name="'.$name.'"]')->item(0);
        if (! $input instanceof DOMElement) {
            $this->fail('Recovery input is missing.');
        }
        expect($input->hasAttribute('required'))->toBeTrue()
            ->and($input->getAttribute('autocomplete'))->toBe($name === 'email' ? 'email' : 'new-password');
        $labels = $xpath->query('//main//label[@for="'.$input->getAttribute('id').'"]');
        expect($labels)->toHaveCount(1)->and(trim($labels->item(0)->textContent))->toBe($copy[$name]);
        if ($name !== 'email') {
            expect($input->getAttribute('minlength'))->toBe('8')
                ->and($input->getAttribute('value'))->toBe('');
        }
    }
    if ($reset) {
        $response->assertSee($copy['hint'])->assertSee($copy['another']);
        expect($xpath->query('//main//a[@href="'.route('password.request').'"]'))->toHaveCount(1);
        $emailInput = $xpath->query('//input[@name="email"]')->item(0);
        if (! $emailInput instanceof DOMElement) {
            $this->fail('Recovery email is missing.');
        }
        expect($emailInput->getAttribute('value'))->toBe('reader@example.test');
    }
})->with([
    ['en', ['forgot' => 'Forgot your password?', 'reset' => 'Choose a new password', 'email' => 'Email', 'password' => 'New password', 'password_confirmation' => 'Confirm new password', 'back' => 'Back to sign in', 'another' => 'Request another reset link', 'hint' => 'Use at least 8 characters.']],
    ['pt_BR', ['forgot' => 'Esqueceu sua senha?', 'reset' => 'Escolha uma nova senha', 'email' => 'E-mail', 'password' => 'Nova senha', 'password_confirmation' => 'Confirme a nova senha', 'back' => 'Voltar para entrar', 'another' => 'Solicitar outro link de redefinição', 'hint' => 'Use pelo menos 8 caracteres.']],
])->with([false, true]);

it('associates each rendered recovery error with its field and escapes feedback', function (bool $reset) {
    // Match the configured JSON session wire shape; Store::start marshals it.
    $errors = ['default' => ['format' => ':message', 'messages' => [
        'email' => ['Email <invalid>'],
        ...($reset ? ['password' => ['Passwords <mismatch>'], 'token' => ['Link <invalid>']] : []),
    ]]];
    $response = $this->withSession(['errors' => $errors])->get($reset
        ? route('password.reset', ['token' => 'presentation-only']) : route('password.request'));
    $response->assertOk()->assertSee('Email <invalid>')->assertDontSee('Email <invalid>', false);
    $xpath = recoveryDocument($response->getContent());

    foreach ($reset ? ['email', 'password', 'password_confirmation'] : ['email'] as $name) {
        $input = $xpath->query('//main//input[@name="'.$name.'"]')->item(0);
        if (! $input instanceof DOMElement) {
            $this->fail('Recovery input is missing.');
        }
        expect($input->getAttribute('aria-invalid'))->toBe('true');
        $expected = $name === 'email' ? 'Email <invalid>' : 'Passwords <mismatch>';
        $descriptions = preg_split('/\s+/', trim($input->getAttribute('aria-describedby')));
        $texts = [];
        foreach ($descriptions as $id) {
            expect($id)->not->toBeEmpty()->not->toBe($input->getAttribute('id'));
            $nodes = $xpath->query('//*[@id="'.$id.'"]');
            expect($nodes)->toHaveCount(1);
            $texts[] = trim($nodes->item(0)->textContent);
        }
        expect($texts)->toContain($expected);
    }
    if ($reset) {
        expect($xpath->query('//*[@role="alert" and @id="token-error"]'))->toHaveCount(1);
    }
})->with([false, true]);

it('announces the recovery acknowledgement as status feedback', function () {
    $response = $this->withSession(['status' => 'Request <acknowledged>'])->get(route('password.request'))->assertOk();
    $xpath = recoveryDocument($response->getContent());
    $nodes = $xpath->query('//main//*[@role="status"]');
    expect($nodes)->toHaveCount(1)->and(trim($nodes->item(0)->textContent))->toBe('Request <acknowledged>');
    $response->assertDontSee('Request <acknowledged>', false);
});

it('discards malformed recovery email prefills without breaking the page', function (bool $reset, bool $oldInput) {
    if ($oldInput) {
        $this->withSession(['_old_input' => ['email' => ['malformed']]]);
    }
    $route = $reset ? route('password.reset', ['token' => 'presentation-only', 'email' => ['malformed']])
        : route('password.request');
    $response = $this->get($route)->assertOk();
    $xpath = recoveryDocument($response->getContent());
    $input = $xpath->query('//main//input[@name="email"]')->item(0);
    if (! $input instanceof DOMElement) {
        $this->fail('Recovery email is missing.');
    }
    expect($input->getAttribute('value'))->toBe('');
    $this->assertGuest();
})->with([false, true])->with([false, true]);

it('preserves and escapes scalar recovery email prefills', function (bool $reset) {
    $email = 'reader"<probe>@example.test';
    $this->withSession(['_old_input' => ['email' => $email]]);
    $response = $this->get($reset ? route('password.reset', ['token' => 'presentation-only', 'email' => 'fallback@example.test'])
        : route('password.request'))->assertOk();
    $xpath = recoveryDocument($response->getContent());
    $input = $xpath->query('//main//input[@name="email"]')->item(0);
    if (! $input instanceof DOMElement) {
        $this->fail('Recovery email is missing.');
    }
    expect($input->getAttribute('value'))->toBe($email);
    expect($xpath->query('//probe'))->toHaveCount(0);
})->with([false, true]);
