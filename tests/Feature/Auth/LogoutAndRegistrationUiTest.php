<?php

use App\Livewire\Home\LoginForm;
use App\Models\User;
use Livewire\Livewire;

it('binds the registration name input to the validated component property', function () {
    Livewire::test(LoginForm::class)
        ->assertSeeHtml('wire:model="registerName"')
        ->assertDontSeeHtml('wire:model="name"');
});

it('renders dashboard navigation and a CSRF-protected POST logout form', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('settings'))
        ->assertOk()
        ->assertSee(route('dashboard'), false)
        ->assertSee(route('logout'), false)
        ->assertSee('method="POST"', false)
        ->assertSee('name="_token"', false);
});

it('does not expose logout through GET', function () {
    $this->actingAs(User::factory()->create())
        ->get('/logout')
        ->assertStatus(405);
});

it('logs out through POST and invalidates the session', function () {
    $oldToken = str_repeat('a', 40);

    $this->actingAs(User::factory()->create())
        ->withSession([
            '_token' => $oldToken,
            'sensitive_state' => 'must be removed',
        ])
        ->post('/logout')
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect(session()->has('sensitive_state'))->toBeFalse();
    expect(session()->token())->not->toBe($oldToken);
});
