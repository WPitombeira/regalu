<?php

use App\Models\User;
use Livewire\Livewire;
use App\Livewire\Home\LoginForm;

describe('registration', function () {
    it('can register with valid data', function () {
        Livewire::test(LoginForm::class)
            ->set('registerName', 'Test User')
            ->set('registerEmail', 'newuser@example.com')
            ->set('registerPassword', 'password123')
            ->set('registerPasswordConfirm', 'password123')
            ->call('register')
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'newuser@example.com',
        ]);

        $this->assertAuthenticated();
    });

    it('cannot register with duplicate email', function () {
        User::factory()->create(['email' => 'existing@example.com']);

        Livewire::test(LoginForm::class)
            ->set('registerName', 'Another User')
            ->set('registerEmail', 'existing@example.com')
            ->set('registerPassword', 'password123')
            ->set('registerPasswordConfirm', 'password123')
            ->call('register')
            ->assertHasErrors(['registerEmail']);

        $this->assertGuest();
    });

    it('cannot register with mismatched passwords', function () {
        Livewire::test(LoginForm::class)
            ->set('registerName', 'Test User')
            ->set('registerEmail', 'mismatch@example.com')
            ->set('registerPassword', 'password123')
            ->set('registerPasswordConfirm', 'differentpassword')
            ->call('register')
            ->assertHasErrors(['registerPasswordConfirm']);

        $this->assertDatabaseMissing('users', [
            'email' => 'mismatch@example.com',
        ]);

        $this->assertGuest();
    });

    it('cannot register with missing fields', function () {
        Livewire::test(LoginForm::class)
            ->set('registerName', '')
            ->set('registerEmail', '')
            ->set('registerPassword', '')
            ->set('registerPasswordConfirm', '')
            ->call('register')
            ->assertHasErrors(['registerName', 'registerEmail', 'registerPassword', 'registerPasswordConfirm']);

        $this->assertGuest();
    });
});
