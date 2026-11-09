<?php

use App\Livewire\Home\LoginForm;
use App\Models\User;
use Livewire\Livewire;

it('authenticates valid HTTP credentials and redirects to the dashboard', function () {
    $user = User::factory()->create([
        'email' => 'http-login@example.com',
        'password' => 'correct-password',
    ]);

    $this->post(route('authenticate'), [
        'email' => $user->email,
        'password' => 'correct-password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('rejects invalid HTTP credentials without authenticating', function () {
    $user = User::factory()->create([
        'email' => 'http-failure@example.com',
        'password' => 'correct-password',
    ]);

    $this->from(route('login'))
        ->post(route('authenticate'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('rejects a non-string HTTP password before attempting authentication', function () {
    $this->from(route('login'))
        ->post(route('authenticate'), [
            'email' => 'malformed@example.com',
            'password' => ['not-a-password'],
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('password');

    $this->assertGuest();
});

it('authenticates through Livewire without validating registration fields', function () {
    $user = User::factory()->create([
        'email' => 'livewire-login@example.com',
        'password' => 'correct-password',
    ]);

    Livewire::test(LoginForm::class)
        ->set('email', $user->email)
        ->set('password', 'correct-password')
        ->call('login')
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('rejects a session bound to the previous password hash', function () {
    $user = User::factory()->create(['password' => 'old-password']);
    $previousPasswordHash = $user->password;

    $user->forceFill(['password' => 'new-password'])->save();

    $this->actingAs($user)
        ->withSession(['password_hash_web' => $previousPasswordHash])
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('rejects invalid Livewire credentials without authenticating', function () {
    $user = User::factory()->create([
        'email' => 'livewire-failure@example.com',
        'password' => 'correct-password',
    ]);

    Livewire::test(LoginForm::class)
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors(['loginError']);

    $this->assertGuest();
});

it('treats a missing login provider name as invalid', function () {
    expect((new LoginForm)->isValidProvider())->toBeFalse();
});

it('requires login for shared entry points and preserves the intended URL', function (string $path) {
    $this->get($path)
        ->assertRedirect(route('login'))
        ->assertSessionHas('url.intended', url($path));
})->with(['/join-event/example-invite', '/w/1']);

it('rejects stale password sessions on shared entry points', function (string $path) {
    $user = User::factory()->create(['password' => 'old-password']);
    $previousPasswordHash = $user->password;
    $user->forceFill(['password' => 'new-password'])->save();

    $this->actingAs($user)
        ->withSession(['password_hash_web' => $previousPasswordHash])
        ->get($path)
        ->assertRedirect(route('login'));

    $this->assertGuest();
})->with(['/join-event/example-invite', '/w/1']);

it('does not create a remember token when remember is explicitly false', function () {
    $user = User::factory()->create(['password' => 'correct-password', 'remember_token' => null]);

    $this->post(route('authenticate'), [
        'email' => $user->email,
        'password' => 'correct-password',
        'remember' => '0',
    ])->assertRedirect(route('dashboard'));

    expect($user->fresh()->remember_token)->toBeNull();
});
