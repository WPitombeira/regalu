<?php

use App\Models\User;
use Livewire\Livewire;
use App\Livewire\Profile\Settings;

describe('profile settings', function () {
    it('can view settings page', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings'))
            ->assertOk();
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

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->set('currentPassword', 'correctpassword')
            ->set('newPassword', 'newpassword123')
            ->set('newPasswordConfirm', 'newpassword123')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertDispatched('notification');
    });
});
