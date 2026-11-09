<?php

use App\Livewire\Home\LoginForm;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    config(['cache.default' => 'array', 'cache.limiter' => 'array']);
});

it('shares the login attempt limit from HTTP requests with Livewire login', function () {
    $user = User::factory()->create([
        'email' => 'shared-http-limit@example.com',
        'password' => 'correct-password',
    ]);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('authenticate'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    Livewire::test(LoginForm::class)
        ->set('email', $user->email)
        ->set('password', 'correct-password')
        ->call('login')
        ->assertHasErrors(['loginError']);

    $this->assertGuest();
});

it('shares the login attempt limit from Livewire with HTTP requests', function () {
    $user = User::factory()->create([
        'email' => 'shared-livewire-limit@example.com',
        'password' => 'correct-password',
    ]);

    foreach (range(1, 5) as $attempt) {
        Livewire::test(LoginForm::class)
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login');
    }

    $this->from(route('login'))
        ->post(route('authenticate'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});
