<?php

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

it('names recovery validation fields in the active locale', function (string $locale, array $messages, bool $reset) {
    app()->setLocale($locale);
    Notification::fake();

    $this->post(route($reset ? 'password.update' : 'password.email'), [])
        ->assertSessionHasErrors($reset ? $messages : ['email' => $messages['email']]);

    Notification::assertNothingSent();
    $this->assertGuest();
})->with([
    ['en', ['email' => 'The email field is required.', 'password' => 'The new password field is required.', 'token' => 'The reset link field is required.']],
    ['pt_BR', ['email' => 'O campo e-mail é obrigatório.', 'password' => 'O campo nova senha é obrigatório.', 'token' => 'O campo link de redefinição é obrigatório.']],
])->with([false, true]);

it('returns the same localized invalid-link feedback for known and unknown accounts', function (string $locale, string $message, bool $registered) {
    app()->setLocale($locale);
    $user = $registered ? User::factory()->create(['email' => 'recovery@example.test']) : null;
    $before = $user?->fresh()->getAttributes();
    $password = Str::random(32);

    $this->from(route('password.reset', ['token' => 'invalid-link']))
        ->post(route('password.update'), [
            'email' => 'recovery@example.test',
            'token' => 'invalid-link',
            'password' => $password,
            'password_confirmation' => $password,
        ])
        ->assertRedirect(route('password.reset', ['token' => 'invalid-link']))
        ->assertSessionHasErrors(['email' => $message]);

    expect($user?->fresh()->getAttributes() === $before)->toBeTrue();
    $this->assertGuest();
})->with([
    ['en', 'This reset link is invalid or has expired. Request another link and check the email address.'],
    ['pt_BR', 'Este link de redefinição é inválido ou expirou. Solicite outro link e confira o endereço de e-mail.'],
])->with([true, false]);

it('localizes successful recovery without changing the credential rotation contract', function (string $locale, string $message) {
    app()->setLocale($locale);
    $user = User::factory()->create();
    $before = $user->remember_token;
    $password = Str::random(32);
    $token = Password::createToken($user);

    $this->post(route('password.update'), [
        'email' => $user->email,
        'token' => $token,
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertRedirect(route('dashboard'))->assertSessionHas('status', $message);

    $user->refresh();
    expect(Hash::check($password, $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe($before)
        ->and(Password::tokenExists($user, $token))->toBeFalse();
    $this->assertAuthenticatedAs($user);
})->with([
    ['en', 'Your password has been reset.'],
    ['pt_BR', 'Sua senha foi redefinida.'],
]);

it('keeps complete recovery catalogs without fallback', function () {
    $english = Arr::dot(require lang_path('en/password_recovery.php'));
    $portuguese = Arr::dot(require lang_path('pt_BR/password_recovery.php'));
    expect(array_keys($portuguese))->toBe(array_keys($english));
    foreach (['en' => $english, 'pt_BR' => $portuguese] as $locale => $catalog) {
        foreach ($catalog as $key => $value) {
            expect($value)->toBeString()->not->toBeEmpty();
            expect(app('translator')->hasForLocale('password_recovery.'.$key, $locale))->toBeTrue();
        }
    }
});
