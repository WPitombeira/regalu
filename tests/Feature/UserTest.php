<?php

use App\Livewire\Profile\Settings;
use App\Models\User;
use Livewire\Livewire;

pest()->group('user');

describe('account profile', function () {
    it('shows an authenticated user their own profile information', function () {
        $user = User::factory()->create([
            'name' => 'Current Account',
            'email' => 'current-account@example.com',
        ]);
        $otherUser = User::factory()->create([
            'name' => 'Other Account',
            'email' => 'other-account@example.com',
        ]);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('Current Account')
            ->assertSee('current-account@example.com')
            ->assertDontSee('other-account@example.com');
    });

    it('does not expose another user profile at a public user URL', function () {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get('/users/'.$user->id)
            ->assertNotFound();
    });

    it('allows a user to edit their own profile', function () {
        $user = User::factory()->create(['name' => 'Before Update']);

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->set('name', 'After Update')
            ->call('updateProfile')
            ->assertHasNoErrors();

        expect($user->fresh()->name)->toBe('After Update');
    });

    it('does not let a settings query target another user account', function () {
        $user = User::factory()->create([
            'name' => 'Signed In Account',
            'email' => 'signed-in@example.com',
        ]);
        $otherUser = User::factory()->create([
            'name' => 'Unrelated Account',
            'email' => 'unrelated@example.com',
        ]);

        $this->actingAs($user)
            ->get(route('settings', ['user' => $otherUser->id]))
            ->assertOk()
            ->assertSee('signed-in@example.com')
            ->assertDontSee('unrelated@example.com');
    });

    it('keeps profile edits scoped to the signed-in user', function () {
        $user = User::factory()->create(['name' => 'Signed In Name']);
        $otherUser = User::factory()->create(['name' => 'Unchanged Name']);

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->set('name', 'Updated Signed In Name')
            ->call('updateProfile')
            ->assertHasNoErrors();

        expect($user->fresh()->name)->toBe('Updated Signed In Name');
        expect($otherUser->fresh()->name)->toBe('Unchanged Name');
    });

    it('provides an authenticated user with the profile route', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('wire:submit="updatePassword"', false);
    });

    it('requires authentication to view account settings', function () {
        $this->get(route('settings'))
            ->assertRedirect(route('login'));
    });
});
