<?php

use App\Livewire\Profile\Settings;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

describe('profile settings', function () {
    it('rejects a component refresh after logout', function () {
        $component = Livewire::actingAs(User::factory()->create())->test(Settings::class);
        Auth::logout();

        $component->call('$refresh')->assertUnauthorized();
    });

    it('can view settings page', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings'))
            ->assertOk()
            ->assertSee('wire:submit="updatePassword"', false);
    });

    it('can update name and phone', function () {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'phone' => '1234567890',
        ]);

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->set('name', 'Updated Name')
            ->set('phone', '0987654321')
            ->call('updateProfile')
            ->assertHasNoErrors()
            ->assertDispatched('notification');

        $user->refresh();
        expect($user->name)->toBe('Updated Name');
        expect($user->phone)->toBe('0987654321');
    });

    it('cannot update password with wrong current password', function () {
        $user = User::factory()->create([
            'password' => 'correctpassword',
        ]);

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->set('currentPassword', 'wrongpassword')
            ->set('newPassword', 'newpassword123')
            ->set('newPasswordConfirm', 'newpassword123')
            ->call('updatePassword')
            ->assertHasErrors(['currentPassword']);
    });

    it('can update password with correct current password', function () {
        $user = User::factory()->create([
            'password' => 'correctpassword',
        ]);
        $oldRememberToken = $user->remember_token;

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->set('currentPassword', 'correctpassword')
            ->set('newPassword', 'newpassword123')
            ->set('newPasswordConfirm', 'newpassword123')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertDispatched('notification');

        $user->refresh();
        expect(Hash::check('newpassword123', $user->password))->toBeTrue();
        expect($user->remember_token)->not->toBe($oldRememberToken);
    });
});
